-- Migration versionada para pasta database
-- Origem: database/migrations/2026-04-13_add_allow_fuel_to_users.sql

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS allow_fuel TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;