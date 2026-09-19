-- Shamba Track — Phase 7: capital sources (funds net profit calculation)
-- Farm-wide by default (batch_client_uuid nullable) since capital usually
-- funds the whole operation, not one specific batch, though a farmer can
-- link a specific injection to a batch if they want that level of detail.
--
-- interest_rate is a flat percentage (not amortized/compounding) — keeps
-- this a deterministic, hand-verifiable number rather than a loan
-- amortization schedule, matching the "free, simple, offline" v1 approach.

SET NAMES utf8mb4;

CREATE TABLE capital_sources (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id           INT UNSIGNED NOT NULL,
    batch_client_uuid CHAR(36) NULL,
    client_uuid       CHAR(36) NOT NULL,
    source_type       ENUM('loan','salary','freelance','savings','other') NOT NULL,
    amount            DECIMAL(12,2) NOT NULL,
    interest_rate     DECIMAL(5,2) NULL,
    date_received     DATE NOT NULL,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_capital_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_capital_batch FOREIGN KEY (batch_client_uuid) REFERENCES batches(client_uuid) ON DELETE SET NULL,
    UNIQUE KEY uq_capital_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_capital_farm ON capital_sources (farm_id);
