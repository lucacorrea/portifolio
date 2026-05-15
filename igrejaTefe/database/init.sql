-- Igreja Tefe Financeiro - database bootstrap
-- MySQL 8+
--
-- Usage:
--   mysql -u root -p < database/init.sql
--
-- This script creates the database when it does not exist, creates the
-- MVP schema, and optionally seeds default categories for igreja id 1.

CREATE DATABASE IF NOT EXISTS igreja_tefe
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE igreja_tefe;

SOURCE database/migrations/001_create_core_schema.sql
SOURCE database/seeds/001_categorias_padrao.sql
