-- wizdam-apis DB schema contribution
-- Target database: wizdam_ecosystem (unified across all Wizdam repos)
-- Layer: 5 — Platform Infrastructure
--
-- NOTE: Merge these tables into the canonical schema in sdgs-mapper/db/schema.sql
--       before running in production. This file is the authoritative source for
--       tables owned by wizdam-apis.

-- ── api_keys (revocation table) ───────────────────────────────────────────────
-- Stores sha256(full_key) for revoked keys only.
-- Key validation is stateless HMAC — this table is consulted only to check
-- if a valid HMAC key has been explicitly revoked.
CREATE TABLE IF NOT EXISTS api_keys (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_hash     CHAR(64)      NOT NULL,           -- sha256(full_api_key)
    user_id      VARCHAR(100)  NOT NULL,            -- extracted from key: wz_{user_id}_...
    issued_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at   TIMESTAMP     NULL,
    revoked_by   VARCHAR(100)  NULL,               -- 'user', 'admin', or 'system'
    UNIQUE KEY uq_key_hash (key_hash),
    INDEX idx_user_id (user_id),
    INDEX idx_revoked_at (revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PostgreSQL equivalent:
-- CREATE TABLE IF NOT EXISTS api_keys (
--     id           BIGSERIAL PRIMARY KEY,
--     key_hash     CHAR(64)     NOT NULL UNIQUE,
--     user_id      VARCHAR(100) NOT NULL,
--     issued_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
--     revoked_at   TIMESTAMPTZ  NULL,
--     revoked_by   VARCHAR(100) NULL
-- );

-- ── api_rate_limits ───────────────────────────────────────────────────────────
-- Fixed-window rate limit counters. One row per (user_id, window_start).
-- window_start is the Unix timestamp of the window's beginning (floor(now/window)*window).
-- Rows older than two windows can be safely deleted.
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      VARCHAR(100)   NOT NULL,
    window_start INT UNSIGNED   NOT NULL,           -- Unix timestamp
    hit_count    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_window (user_id, window_start),
    INDEX idx_user_id (user_id),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PostgreSQL equivalent:
-- CREATE TABLE IF NOT EXISTS api_rate_limits (
--     id           BIGSERIAL PRIMARY KEY,
--     user_id      VARCHAR(100) NOT NULL,
--     window_start INT          NOT NULL,
--     hit_count    SMALLINT     NOT NULL DEFAULT 1,
--     updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
--     UNIQUE (user_id, window_start)
-- );

-- ── jobs ──────────────────────────────────────────────────────────────────────
-- Background job queue for async operations (e.g. bulk SDG classification,
-- large ORCID harvests). Workers dequeue by polling for status='pending'.
CREATE TABLE IF NOT EXISTS jobs (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue        VARCHAR(100)   NOT NULL DEFAULT 'default',
    payload      LONGTEXT       NOT NULL,           -- JSON job payload
    status       ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reserved_at  TIMESTAMP      NULL,
    available_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_queue_status (queue, status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
