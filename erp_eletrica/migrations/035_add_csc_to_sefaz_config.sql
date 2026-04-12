-- Migration 035: Add missing csc_id and csc columns to sefaz_config global table
ALTER TABLE sefaz_config ADD COLUMN IF NOT EXISTS csc_id VARCHAR(10) NULL;
ALTER TABLE sefaz_config ADD COLUMN IF NOT EXISTS csc VARCHAR(100) NULL;
