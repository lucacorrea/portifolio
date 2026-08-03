<?php
declare(strict_types=1);
namespace Sigesp\Modules\Auth\Repositories;
use Sigesp\Core\Database;
final class UsuarioRepository
{
    public function findByIdentifier(string $identifier): ?array { $q = Database::connection()->prepare('SELECT * FROM usuarios WHERE (email = :identifier OR cpf = :cpf) AND ativo = 1 AND deleted_at IS NULL LIMIT 1'); $q->execute(['identifier' => strtolower($identifier), 'cpf' => preg_replace('/\D/', '', $identifier)]); return $q->fetch() ?: null; }
    public function hasPermission(int $userId, string $permission): bool { $q = Database::connection()->prepare('SELECT 1 FROM usuarios_perfis up JOIN perfis_permissoes pp ON pp.perfil_id=up.perfil_id JOIN permissoes p ON p.id=pp.permissao_id WHERE up.usuario_id=? AND p.chave=? LIMIT 1'); $q->execute([$userId, $permission]); return (bool) $q->fetchColumn(); }
    public function createResetToken(int $userId, string $token, string $ip): void { $q = Database::connection()->prepare('INSERT INTO tokens_senha (usuario_id,token_hash,expira_em,solicitado_ip) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE),?)'); $q->execute([$userId, hash('sha256', $token), $ip]); }
}
