-- Portfolio database schema (MySQL 8 / MariaDB 10.4+).
--
-- Local (XAMPP): create a database named "portfolio" in phpMyAdmin, select it,
-- then Import this file. Safe to re-run: tables are created only if missing.
-- Production: the MySQL container runs this automatically on first start.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Tip ledger (Buy Me a Tea). APPEND-ONLY: every state change is a new row.
-- The current status of a tip is its most recent row. Triggers below reject
-- UPDATE and DELETE, so the record can never be rewritten.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tip_ledger (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tip_id               CHAR(32)        NOT NULL COMMENT 'Public id used for status polling',
    event                VARCHAR(24)     NOT NULL COMMENT 'REQUESTED | SENT | FAILED | COMPLETED | CANCELLED | DUPLICATE_CALLBACK',
    amount               INT UNSIGNED    NOT NULL COMMENT 'KES',
    phone_hash           CHAR(64)        NOT NULL COMMENT 'HMAC-SHA256 of the normalised phone',
    phone_last3          CHAR(3)         NOT NULL,
    merchant_request_id  VARCHAR(64)     NULL,
    checkout_request_id  VARCHAR(64)     NULL,
    mpesa_receipt        VARCHAR(32)     NULL,
    result_code          INT             NULL,
    result_desc          VARCHAR(255)    NULL,
    source               VARCHAR(16)     NOT NULL COMMENT 'app | callback | reconcile',
    dedupe_key           VARCHAR(96)     NULL COMMENT 'Set on final events so a tip can only settle once',
    raw_payload          TEXT            NULL COMMENT 'Raw JSON from Daraja',
    created_at           DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    UNIQUE KEY uq_tip_ledger_dedupe (dedupe_key),
    KEY idx_tip_ledger_tip (tip_id, id),
    KEY idx_tip_ledger_checkout (checkout_request_id),
    KEY idx_tip_ledger_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS tip_ledger_no_update;
CREATE TRIGGER tip_ledger_no_update BEFORE UPDATE ON tip_ledger
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tip_ledger is append-only: UPDATE is not allowed';

DROP TRIGGER IF EXISTS tip_ledger_no_delete;
CREATE TRIGGER tip_ledger_no_delete BEFORE DELETE ON tip_ledger
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tip_ledger is append-only: DELETE is not allowed';

-- ---------------------------------------------------------------------------
-- Contact form messages. Saved before any email is attempted, so nothing is
-- lost if SMTP is down.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120)    NOT NULL,
    email       VARCHAR(190)    NOT NULL,
    subject     VARCHAR(190)    NOT NULL DEFAULT '',
    body        TEXT            NOT NULL,
    project     VARCHAR(64)     NULL,
    ip_hash     CHAR(64)        NOT NULL,
    mail_sent   TINYINT(1)      NOT NULL DEFAULT 0,
    read_at     DATETIME        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- CV download counter.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cv_downloads (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cv_key      VARCHAR(64)     NOT NULL,
    ip_hash     CHAR(64)        NOT NULL,
    user_agent  VARCHAR(255)    NOT NULL DEFAULT '',
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cv_downloads_key (cv_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Fixed-window rate limiting (contact form, tips, admin login).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    bucket        CHAR(64)     NOT NULL,
    window_start  INT UNSIGNED NOT NULL,
    hits          INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (bucket, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
