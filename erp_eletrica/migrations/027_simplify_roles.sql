-- Migration 027: Simplify Roles to Admin, Gerente, Vendedor
-- Removes 'master' and 'tecnico' roles

-- 1. Reassign existing 'master' users to 'admin'
UPDATE usuarios SET nivel = 'admin' WHERE nivel = 'master';

-- 2. Reassign existing 'tecnico' users to 'vendedor'
UPDATE usuarios SET nivel = 'vendedor' WHERE nivel = 'tecnico';

-- 3. In the permissions bridge table, we should reassign or delete the obsolete roles.
-- Let's just delete them and let admins handle custom adjustments if they had any.
-- The default `admin` role will have all permissions. 
DELETE FROM permissao_nivel WHERE nivel IN ('master', 'tecnico');

-- 4. Update the ENUM definition in the `usuarios` table
ALTER TABLE usuarios MODIFY COLUMN nivel ENUM('vendedor', 'gerente', 'admin') DEFAULT 'vendedor';

-- 5. Update the ENUM definition in the `permissao_nivel` table
ALTER TABLE permissao_nivel MODIFY COLUMN nivel ENUM('vendedor', 'gerente', 'admin') NOT NULL;
