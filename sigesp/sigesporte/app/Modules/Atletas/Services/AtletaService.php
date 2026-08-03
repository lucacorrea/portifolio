<?php
declare(strict_types=1);
namespace Sigesp\Modules\Atletas\Services;
use Sigesp\Core\{Database,Validator};
use Sigesp\Modules\Atletas\Repositories\AtletaRepository;
final class AtletaService
{
    public function create(array $input): int
    {
        $cpf = preg_replace('/\D/', '', (string) ($input['cpf'] ?? '')) ?? ''; if (!Validator::cpf($cpf)) throw new \InvalidArgumentException('Informe um CPF válido.');
        if (!Validator::email((string) ($input['email'] ?? ''))) throw new \InvalidArgumentException('Informe um e-mail válido.');
        $birth = new \DateTimeImmutable((string) ($input['nascimento'] ?? '')); if ($birth > new \DateTimeImmutable('today')) throw new \InvalidArgumentException('A data de nascimento não pode estar no futuro.');
        $minor = $birth->diff(new \DateTimeImmutable('today'))->y < 18; if ($minor && trim((string) ($input['responsavel_nome'] ?? '')) === '') throw new \InvalidArgumentException('Responsável é obrigatório para atleta menor de idade.');
        $db = Database::connection(); $db->beginTransaction(); try { $id = (new AtletaRepository())->create(['codigo' => 'ATL-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8)), 'nome' => trim((string) $input['nome']), 'nome_social' => trim((string) ($input['nome_social'] ?? '')) ?: null, 'cpf' => $cpf, 'nascimento' => $birth->format('Y-m-d'), 'sexo' => $input['sexo'] ?? 'nao_informado', 'telefone' => preg_replace('/\D/', '', (string) ($input['telefone'] ?? '')), 'email' => mb_strtolower(trim((string) $input['email'])), 'status' => $input['acao'] === 'rascunho' ? 'rascunho' : 'ativo']); if ($minor) { $q=$db->prepare('INSERT INTO responsaveis (nome,cpf,telefone,email,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())'); $q->execute([trim((string) $input['responsavel_nome']), preg_replace('/\D/', '', (string) ($input['responsavel_cpf'] ?? '')), preg_replace('/\D/', '', (string) ($input['responsavel_telefone'] ?? '')), mb_strtolower(trim((string) ($input['responsavel_email'] ?? '')))]); $db->prepare('INSERT INTO atletas_responsaveis (atleta_id,responsavel_id,parentesco,principal) VALUES (?,?,?,1)')->execute([$id, $db->lastInsertId(), $input['parentesco'] ?? 'Responsável']); } $db->commit(); return $id; } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
