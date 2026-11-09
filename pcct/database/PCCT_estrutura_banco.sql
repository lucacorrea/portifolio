-- ============================================================================
-- PCCT | ESTRUTURA INICIAL DO BANCO DE DADOS
-- Compativel com MariaDB 11.x (Hostinger) e Laravel 9
-- Baseado no diagrama REPOA-IFAM e na pasta pcct do repositorio portifolio.
-- ============================================================================
-- IMPORTANTE:
-- 1. No phpMyAdmin, selecione ANTES o banco de dados destinado ao PCCT.
-- 2. Faca backup antes de importar se o banco ja tiver dados.
-- 3. Este arquivo NAO cria nem seleciona banco, NAO apaga tabelas ou registros.
-- 4. A tabela users e a tabela objetos_aprendizagem preservam os campos
--    existentes no codigo Laravel; as colunas de relacionamento sao adicionadas.
-- 5. Login/senhas ficam SOMENTE em users; salve senhas com Hash::make no Laravel.
-- 6. O codigo PHP/Blade precisara ser adaptado para consumir as novas relacoes.
-- ============================================================================

SET NAMES utf8mb4;

-- 01. Usuarios / autenticacao do Laravel
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `role` ENUM('admin','professor','estudante') NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Para bancos em que a migration original ja criou users sem role.
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `role` ENUM('admin','professor','estudante') NULL DEFAULT NULL;

-- 02. Instituicoes
CREATE TABLE IF NOT EXISTS `instituicoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `descricao` TEXT NULL,
  `localidade` VARCHAR(255) NULL,
  `url` VARCHAR(2048) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_instituicoes_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 03. Administradores (perfil 1:1 com users)
CREATE TABLE IF NOT EXISTS `administradores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_administradores_user` (`user_id`),
  CONSTRAINT `fk_pcct_admin_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 04. Professores (1 instituicao -> N professores)
CREATE TABLE IF NOT EXISTS `professores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `instituicao_id` BIGINT UNSIGNED NULL,
  `formacao` VARCHAR(150) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_professores_user` (`user_id`),
  KEY `idx_professores_instituicao` (`instituicao_id`),
  CONSTRAINT `fk_pcct_prof_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_pcct_prof_inst` FOREIGN KEY (`instituicao_id`)
    REFERENCES `instituicoes` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 05. Estudantes (1 instituicao -> N estudantes)
CREATE TABLE IF NOT EXISTS `estudantes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `instituicao_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_estudantes_user` (`user_id`),
  KEY `idx_estudantes_instituicao` (`instituicao_id`),
  CONSTRAINT `fk_pcct_est_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_pcct_est_inst` FOREIGN KEY (`instituicao_id`)
    REFERENCES `instituicoes` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 06. Disciplinas
-- Os campos created_at e updated_at ja representam as datas do diagrama;
-- nao criamos outras duas datas redundantes.
CREATE TABLE IF NOT EXISTS `disciplinas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `descricao` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_disciplinas_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 07. Habilidades BNCC (N habilidades -> 1 disciplina)
CREATE TABLE IF NOT EXISTS `habilidades_bncc` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(40) NOT NULL,
  `nome` VARCHAR(255) NOT NULL,
  `descricao` TEXT NULL,
  `disciplina_id` BIGINT UNSIGNED NULL,
  `ano_escolar` VARCHAR(80) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_habilidades_codigo` (`codigo`),
  KEY `idx_habilidades_disciplina` (`disciplina_id`),
  CONSTRAINT `fk_pcct_hab_disc` FOREIGN KEY (`disciplina_id`)
    REFERENCES `disciplinas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 08. Objetos de aprendizagem
-- Campos originais mantidos: curso, tematica, nome_disciplina, titulo,
-- descricao, bncc, tipo_objeto, imagem, tags, link.
CREATE TABLE IF NOT EXISTS `objetos_aprendizagem` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `curso` VARCHAR(255) NOT NULL,
  `tematica` VARCHAR(255) NOT NULL,
  `nome_disciplina` VARCHAR(255) NOT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `descricao` TEXT NOT NULL,
  `bncc` VARCHAR(255) NULL,
  `tipo_objeto` VARCHAR(255) NOT NULL,
  `imagem` VARCHAR(255) NULL,
  `tags` VARCHAR(255) NULL,
  `link` VARCHAR(255) NOT NULL,
  `professor_id` BIGINT UNSIGNED NULL,
  `disciplina_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oa_professor` (`professor_id`),
  KEY `idx_oa_disciplina` (`disciplina_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adiciona somente colunas ausentes caso a migration ja tenha criado esta tabela.
ALTER TABLE `objetos_aprendizagem`
  ADD COLUMN IF NOT EXISTS `professor_id` BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS `disciplina_id` BIGINT UNSIGNED NULL;

-- Adiciona chaves estrangeiras apenas se ainda nao existirem (reimportacao).
SET @pcct_sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'objetos_aprendizagem'
     AND CONSTRAINT_NAME = 'fk_pcct_oa_prof') = 0,
  'ALTER TABLE `objetos_aprendizagem` ADD CONSTRAINT `fk_pcct_oa_prof` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE pcct_stmt FROM @pcct_sql;
EXECUTE pcct_stmt;
DEALLOCATE PREPARE pcct_stmt;

SET @pcct_sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'objetos_aprendizagem'
     AND CONSTRAINT_NAME = 'fk_pcct_oa_disc') = 0,
  'ALTER TABLE `objetos_aprendizagem` ADD CONSTRAINT `fk_pcct_oa_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE pcct_stmt FROM @pcct_sql;
EXECUTE pcct_stmt;
DEALLOCATE PREPARE pcct_stmt;

-- 09. Relacao N:N entre objetos de aprendizagem e habilidades da BNCC.
CREATE TABLE IF NOT EXISTS `objeto_habilidade` (
  `objeto_id` BIGINT UNSIGNED NOT NULL,
  `habilidade_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`objeto_id`, `habilidade_id`),
  KEY `idx_obj_hab_habilidade` (`habilidade_id`),
  CONSTRAINT `fk_pcct_objhab_obj` FOREIGN KEY (`objeto_id`)
    REFERENCES `objetos_aprendizagem` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_pcct_objhab_hab` FOREIGN KEY (`habilidade_id`)
    REFERENCES `habilidades_bncc` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelas de infraestrutura previstas nas migrations Laravel 9 do repositorio.
CREATE TABLE IF NOT EXISTS `password_resets` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registra SOMENTE as cinco migrations existentes no repositorio que este
-- arquivo recriou para evitar que php artisan migrate tente recriar tabelas.
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_migrations_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2014_10_12_000000_create_users_table', 1
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2014_10_12_000000_create_users_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2014_10_12_100000_create_password_resets_table', 1
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2014_10_12_100000_create_password_resets_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2019_08_19_000000_create_failed_jobs_table', 1
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2019_08_19_000000_create_failed_jobs_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2019_12_14_000001_create_personal_access_tokens_table', 1
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2019_12_14_000001_create_personal_access_tokens_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_09_11_034901_create_objetos_aprendizagem_table', 1
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_09_11_034901_create_objetos_aprendizagem_table');

-- Verificacao rapida (deve apresentar as tabelas criadas e preservadas):
SHOW TABLES;
