<?php

declare(strict_types=1);

namespace App\Integration\SO\Service;

use App\Integration\SO\Repository\SoAcquisitionReadRepository;
use InvalidArgumentException;
use PDO;

final class SoAcquisitionBrowserService
{
    public function __construct(
        private readonly PDO $localConnection,
        private readonly SoAcquisitionReadRepository $acquisitions
    ) {}

    /** @return array{company:array<string,mixed>,supplier:array<string,mixed>,result:array<string,mixed>} */
    public function browse(int $companyId, array $filters, int $page, int $perPage): array
    {
        $context = $this->context($companyId);
        $result = $this->acquisitions->paginate((int) $context['supplier_id'], $this->validateFilters($filters), $page, $perPage);
        $ids = array_map(static fn(array $row): int => (int) $row['id'], $result['items']);
        $integrations = $this->localIntegrations($companyId, $ids);
        foreach ($result['items'] as &$row) $row['integration'] = $integrations[(int) $row['id']] ?? null;
        unset($row);
        return ['company' => $context['company'], 'supplier' => $context['supplier'], 'result' => $result];
    }

    /** @return array{company:array<string,mixed>,supplier:array<string,mixed>,acquisition:array<string,mixed>,items:array<int,array<string,mixed>>}|null */
    public function details(int $companyId, int $acquisitionId): ?array
    {
        $context = $this->context($companyId);
        $acquisition = $this->acquisitions->findById((int) $context['supplier_id'], $acquisitionId);
        if ($acquisition === null) return null;
        return ['company' => $context['company'], 'supplier' => $context['supplier'], 'acquisition' => $acquisition, 'items' => $this->acquisitions->findItems((int) $context['supplier_id'], $acquisitionId)];
    }

    /** @return array{company:array<string,mixed>,supplier:array<string,mixed>,supplier_id:int} */
    private function context(int $companyId): array
    {
        if ($companyId <= 0) throw new InvalidArgumentException('Empresa inválida.');
        $company = $this->localConnection->prepare('SELECT id, razao_social, nome_fantasia, documento, segmento, status FROM empresas WHERE id = :empresa_id LIMIT 1');
        $company->execute(['empresa_id' => $companyId]); $row = $company->fetch();
        if (!is_array($row) || ($row['status'] ?? '') === 'bloqueado') throw new InvalidArgumentException('Empresa não disponível para consulta.');
        $link = $this->localConnection->prepare("SELECT identificador_externo FROM empresa_integracoes WHERE empresa_id = :empresa_id AND sistema = 'SO' AND entidade = 'fornecedor' AND identificador_externo REGEXP '^[1-9][0-9]*$' LIMIT 2");
        $link->execute(['empresa_id' => $companyId]); $links = $link->fetchAll();
        if (count($links) !== 1) throw new InvalidArgumentException('A empresa não possui vínculo válido com fornecedor do SO.');
        $supplierId = (int) $links[0]['identificador_externo'];
        $supplier = $this->acquisitions->findSupplier($supplierId);
        if ($supplier === null) throw new InvalidArgumentException('Fornecedor vinculado não foi localizado no SO.');
        return ['company' => $row, 'supplier' => $supplier, 'supplier_id' => $supplierId];
    }

    /** @return array<int,array<string,mixed>> */
    private function localIntegrations(int $companyId, array $ids): array
    {
        if ($ids === []) return [];
        $params = ['empresa_id' => $companyId]; $marks = [];
        foreach ($ids as $index => $id) { $key = 'id' . $index; $marks[] = ':' . $key; $params[$key] = $id; }
        $statement = $this->localConnection->prepare('SELECT empresa_id, ordem_servico_id, orcamento_id, aquisicao_so_id, numero_aquisicao_so, direcao, origem, status_integracao, status_so, criado_em, sincronizado_em FROM integracao_so_aquisicoes WHERE empresa_id = :empresa_id AND aquisicao_so_id IN (' . implode(',', $marks) . ')');
        $statement->execute($params); $result = [];
        foreach ($statement->fetchAll() as $row) $result[(int) $row['aquisicao_so_id']] = $row;
        return $result;
    }

    private function validateFilters(array $filters): array
    {
        foreach (['date_from', 'date_to'] as $key) if (($filters[$key] ?? '') !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', (string) $filters[$key])) throw new InvalidArgumentException('Período informado é inválido.');
        if (($filters['date_from'] ?? '') !== '' && ($filters['date_to'] ?? '') !== '' && $filters['date_from'] > $filters['date_to']) throw new InvalidArgumentException('O período informado é inválido.');
        return $filters;
    }
}
