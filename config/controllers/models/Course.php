<?php
/**
 * Course.php — Professional Course Model
 *
 * Enhancements over original:
 *  - Multi-currency support (pulls symbol from system_settings)
 *  - fee column is total fee (reg + tuition combined) for simple installs;
 *    keeps registration_fee + tuition_fee for detailed breakdowns
 *  - currency_code stored per-course so historical records stay consistent
 *  - status field: Active / Inactive / Archived
 *  - capacity + enrolled count tracking
 *  - enrollment stats joined on every fetch
 *  - soft-delete (archived) instead of hard DELETE
 *  - full audit: created_at / updated_at
 */
class Course {
    private PDO    $conn;
    private string $table    = 'courses';
    private string $settingsTbl = 'system_settings';

    // Currency map: code → symbol
    private const CURRENCY_SYMBOLS = [
        'USD' => '$',  'LRD' => 'L$', 'GHS' => '₵',
        'NGN' => '₦',  'KES' => 'Ksh','ZAR' => 'R',
        'EUR' => '€',  'GBP' => '£',  'XOF' => 'CFA',
        'RWF' => 'Fr', 'ETB' => 'Br', 'TZS' => 'TSh',
    ];

    public function __construct(PDO $db) {
        $this->conn = $db;
        $this->ensureSchema();
    }

    // ── Schema migration ──────────────────────────────────────────────────────
    private function ensureSchema(): void {
        // Create table with full schema if missing
        $this->conn->exec("CREATE TABLE IF NOT EXISTS {$this->table} (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            branch_id         INT          NOT NULL,
            name              VARCHAR(200) NOT NULL,
            code              VARCHAR(50)  NULL UNIQUE,
            duration          VARCHAR(80)  NOT NULL DEFAULT '',
            duration_weeks    SMALLINT     NULL,
            registration_fee  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            tuition_fee       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            currency_code     VARCHAR(5)   NOT NULL DEFAULT 'USD',
            description       TEXT         NULL,
            capacity          SMALLINT     NULL DEFAULT NULL,
            status            ENUM('Active','Inactive','Archived') NOT NULL DEFAULT 'Active',
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add missing columns to existing installations
        $newCols = [
            'code'            => "VARCHAR(50) NULL AFTER name",
            'duration_weeks'  => "SMALLINT NULL AFTER duration",
            'currency_code'   => "VARCHAR(5) NOT NULL DEFAULT 'USD' AFTER tuition_fee",
            'capacity'        => "SMALLINT NULL DEFAULT NULL AFTER description",
            'status'          => "ENUM('Active','Inactive','Archived') NOT NULL DEFAULT 'Active' AFTER capacity",
            'created_at'      => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at'      => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        ];
        foreach ($newCols as $col => $def) {
            $chk = $this->conn->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?"
            );
            $chk->execute([$this->table, $col]);
            if (!(int)$chk->fetchColumn()) {
                try {
                    $this->conn->exec("ALTER TABLE {$this->table} ADD COLUMN `{$col}` {$def}");
                } catch (\Throwable $e) { /* non-fatal */ }
            }
        }
    }

    // ── Currency helpers ──────────────────────────────────────────────────────

    /**
     * Get the institution's default currency from system_settings.
     * Falls back to USD if the table / key doesn't exist yet.
     */
    public function getDefaultCurrency(): string {
        try {
            $stmt = $this->conn->prepare(
                "SELECT setting_val FROM {$this->settingsTbl}
                  WHERE setting_key = 'default_currency' LIMIT 1"
            );
            $stmt->execute();
            return $stmt->fetchColumn() ?: 'USD';
        } catch (\Throwable $e) {
            return 'USD';
        }
    }

    /** Return the currency symbol for a given code. */
    public static function currencySymbol(string $code): string {
        return self::CURRENCY_SYMBOLS[strtoupper($code)] ?? $code;
    }

    /**
     * Format a fee amount with the correct symbol.
     * e.g. formatFee(450.00, 'LRD') → 'L$ 450.00'
     */
    public static function formatFee(float $amount, string $currencyCode = 'USD'): string {
        $symbol = self::currencySymbol($currencyCode);
        return $symbol . ' ' . number_format($amount, 2);
    }

    /** Total fee = registration + tuition */
    public static function totalFee(array $course): float {
        return (float)($course['registration_fee'] ?? 0)
             + (float)($course['tuition_fee']      ?? 0);
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    /**
     * List all courses, optionally filtered by branch and/or status.
     * Includes: branch name, enrolled count, available seats.
     */
    public function getAll(?int $branch_id = null, string $status = 'Active'): array {
        $where  = ['1=1'];
        $params = [];

        if ($branch_id) {
            $where[]  = 'c.branch_id = :branch_id';
            $params['branch_id'] = $branch_id;
        }
        if ($status && $status !== 'all') {
            $where[]  = 'c.status = :status';
            $params['status'] = $status;
        }

        $sql = "SELECT c.*,
                       b.name AS branch_name,
                       COUNT(DISTINCT e.id) AS enrolled_count,
                       CASE WHEN c.capacity IS NULL THEN NULL
                            ELSE GREATEST(0, c.capacity - COUNT(DISTINCT e.id))
                       END AS seats_available
                FROM {$this->table} c
                LEFT JOIN branches b    ON c.branch_id = b.id
                LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'Active'
                WHERE " . implode(' AND ', $where) . "
                GROUP BY c.id
                ORDER BY b.name, c.name";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Get a single course with full detail including enrollment stats. */
    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare(
            "SELECT c.*,
                    b.name AS branch_name,
                    COUNT(DISTINCT e.id) AS enrolled_count,
                    CASE WHEN c.capacity IS NULL THEN NULL
                         ELSE GREATEST(0, c.capacity - COUNT(DISTINCT e.id))
                    END AS seats_available,
                    COALESCE(SUM(p.amount), 0) AS total_revenue
             FROM   {$this->table} c
             LEFT JOIN branches    b ON c.branch_id = b.id
             LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'Active'
             LEFT JOIN payments    p ON p.enrollment_id = e.id AND p.status = 'Active'
             WHERE c.id = :id
             GROUP BY c.id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Search courses by name or code. */
    public function search(string $query, ?int $branch_id = null): array {
        $params = ['q' => '%' . $query . '%'];
        $branchSql = '';
        if ($branch_id) {
            $branchSql = ' AND c.branch_id = :branch_id';
            $params['branch_id'] = $branch_id;
        }
        $stmt = $this->conn->prepare(
            "SELECT c.*, b.name AS branch_name
             FROM {$this->table} c
             LEFT JOIN branches b ON c.branch_id = b.id
             WHERE (c.name LIKE :q OR c.code LIKE :q OR c.description LIKE :q)
               AND c.status = 'Active'
               {$branchSql}
             ORDER BY c.name
             LIMIT 50"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Get all available currencies as code → symbol map. */
    public static function availableCurrencies(): array {
        return self::CURRENCY_SYMBOLS;
    }

    // ── Write operations ──────────────────────────────────────────────────────

    public function create(array $data): bool|int {
        $defaultCurrency = $this->getDefaultCurrency();
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table}
                (branch_id, name, code, duration, duration_weeks,
                 registration_fee, tuition_fee, currency_code,
                 description, capacity, status)
             VALUES
                (:branch_id, :name, :code, :duration, :duration_weeks,
                 :registration_fee, :tuition_fee, :currency_code,
                 :description, :capacity, :status)"
        );
        $ok = $stmt->execute([
            'branch_id'        => (int)$data['branch_id'],
            'name'             => trim($data['name']),
            'code'             => !empty($data['code']) ? strtoupper(trim($data['code'])) : null,
            'duration'         => trim($data['duration']         ?? ''),
            'duration_weeks'   => !empty($data['duration_weeks']) ? (int)$data['duration_weeks'] : null,
            'registration_fee' => (float)($data['registration_fee'] ?? 0),
            'tuition_fee'      => (float)($data['tuition_fee']      ?? 0),
            'currency_code'    => strtoupper(trim($data['currency_code'] ?? $defaultCurrency)),
            'description'      => trim($data['description'] ?? ''),
            'capacity'         => !empty($data['capacity'])  ? (int)$data['capacity']  : null,
            'status'           => $data['status'] ?? 'Active',
        ]);
        return $ok ? (int)$this->conn->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET branch_id        = :branch_id,
                 name             = :name,
                 code             = :code,
                 duration         = :duration,
                 duration_weeks   = :duration_weeks,
                 registration_fee = :registration_fee,
                 tuition_fee      = :tuition_fee,
                 currency_code    = :currency_code,
                 description      = :description,
                 capacity         = :capacity,
                 status           = :status
             WHERE id = :id"
        );
        return $stmt->execute([
            'id'               => $id,
            'branch_id'        => (int)$data['branch_id'],
            'name'             => trim($data['name']),
            'code'             => !empty($data['code']) ? strtoupper(trim($data['code'])) : null,
            'duration'         => trim($data['duration']         ?? ''),
            'duration_weeks'   => !empty($data['duration_weeks']) ? (int)$data['duration_weeks'] : null,
            'registration_fee' => (float)($data['registration_fee'] ?? 0),
            'tuition_fee'      => (float)($data['tuition_fee']      ?? 0),
            'currency_code'    => strtoupper(trim($data['currency_code'] ?? 'USD')),
            'description'      => trim($data['description'] ?? ''),
            'capacity'         => !empty($data['capacity'])  ? (int)$data['capacity']  : null,
            'status'           => $data['status'] ?? 'Active',
        ]);
    }

    /**
     * Soft-delete: archive the course instead of hard-deleting.
     * Hard delete only when $force = true AND no active enrollments exist.
     */
    public function delete(int $id, bool $force = false): bool|string {
        if ($force) {
            // Check for active enrollments first
            $chk = $this->conn->prepare(
                "SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'Active'"
            );
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) {
                return 'has_enrollments';
            }
            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        } else {
            $stmt = $this->conn->prepare(
                "UPDATE {$this->table} SET status = 'Archived' WHERE id = ?"
            );
        }
        return $stmt->execute([$id]);
    }

    /** Restore an archived course back to Active. */
    public function restore(int $id): bool {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET status = 'Active' WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    // ── Statistics ────────────────────────────────────────────────────────────

    /** Summary stats for the dashboard KPI row. */
    public function getStats(?int $branch_id = null): array {
        $where  = '1=1';
        $params = [];
        if ($branch_id) {
            $where    = 'c.branch_id = :branch_id';
            $params['branch_id'] = $branch_id;
        }

        $stmt = $this->conn->prepare("
            SELECT
                COUNT(DISTINCT CASE WHEN c.status='Active' THEN c.id END)          AS active_courses,
                COUNT(DISTINCT CASE WHEN c.status='Inactive' THEN c.id END)        AS inactive_courses,
                COUNT(DISTINCT CASE WHEN c.status='Archived' THEN c.id END)        AS archived_courses,
                COUNT(DISTINCT CASE WHEN e.status='Active' THEN e.id END)          AS total_enrollments,
                COALESCE(SUM(CASE WHEN p.status='Active' THEN p.amount END), 0)    AS total_revenue
            FROM {$this->table} c
            LEFT JOIN enrollments e ON e.course_id = c.id
            LEFT JOIN payments    p ON p.enrollment_id = e.id
            WHERE {$where}
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Top courses by enrollment count. */
    public function getTopCourses(int $limit = 5, ?int $branch_id = null): array {
        $branchSql = $branch_id ? 'AND c.branch_id = ?' : '';
        $params = $branch_id ? [$branch_id] : [];
        $stmt = $this->conn->prepare("
            SELECT c.id, c.name, c.currency_code,
                   COUNT(DISTINCT e.id)             AS enrolled_count,
                   COALESCE(SUM(p.amount), 0)       AS revenue
            FROM {$this->table} c
            LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'Active'
            LEFT JOIN payments    p ON p.enrollment_id = e.id AND p.status = 'Active'
            WHERE c.status = 'Active' {$branchSql}
            GROUP BY c.id
            ORDER BY enrolled_count DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
