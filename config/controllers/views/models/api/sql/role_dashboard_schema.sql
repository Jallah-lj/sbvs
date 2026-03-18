-- Role-Based Dashboard & Access Control Enhancement Schema
-- Date: 2026-03-13
-- Purpose: Add tables for audit logs, system settings, global programs,
--          attendance, and approval workflows per role separation spec.

-- ──────────────────────────────────────────────────────────────────────────────
-- 1) AUDIT LOGS – Super Admin can view all changes system-wide
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    user_name   VARCHAR(150) NOT NULL,
    user_role   VARCHAR(50)  NOT NULL,
    branch_id   INT          NULL,
    action      VARCHAR(100) NOT NULL,
    module      VARCHAR(100) NOT NULL,
    record_id   INT          NULL,
    old_value   LONGTEXT     NULL,
    new_value   LONGTEXT     NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id   (user_id),
    INDEX idx_module    (module),
    INDEX idx_created   (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────────────────────
-- 2) SYSTEM SETTINGS – Global policy settings managed by Super Admin only
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    setting_key  VARCHAR(100) NOT NULL UNIQUE,
    setting_val  TEXT         NOT NULL,
    label        VARCHAR(200) NULL,
    category     VARCHAR(100) NOT NULL DEFAULT 'general',
    updated_by   INT          NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_val, label, category) VALUES
    ('max_discount_pct',    '15',                       'Maximum Discount % (without approval)',          'finance'),
    ('invoice_prefix',      'INV',                       'Invoice Number Prefix',                          'finance'),
    ('currency_symbol',     '$',                         'Currency Symbol',                                'finance'),
    ('school_name',         'Shining Bright Vocational School', 'School / Institution Name',              'general'),
    ('school_email',        '',                          'School Contact Email',                           'general'),
    ('school_phone',        '',                          'School Contact Phone',                           'general'),
    ('data_retention_days', '1095',                      'Data Retention Period (days)',                   'governance'),
    ('allow_cross_branch_reports', '0',                  'Allow Branch Admins to see cross-branch reports','governance'),
    ('sms_provider',        '',                          'SMS Provider Name',                              'integrations'),
    ('smtp_host',           '',                          'SMTP Host for Email',                            'integrations');

-- ──────────────────────────────────────────────────────────────────────────────
-- 3) ATTENDANCE – Branch-level daily attendance per batch/class
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS attendance (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    batch_id      INT          NOT NULL,
    student_id    INT          NOT NULL,
    branch_id     INT          NOT NULL,
    attend_date   DATE         NOT NULL,
    status        ENUM('Present','Absent','Late','Excused') NOT NULL DEFAULT 'Present',
    notes         VARCHAR(255) NULL,
    recorded_by   INT          NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_batch_student_date (batch_id, student_id, attend_date),
    FOREIGN KEY (batch_id)    REFERENCES batches(id)  ON DELETE CASCADE,
    FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id)   REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────────────────────────────────────
-- 5) DISCOUNT APPROVALS – Approval workflow for discounts above policy limit
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS discount_approvals (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT             NOT NULL,
    course_id       INT             NOT NULL,
    branch_id       INT             NOT NULL,
    requested_by    INT             NOT NULL,
    discount_pct    DECIMAL(5,2)    NOT NULL,
    justification   TEXT            NULL,
    status          ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    reviewed_by     INT             NULL,
    review_notes    TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at     TIMESTAMP       NULL,
    FOREIGN KEY (student_id)   REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)    REFERENCES courses(id)  ON DELETE CASCADE,
    FOREIGN KEY (branch_id)    REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by)  REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
