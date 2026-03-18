<?php
/**
 * Branch.php — Professional Branch Model
 *
 * Provides CRUD and management operations for branches:
 * - Multi-branch organization support
 * - Status tracking: Active / Inactive
 * - Contact information management
 * - Branch statistics and enrollment tracking
 * - Full audit trail with timestamps
 *
 * @package SBVS\Models
 * @version 2.0
 */

class Branch
{
    private PDO    $conn;
    private string $table = 'branches';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        $this->ensureSchema();
    }

    /**
     * Ensure the branches table exists with proper schema
     */
    private function ensureSchema(): void
    {
        // Create table if missing
        $this->conn->exec("CREATE TABLE IF NOT EXISTS {$this->table} (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(120) NOT NULL UNIQUE,
            address     TEXT         NULL,
            phone       VARCHAR(20)  NULL,
            email       VARCHAR(100) NULL,
            status      ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Migrate: Add missing columns to existing installations
        $newCols = [
            'name'       => "VARCHAR(120) NOT NULL UNIQUE",
            'address'    => "TEXT NULL",
            'phone'      => "VARCHAR(20) NULL",
            'email'      => "VARCHAR(100) NULL",
            'status'     => "ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'",
            'created_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
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
                } catch (\Throwable $e) {
                    // Non-fatal: column may already exist with different definition
                }
            }
        }
    }

    /**
     * Get all branches with optional filtering
     *
     * @param string|null $status Filter by status (Active, Inactive, or null for all)
     * @param int $limit Limit results
     * @param int $offset Pagination offset
     * @return array Array of branch records
     */
    public function getAll(?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $where = '1=1';
        $params = [];

        if ($status && in_array($status, ['Active', 'Inactive'])) {
            $where = 'status = ?';
            $params[] = $status;
        }

        $sql = "SELECT * FROM {$this->table}
                WHERE {$where}
                ORDER BY name ASC
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single branch by ID with statistics
     *
     * @param int $id Branch ID
     * @return array|null Branch record with stats or null if not found
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT b.*,
                    COUNT(DISTINCT c.id) AS course_count,
                    COUNT(DISTINCT s.id) AS student_count,
                    COUNT(DISTINCT t.id) AS teacher_count
             FROM {$this->table} b
             LEFT JOIN courses c ON c.branch_id = b.id AND c.status = 'Active'
             LEFT JOIN students s ON s.branch_id = b.id
             LEFT JOIN teachers t ON t.branch_id = b.id
             WHERE b.id = ?
             GROUP BY b.id"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Search branches by name, email, or phone
     *
     * @param string $query Search query
     * @return array Search results
     */
    public function search(string $query): array
    {
        $query = '%' . $query . '%';
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?
             ORDER BY name ASC
             LIMIT 50"
        );
        $stmt->execute([$query, $query, $query]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new branch
     *
     * @param array $data Branch data (name, address, phone, email, status)
     * @return int|false Branch ID on success, false on failure
     */
    public function create(array $data): int|false
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table}
                (name, address, phone, email, status)
             VALUES (?, ?, ?, ?, ?)"
        );

        $ok = $stmt->execute([
            trim($data['name'] ?? ''),
            trim($data['address'] ?? '') ?: null,
            trim($data['phone'] ?? '') ?: null,
            strtolower(trim($data['email'] ?? '')) ?: null,
            $data['status'] ?? 'Active',
        ]);

        return $ok ? (int)$this->conn->lastInsertId() : false;
    }

    /**
     * Update an existing branch
     *
     * @param int $id Branch ID
     * @param array $data Updated branch data
     * @return bool Success status
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table}
             SET name = ?, address = ?, phone = ?, email = ?, status = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            trim($data['name'] ?? ''),
            trim($data['address'] ?? '') ?: null,
            trim($data['phone'] ?? '') ?: null,
            strtolower(trim($data['email'] ?? '')) ?: null,
            $data['status'] ?? 'Active',
            $id,
        ]);
    }

    /**
     * Soft-delete a branch (set to Inactive)
     * Hard delete only when $force = true AND no related records exist
     *
     * @param int $id Branch ID
     * @param bool $force Force hard delete if true
     * @return bool|string Success status or error code
     */
    public function delete(int $id, bool $force = false): bool|string
    {
        if ($force) {
            // Check for active courses
            $chk = $this->conn->prepare(
                "SELECT COUNT(*) FROM courses WHERE branch_id = ? AND status = 'Active'"
            );
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) {
                return 'has_courses';
            }

            // Check for active students
            $chk = $this->conn->prepare("SELECT COUNT(*) FROM students WHERE branch_id = ?");
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) {
                return 'has_students';
            }

            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$id]);
        } else {
            // Soft delete: set to Inactive
            $stmt = $this->conn->prepare(
                "UPDATE {$this->table} SET status = 'Inactive' WHERE id = ?"
            );
            return $stmt->execute([$id]);
        }
    }

    /**
     * Restore an inactive branch to Active
     *
     * @param int $id Branch ID
     * @return bool Success status
     */
    public function restore(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET status = 'Active' WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Get branch statistics for dashboard
     *
     * @param int|null $branchId Specific branch or all if null
     * @return array Statistics
     */
    public function getStats(?int $branchId = null): array
    {
        $where = '1=1';
        $params = [];

        if ($branchId) {
            $where = 'b.id = ?';
            $params[] = $branchId;
        }

        $stmt = $this->conn->prepare("
            SELECT
                COUNT(DISTINCT b.id)                                              AS total_branches,
                COUNT(DISTINCT CASE WHEN b.status='Active' THEN b.id END)         AS active_branches,
                COUNT(DISTINCT CASE WHEN b.status='Inactive' THEN b.id END)       AS inactive_branches,
                COUNT(DISTINCT c.id)                                              AS total_courses,
                COUNT(DISTINCT s.id)                                              AS total_students,
                COUNT(DISTINCT t.id)                                              AS total_teachers,
                COALESCE(SUM(CASE WHEN p.status='Active' THEN p.amount END), 0)   AS total_revenue
            FROM {$this->table} b
            LEFT JOIN courses c  ON c.branch_id = b.id
            LEFT JOIN students s ON s.branch_id = b.id
            LEFT JOIN teachers t ON t.branch_id = b.id
            LEFT JOIN enrollments e ON e.student_id = s.id
            LEFT JOIN payments p ON p.enrollment_id = e.id
            WHERE {$where}
        ");

        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get total count of branches
     *
     * @param string|null $status Filter by status
     * @return int Branch count
     */
    public function count(?string $status = null): int
    {
        $where = '1=1';
        $params = [];

        if ($status && in_array($status, ['Active', 'Inactive'])) {
            $where = 'status = ?';
            $params[] = $status;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if a branch name already exists (for uniqueness validation)
     *
     * @param string $name Branch name to check
     * @param int|null $excludeId ID to exclude from check (for updates)
     * @return bool True if name exists, false otherwise
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $where = 'name = ?';
        $params = [trim($name)];

        if ($excludeId) {
            $where .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if a branch email already exists (for uniqueness validation)
     *
     * @param string $email Email to check
     * @param int|null $excludeId ID to exclude from check (for updates)
     * @return bool True if email exists, false otherwise
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $where = 'email = ?';
        $params = [strtolower(trim($email))];

        if ($excludeId) {
            $where .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}
