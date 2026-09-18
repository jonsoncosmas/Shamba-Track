-- Shamba Track — Phase 5: sales & revenue
-- Same offline-sync pattern: client_uuid idempotency, batch_client_uuid
-- reference (nullable — manure/other sales aren't always batch-specific).

SET NAMES utf8mb4;

CREATE TABLE sales (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id           INT UNSIGNED NOT NULL,
    batch_client_uuid CHAR(36) NULL,
    client_uuid       CHAR(36) NOT NULL,
    sale_type         ENUM('eggs','birds','manure','other') NOT NULL,
    quantity          DECIMAL(10,2) NOT NULL,
    unit              VARCHAR(30) NULL,
    weight_kg         DECIMAL(10,2) NULL,
    unit_price        DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount      DECIMAL(12,2) NOT NULL,
    buyer             VARCHAR(150) NULL,
    date_sold         DATE NOT NULL,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_batch FOREIGN KEY (batch_client_uuid) REFERENCES batches(client_uuid) ON DELETE SET NULL,
    UNIQUE KEY uq_sales_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_sales_farm ON sales (farm_id, sale_type, date_sold);
