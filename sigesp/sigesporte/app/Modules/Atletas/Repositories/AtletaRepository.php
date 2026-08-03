<?php
declare(strict_types=1);
namespace Sigesp\Modules\Atletas\Repositories;
use Sigesp\Core\Database;
final class AtletaRepository
{
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['a.deleted_at IS NULL']; $params = [];
        if ($search = trim((string) ($filters['q'] ?? ''))) { $where[] = '(a.nome LIKE :q OR a.cpf LIKE :q OR a.codigo LIKE :q)'; $params['q'] = "%$search%"; }
        if (in_array($filters['status'] ?? '', ['ativo','inativo','rascunho'], true)) { $where[] = 'a.status=:status'; $params['status'] = $filters['status']; }
        $sqlWhere = implode(' AND ', $where); $db = Database::connection(); $count = $db->prepare("SELECT COUNT(*) FROM atletas a WHERE $sqlWhere"); $count->execute($params); $total = (int) $count->fetchColumn(); $page = min(max(1, $page), max(1, (int) ceil($total / $perPage)));
        $query = $db->prepare("SELECT a.*, m.nome modalidade FROM atletas a LEFT JOIN atletas_perfis_esportivos ape ON ape.atleta_id=a.id AND ape.encerrado_em IS NULL LEFT JOIN modalidades m ON m.id=ape.modalidade_principal_id WHERE $sqlWhere ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset"); foreach ($params as $key => $value) $query->bindValue(":$key", $value); $query->bindValue(':limit', $perPage, \PDO::PARAM_INT); $query->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT); $query->execute(); return ['items' => $query->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }
    public function find(int $id): ?array { $q = Database::connection()->prepare('SELECT a.*,e.cep,e.municipio,e.bairro,e.logradouro,e.numero FROM atletas a LEFT JOIN atletas_enderecos e ON e.atleta_id=a.id WHERE a.id=? AND a.deleted_at IS NULL'); $q->execute([$id]); return $q->fetch() ?: null; }
    public function create(array $data): int { $q = Database::connection()->prepare('INSERT INTO atletas (codigo,nome,nome_social,cpf,nascimento,sexo,telefone,email,status,created_at,updated_at) VALUES (:codigo,:nome,:nome_social,:cpf,:nascimento,:sexo,:telefone,:email,:status,NOW(),NOW())'); $q->execute($data); return (int) Database::connection()->lastInsertId(); }
}
