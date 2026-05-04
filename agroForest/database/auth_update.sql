ALTER TABLE usuarios
  ADD COLUMN ultimo_login DATETIME NULL AFTER ativo;

INSERT INTO usuarios (nome, email, senha, nivel, ativo) VALUES
('Recepção Agro Forest', 'recepcao@agroforest.test', 'pbkdf2_sha256$100000$58956eb178db20badfc23db429605cc4$6be2b831342df6b188c2a8ad601d18c6a0c56c2499a71e7f2bb7bfc619841e19', 'recepcao', 1),
('Administrativo Agro Forest', 'administrativo@agroforest.test', 'pbkdf2_sha256$100000$d23c59529dfa23f756ac61d436b4a740$c514b9459bddd28735141f1f689d76c382f413d8a465668373d1f1e83c4febc2', 'administrativo', 1),
('Dono Agro Forest', 'dono@agroforest.test', 'pbkdf2_sha256$100000$c88d6949ee4c91a8a4a6cd7d7c086c13$9accfad0d725ab7f227cabba34f79ae6507b93ffa041bf35f046aa96c3bb3771', 'dono', 1)
ON DUPLICATE KEY UPDATE
  nome = VALUES(nome),
  senha = VALUES(senha),
  nivel = VALUES(nivel),
  ativo = VALUES(ativo);
