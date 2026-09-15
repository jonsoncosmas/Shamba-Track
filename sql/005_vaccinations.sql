-- Shamba Track — Phase 4: vaccination schedule & reminders
--
-- The schedule TEMPLATE (which vaccines, at what age, per breed) lives in
-- JS (assets/js/vaccinations.js), not the database — it's static reference
-- data that must be available offline from first load, and the app already
-- caches all JS via the service worker. This table stores only the
-- per-batch INSTANTIATED records the client generates the moment a batch
-- is saved (online or off).
--
-- Unlike every other Phase 2/3 table, records here can be legitimately
-- mutated after their first sync (status: pending -> done). See
-- VaccinationRecord::upsert() — it updates status/completed_date/notes on
-- an existing client_uuid instead of only ever inserting-if-new.

SET NAMES utf8mb4;

CREATE TABLE vaccination_records (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farm_id           INT UNSIGNED NOT NULL,
    batch_client_uuid CHAR(36) NOT NULL,
    client_uuid       CHAR(36) NOT NULL,
    disease           VARCHAR(100) NOT NULL,
    vaccine_name      VARCHAR(100) NOT NULL,
    age_days          INT UNSIGNED NOT NULL,
    due_date          DATE NOT NULL,
    status            ENUM('pending','done') NOT NULL DEFAULT 'pending',
    completed_date    DATE NULL,
    notes             TEXT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vaccination_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE,
    CONSTRAINT fk_vaccination_batch FOREIGN KEY (batch_client_uuid) REFERENCES batches(client_uuid) ON DELETE CASCADE,
    UNIQUE KEY uq_vaccination_uuid (client_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_vaccination_batch ON vaccination_records (batch_client_uuid, due_date);
CREATE INDEX idx_vaccination_farm_status ON vaccination_records (farm_id, status, due_date);
