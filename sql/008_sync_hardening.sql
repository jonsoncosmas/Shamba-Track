-- Shamba Track — Phase 8: sync hardening (conflict detection)
--
-- vaccination_records is currently the ONLY record type that can be
-- legitimately mutated after its first sync (status: pending -> done).
-- Every other table so far is create-once, so there is nothing to
-- conflict on there yet — this column exists specifically because
-- mutation exists here and needs protecting.
--
-- Optimistic concurrency: the client always sends the version it last
-- knew about alongside an update. If it no longer matches the row's
-- current version, someone else's update already landed first — the
-- server rejects (409) instead of silently overwriting, and the client
-- surfaces it as a conflict for the farmer to resolve.

ALTER TABLE vaccination_records ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER notes;
