<?php

declare(strict_types=1);

namespace App\Fiscal\Repository;

use PDO;
use Throwable;

final class FiscalConfigurationRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string,mixed> */
    public function companyFiscalData(): array
    {
        $row = $this->connection->query(
            'SELECT razao_social, nome_fantasia, documento, inscricao_estadual,
                    inscricao_municipal, crt, cnae_principal, email, telefone,
                    endereco_logradouro, endereco_numero, endereco_complemento,
                    endereco_bairro, endereco_cidade, endereco_uf, endereco_cep,
                    codigo_municipio_ibge
               FROM configuracoes_empresa WHERE id = 1'
        )->fetch();

        return $row === false ? [] : $row;
    }

    /** @return array<string,mixed>|null */
    public function configurationById(int $id): ?array
    {
        $statement = $this->connection->prepare($this->configurationSql() . ' WHERE cfg.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function latestConfiguration(string $environment, string $model): ?array
    {
        $statement = $this->connection->prepare(
            $this->configurationSql()
            . ' WHERE cfg.ambiente = :environment AND cfg.modelo = :model'
            . ' ORDER BY cfg.versao DESC LIMIT 1'
        );
        $statement->execute(['environment' => $environment, 'model' => $model]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function activeSeries(string $environment, string $model): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, ambiente, modelo, serie, proximo_numero, ultimo_numero_reservado,
                    status, criado_em, atualizado_em
               FROM fiscal_series
              WHERE ambiente = :environment AND modelo = :model AND status = \'ativa\'
              ORDER BY serie ASC'
        );
        $statement->execute(['environment' => $environment, 'model' => $model]);
        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function activeCertificates(): array
    {
        return $this->connection->query(
            'SELECT id, titular_cnpj, titular_nome, certificado_fingerprint_sha256,
                    valido_de, valido_ate, criado_em
               FROM fiscal_certificados
              WHERE status = \'ativo\' AND valido_ate >= CURRENT_TIMESTAMP
              ORDER BY valido_ate DESC, id DESC'
        )->fetchAll();
    }

    /** @return array<string,int> */
    public function productReadiness(int $crt): array
    {
        $taxCodeColumn = in_array($crt, [1, 2, 4], true) ? 'csosn' : 'cst_icms';
        $statement = $this->connection->query(
            'SELECT COUNT(*) AS sale_products,
                    SUM(CASE WHEN ncm IS NULL OR ncm NOT REGEXP \'^[0-9]{8}$\' THEN 1 ELSE 0 END) AS missing_ncm,
                    SUM(CASE WHEN origem_mercadoria IS NULL OR origem_mercadoria > 8 THEN 1 ELSE 0 END) AS missing_origin,
                    SUM(CASE WHEN cfop_padrao IS NULL OR cfop_padrao NOT REGEXP \'^[0-9]{4}$\' THEN 1 ELSE 0 END) AS missing_cfop,
                    SUM(CASE WHEN ' . $taxCodeColumn . ' IS NULL OR ' . $taxCodeColumn . ' NOT REGEXP \'^[0-9]{3}$\' THEN 1 ELSE 0 END) AS missing_icms_code,
                    SUM(CASE WHEN cst_pis IS NULL OR cst_pis NOT REGEXP \'^[0-9]{2}$\' THEN 1 ELSE 0 END) AS missing_pis,
                    SUM(CASE WHEN cst_cofins IS NULL OR cst_cofins NOT REGEXP \'^[0-9]{2}$\' THEN 1 ELSE 0 END) AS missing_cofins,
                    SUM(CASE WHEN unidade_tributavel IS NULL OR TRIM(unidade_tributavel) = \'\' THEN 1 ELSE 0 END) AS missing_tax_unit
               FROM produtos
              WHERE status = \'ativo\' AND preco_venda > 0'
        );
        $row = $statement->fetch() ?: [];
        return array_map('intval', $row);
    }

    /** @return array<string,int> */
    public function clientReadiness(): array
    {
        $row = $this->connection->query(
            'SELECT COUNT(*) AS active_clients,
                    SUM(CASE WHEN documento IS NOT NULL AND documento <> \'\'
                              AND (endereco IS NULL OR numero IS NULL OR bairro IS NULL
                                   OR cidade IS NULL OR uf IS NULL OR cep IS NULL
                                   OR codigo_municipio_ibge IS NULL)
                             THEN 1 ELSE 0 END) AS identified_without_address,
                    SUM(CASE WHEN tipo_pessoa = \'juridica\' AND indicador_ie = \'contribuinte\'
                              AND (inscricao_estadual IS NULL OR TRIM(inscricao_estadual) = \'\')
                             THEN 1 ELSE 0 END) AS contributors_without_ie,
                    SUM(CASE WHEN codigo_municipio_ibge IS NOT NULL
                              AND codigo_municipio_ibge NOT REGEXP \'^[0-9]{7}$\'
                             THEN 1 ELSE 0 END) AS invalid_city_code
               FROM clientes WHERE status = \'ativo\''
        )->fetch() ?: [];
        return array_map('intval', $row);
    }

    /** @param array<string,mixed> $metadata @param array<string,string> $secret */
    public function insertCertificate(array $metadata, array $secret, int $userId): int
    {
        return $this->transactional(function () use ($metadata, $secret, $userId): int {
            $statement = $this->connection->prepare(
            'INSERT INTO fiscal_certificados
                (arquivo_referencia, arquivo_sha256, certificado_fingerprint_sha256,
                 certificado_serial, titular_cnpj, titular_nome, valido_de, valido_ate,
                 senha_ciphertext, senha_nonce, senha_tag, cifra_algoritmo, chave_versao,
                 status, criado_por)
             VALUES
                (:reference, :file_sha256, :fingerprint, :serial, :holder_cnpj, :holder_name,
                 :valid_from, :valid_to, :ciphertext, :nonce, :tag, :algorithm, :key_version,
                 \'ativo\', :user_id)'
            );
            $statement->execute([
            'reference' => $metadata['reference'],
            'file_sha256' => $metadata['file_sha256'],
            'fingerprint' => $metadata['fingerprint'],
            'serial' => $metadata['serial'] ?: null,
            'holder_cnpj' => $metadata['holder_cnpj'],
            'holder_name' => $metadata['holder_name'] ?: null,
            'valid_from' => date('Y-m-d H:i:s', (int) $metadata['valid_from']),
            'valid_to' => date('Y-m-d H:i:s', (int) $metadata['valid_to']),
            'ciphertext' => base64_decode($secret['ciphertext'], true),
            'nonce' => base64_decode($secret['nonce'], true),
            'tag' => base64_decode($secret['tag'], true),
            'algorithm' => $secret['algorithm'],
            'key_version' => $secret['key_version'],
            'user_id' => $userId,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->audit('certificado', $id, 'cadastrado', null, null, $userId, [
                'fingerprint' => $metadata['fingerprint'],
                'valid_to' => date('Y-m-d H:i:s', (int) $metadata['valid_to']),
            ]);
            return $id;
        });
    }

    /** @param array<string,mixed> $data @param array<string,string>|null $csc */
    public function insertConfiguration(array $data, ?array $csc, int $userId): int
    {
        return $this->transactional(function () use ($data, $csc, $userId): int {
            $version = $this->nextConfigurationVersion($data['environment'], $data['model']);
            $statement = $this->connection->prepare(
                'INSERT INTO fiscal_configuracoes
                    (ambiente, modelo, versao, uf, schema_versao, qr_code_versao,
                     certificado_id, csc_id, csc_ciphertext, csc_nonce, csc_tag,
                     csc_algoritmo, segredo_chave_versao, status, criado_por)
                 VALUES
                    (:environment, :model, :version, :state, :schema_version, :qr_version,
                     :certificate_id, :csc_id, :csc_ciphertext, :csc_nonce, :csc_tag,
                     :csc_algorithm, :secret_key_version, \'rascunho\', :user_id)'
            );
            $statement->execute([
                'environment' => $data['environment'], 'model' => $data['model'],
                'version' => $version, 'state' => $data['state'],
                'schema_version' => $data['schema_version'], 'qr_version' => $data['qr_version'],
                'certificate_id' => $data['certificate_id'], 'csc_id' => $data['csc_id'],
                'csc_ciphertext' => $csc === null ? null : base64_decode($csc['ciphertext'], true),
                'csc_nonce' => $csc === null ? null : base64_decode($csc['nonce'], true),
                'csc_tag' => $csc === null ? null : base64_decode($csc['tag'], true),
                'csc_algorithm' => $csc['algorithm'] ?? null,
                'secret_key_version' => $csc['key_version'] ?? null,
                'user_id' => $userId,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->audit('configuracao', $id, 'versao_criada', $data['environment'], $data['model'], $userId, [
                'version' => $version, 'has_csc' => $csc !== null,
            ]);
            return $id;
        });
    }

    /** @param array<string,mixed> $data */
    public function saveSeries(array $data, int $userId): void
    {
        $this->connection->prepare(
            'INSERT INTO fiscal_series
                (ambiente, modelo, serie, proximo_numero, status, criado_por, atualizado_por)
             VALUES (:environment, :model, :series, :next_number, \'ativa\', :user_id, :user_id)
             ON DUPLICATE KEY UPDATE
                proximo_numero = GREATEST(proximo_numero, VALUES(proximo_numero)),
                status = \'ativa\', atualizado_por = VALUES(atualizado_por)'
        )->execute([
            'environment' => $data['environment'], 'model' => $data['model'],
            'series' => $data['series'], 'next_number' => $data['next_number'], 'user_id' => $userId,
        ]);
        $this->audit('serie', null, 'configurada', $data['environment'], $data['model'], $userId, [
            'series' => $data['series'], 'next_number' => $data['next_number'],
        ]);
    }

    public function activateConfiguration(int $id, string $environment, string $model, int $userId): void
    {
        $this->transactional(function () use ($id, $environment, $model, $userId): void {
            $this->connection->prepare(
                'UPDATE fiscal_configuracoes SET status = \'inativa\', desativado_por = :user_id,
                        desativado_em = CURRENT_TIMESTAMP
                  WHERE ambiente = :environment AND modelo = :model AND status = \'ativa\''
            )->execute(['user_id' => $userId, 'environment' => $environment, 'model' => $model]);
            $this->connection->prepare(
                'UPDATE fiscal_configuracoes SET status = \'ativa\', ativado_por = :user_id,
                        ativado_em = CURRENT_TIMESTAMP, desativado_por = NULL, desativado_em = NULL
                  WHERE id = :id AND ambiente = :environment AND modelo = :model'
            )->execute(['id' => $id, 'user_id' => $userId, 'environment' => $environment, 'model' => $model]);
            $this->audit('configuracao', $id, 'ativada', $environment, $model, $userId, []);
        });
    }

    private function configurationSql(): string
    {
        return 'SELECT cfg.id, cfg.ambiente, cfg.modelo, cfg.versao, cfg.uf, cfg.schema_versao,
                       cfg.qr_code_versao, cfg.certificado_id, cfg.csc_id,
                       CASE WHEN cfg.csc_ciphertext IS NULL THEN 0 ELSE 1 END AS has_csc,
                       cfg.status, cfg.criado_em, cert.arquivo_referencia,
                       cert.titular_cnpj, cert.titular_nome, cert.valido_de, cert.valido_ate,
                       cert.status AS certificado_status
                  FROM fiscal_configuracoes cfg
                  JOIN fiscal_certificados cert ON cert.id = cfg.certificado_id';
    }

    private function nextConfigurationVersion(string $environment, string $model): int
    {
        $statement = $this->connection->prepare(
            'SELECT COALESCE(MAX(versao), 0) FROM fiscal_configuracoes
              WHERE ambiente = :environment AND modelo = :model FOR UPDATE'
        );
        $statement->execute(['environment' => $environment, 'model' => $model]);
        return ((int) $statement->fetchColumn()) + 1;
    }

    /** @param array<string,mixed> $details */
    private function audit(string $type, ?int $id, string $action, ?string $environment, ?string $model, int $userId, array $details): void
    {
        $this->connection->prepare(
            'INSERT INTO fiscal_auditoria
                (entidade_tipo, entidade_id, acao, ambiente, modelo, usuario_id, detalhes)
             VALUES (:type, :id, :action, :environment, :model, :user_id, :details)'
        )->execute([
            'type' => $type, 'id' => $id, 'action' => $action,
            'environment' => $environment, 'model' => $model, 'user_id' => $userId,
            'details' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function transactional(callable $callback): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $callback();
            $this->connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }
}
