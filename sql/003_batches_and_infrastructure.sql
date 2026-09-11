-- Shamba Track — Phase 2: batches and infrastructure/equipment costs
-- client_uuid on both tables: generated on-device when a farmer creates a
-- record offline, so a retried sync (e.g. app reopened before the first
-- sync confirmed) can't create a duplicate — the API upserts on this key.

SET NAMES utf8mb4;

CREATE TABLE batches (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id        INT UNSIGNED NOT NULL,
    client_uuid    CHAR(36) NOT NULL,
    breed          ENUM('layers','broilers','kienyeji','sasso') NOT NULL,
    quantity       INT UNSIGNED NOT NULL,
    date_acquired  DATE NOT NULL,
    cost_per_bird  DECIMAL(12,2) NOT NULL DEFAULT 0,
    source         VARCHAR(150) NULL,
    notes          TEXT NULL,
    status         ENUM('active','closed') NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_batches_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    UNIQUE KEY uq_batches_client_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_batches_farm ON batches (farm_id, status);

CREATE TABLE infrastructure_items (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id        INT UNSIGNED NOT NULL,
    batch_id       INT UNSIGNED NULL,
    client_uuid    CHAR(36) NOT NULL,
    category       ENUM('coop','land','equipment') NOT NULL,
    item_name      VARCHAR(150) NOT NULL,
    land_status    ENUM('bought','rented') NULL,
    amount         DECIMAL(12,2) NOT NULL,
    date_incurred  DATE NOT NULL,
    notes          TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_infra_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_infra_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    UNIQUE KEY uq_infra_client_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_infra_farm ON infrastructure_items (farm_id, category);
