-- OBRAS360 V2 — 02 INSERT INTO (dados iniciais, seguro para reexecutar)
-- Selecione u784961086_obras e execute após 01_CREATE_TABLE.sql.
-- O suporte inicial é provisionado como INATIVO com senha aleatória não divulgada.
-- Acesse /obras/public/instalar.php e defina sua senha real do suporte.
-- Se a conta suporte já existe, nenhuma senha nem situação será sobrescrita.
SET NAMES utf8mb4;
-- Inicialização idempotente da sequência, sem regredir identificadores já existentes.
INSERT IGNORE INTO sequences (module,last_number) VALUES ('obras',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('ordens',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('fiscalizacao',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('projetos',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('orcamentos',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('materiais',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('patrimonio',0);
INSERT IGNORE INTO sequences (module,last_number) VALUES ('protocolos',0);


INSERT INTO users (username,email,name,role,password_hash,active,must_change_password)
SELECT 'suporte','suporte@obras360.local','Suporte OBRAS360','suporte','$argon2id$v=19$m=65536,t=3,p=2$Z1pWUHpqZmplRUtrdXpEZA$7LMDD12Ktrmjs0nTQDEc980c2F/JFrMieFuNmDwjRT4',0,1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='suporte' OR email='suporte@obras360.local');
