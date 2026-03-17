-- Add authorization fields for discount release
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS auth_pin VARCHAR(255) DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS auth_type ENUM('password', 'pin') DEFAULT 'password';
