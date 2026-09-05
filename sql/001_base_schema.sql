-- Shamba Track — Phase 0 Base Schema
-- Multi-tenant ready: every farm-owned table below (and every table added
-- in later phases) carries a farm_id, even though v1 UI only supports a
-- single farmer managing one farm at a time.

SET NAMES utf8mb4;
SET time_zone = '+03:00';

-- ---------------------------------------------------------------------
-- currencies — reference table, seeded once, searchable in the UI
-- ---------------------------------------------------------------------
CREATE TABLE currencies (
    code        CHAR(3) PRIMARY KEY,          -- ISO 4217, e.g. 'TZS', 'USD', 'KES'
    name        VARCHAR(100) NOT NULL,        -- e.g. 'Tanzanian Shilling'
    symbol      VARCHAR(10)  NOT NULL,        -- e.g. 'TSh', '$'
    country     VARCHAR(100) NOT NULL,        -- e.g. 'Tanzania'
    is_active   TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_currencies_name ON currencies (name);
CREATE INDEX idx_currencies_country ON currencies (country);

-- ---------------------------------------------------------------------
-- users — the farmer (or, later, a co-op admin/extension officer)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number        VARCHAR(20)  NOT NULL UNIQUE,   -- E.164 format, e.g. +255XXXXXXXXX
    full_name           VARCHAR(150) NULL,
    preferred_language  ENUM('en','sw') NOT NULL DEFAULT 'sw',
    role                ENUM('farmer','admin','extension_officer') NOT NULL DEFAULT 'farmer',
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- farms — the tenant boundary. Every operational table references this.
-- ---------------------------------------------------------------------
CREATE TABLE farms (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id   INT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    region          VARCHAR(100) NULL,          -- e.g. 'Dar es Salaam'
    district        VARCHAR(100) NULL,
    village         VARCHAR(100) NULL,
    currency_code   CHAR(3) NOT NULL DEFAULT 'TZS',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_farms_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_farms_currency FOREIGN KEY (currency_code) REFERENCES currencies(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_farms_owner ON farms (owner_user_id);

-- ---------------------------------------------------------------------
-- farm_users — join table for future multi-tenant (co-op / extension
-- officer overseeing many farms, or multiple users per farm)
-- ---------------------------------------------------------------------
CREATE TABLE farm_users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    role        ENUM('owner','manager','viewer') NOT NULL DEFAULT 'owner',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_farmusers_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_farmusers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_farm_user (farm_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- otp_codes — short-lived codes for phone login
-- ---------------------------------------------------------------------
CREATE TABLE otp_codes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number    VARCHAR(20) NOT NULL,
    code_hash       VARCHAR(255) NOT NULL,   -- hashed, never store the raw code
    expires_at      DATETIME NOT NULL,
    consumed_at     DATETIME NULL,
    attempt_count   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_otp_phone ON otp_codes (phone_number, expires_at);

-- ---------------------------------------------------------------------
-- auth_sessions — long-lived device tokens for the "no re-login" flow
-- ---------------------------------------------------------------------
CREATE TABLE auth_sessions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    token_hash      VARCHAR(255) NOT NULL,   -- hashed device token, never store raw
    device_label    VARCHAR(150) NULL,       -- optional, e.g. "Samsung A14"
    last_used_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at      DATETIME NULL,

    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_sessions_user ON auth_sessions (user_id);
CREATE INDEX idx_sessions_token ON auth_sessions (token_hash);

-- ---------------------------------------------------------------------
-- sync_log — every offline-origin write passes through here so Phase 8
-- (sync hardening) has an audit trail and conflict-detection base to build on
-- ---------------------------------------------------------------------
CREATE TABLE sync_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id         INT UNSIGNED NOT NULL,
    table_name      VARCHAR(100) NOT NULL,
    record_uuid     CHAR(36) NOT NULL,        -- client-generated UUID, stable across offline/online
    action          ENUM('insert','update','delete') NOT NULL,
    payload_json    JSON NOT NULL,
    client_created_at DATETIME NOT NULL,      -- timestamp from the device, may predate sync time
    synced_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('applied','conflict','error') NOT NULL DEFAULT 'applied',

    CONSTRAINT fk_synclog_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_synclog_farm ON sync_log (farm_id, table_name);
CREATE INDEX idx_synclog_uuid ON sync_log (record_uuid);
