-- Shamba Track — Phase 3: daily operational logging
-- Same offline-sync pattern as Phase 2: client_uuid generated on-device,
-- API upserts on it so a retried sync can't create duplicates.
--
-- Batch references use batch_client_uuid (-> batches.client_uuid), NOT the
-- server-assigned integer id. A device always knows a batch's client_uuid
-- immediately (it generated it), but can't know the server's auto-increment
-- id until that batch has synced — which may not have happened yet when a
-- feed/egg/mortality entry for it is logged offline. Referencing the UUID
-- means child records can be created correctly regardless of sync order.

SET NAMES utf8mb4;

CREATE TABLE feed_purchases (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id           INT UNSIGNED NOT NULL,
    batch_client_uuid CHAR(36) NULL,
    client_uuid       CHAR(36) NOT NULL,
    source            ENUM('bought','home_made') NOT NULL DEFAULT 'bought',
    feed_type         ENUM('starter','grower','layers_mash','other') NOT NULL,
    quantity_kg       DECIMAL(10,2) NOT NULL,
    total_cost        DECIMAL(12,2) NOT NULL,
    date_purchased    DATE NOT NULL,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedpurch_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedpurch_batch FOREIGN KEY (batch_client_uuid) REFERENCES batches(client_uuid) ON DELETE SET NULL,
    UNIQUE KEY uq_feedpurch_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_feedpurch_farm ON feed_purchases (farm_id, date_purchased);

CREATE TABLE feed_consumption (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id           INT UNSIGNED NOT NULL,
    batch_client_uuid CHAR(36) NOT NULL,
    client_uuid       CHAR(36) NOT NULL,
    quantity_kg       DECIMAL(10,2) NOT NULL,
    date_consumed     DATE NOT NULL,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedcons_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedcons_batch FOREIGN KEY (batch_client_uuid) REFERENCES batches(client_uuid) ON DELETE CASCADE,
    UNIQUE KEY uq_feedcons_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_feedcons_batch ON feed_consumption (batch_client_uuid, date_consumed);

CREATE TABLE egg_logs (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id           INT UNSIGNED NOT NULL,
    batch_client_uuid CHAR(36) NOT NULL,
    client_uuid       CHAR(36) NOT NULL,
    date_collected    DATE NOT NULL,
    quantity_whole    INT UNSIGNED NOT NULL DEFAULT 0,
    quantity_broken   INT UNSIGNED NOT NULL DEFAULT 0,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_egglogs_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_egglogs_batch FOREIGN KEY (batch_client_uuid) REFERENCES batches(client_uuid) ON DELETE CASCADE,
    UNIQUE KEY uq_egglogs_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_egglogs_batch ON egg_logs (batch_client_uuid, date_collected);

CREATE TABLE mortality_logs (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id           INT UNSIGNED NOT NULL,
    batch_client_uuid CHAR(36) NOT NULL,
    client_uuid       CHAR(36) NOT NULL,
    date_occurred     DATE NOT NULL,
    quantity          INT UNSIGNED NOT NULL,
    cause             VARCHAR(150) NULL,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mortality_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_mortality_batch FOREIGN KEY (batch_client_uuid) REFERENCES batches(client_uuid) ON DELETE CASCADE,
    UNIQUE KEY uq_mortality_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_mortality_batch ON mortality_logs (batch_client_uuid, date_occurred);

-- Generic cost entry: covers labor, utilities, medication, transport — all
-- structurally identical (a label, an amount, a date), so one table avoids
-- four near-duplicate tables. sub_type carries category-specific meaning:
-- labor -> 'allowance'|'salary', others -> unused/null.
CREATE TABLE cost_entries (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id           INT UNSIGNED NOT NULL,
    batch_client_uuid CHAR(36) NULL,
    client_uuid       CHAR(36) NOT NULL,
    category          ENUM('labor','utilities','medication','transport') NOT NULL,
    sub_type          VARCHAR(50) NULL,
    label             VARCHAR(150) NOT NULL,
    amount            DECIMAL(12,2) NOT NULL,
    date_incurred     DATE NOT NULL,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_costentries_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_costentries_batch FOREIGN KEY (batch_client_uuid) REFERENCES batches(client_uuid) ON DELETE SET NULL,
    UNIQUE KEY uq_costentries_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_costentries_farm ON cost_entries (farm_id, category, date_incurred);
