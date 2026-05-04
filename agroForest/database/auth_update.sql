ALTER TABLE usuarios
  ADD COLUMN ultimo_login DATETIME NULL AFTER ativo;

INSERT INTO usuarios (nome, email, senha, nivel, ativo) VALUES
('Recepção Agro Forest', 'recepcao@agroforest.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'recepcao', 1),
('Administrativo Agro Forest', 'administrativo@agroforest.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'administrativo', 1),
('Dono Agro Forest', 'dono@agroforest.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi', 'dono', 1)
ON DUPLICATE KEY UPDATE
  nome = VALUES(nome),
  senha = VALUES(senha),
  nivel = VALUES(nivel),
  ativo = VALUES(ativo);
