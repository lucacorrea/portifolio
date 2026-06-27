<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\CompanyAuditRepository;
use App\Repositories\StoreRepository;
use App\Repositories\UserCompanyRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class StoreService
{
    private PDO $db;
    private StoreRepository $stores;
    private UserCompanyRepository $memberships;
    private StoreAccessService $access;
    private CompanyAuditRepository $audit;
    private CompanyLogoService $logos;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
        $this->stores = new StoreRepository($this->db);
        $this->memberships = new UserCompanyRepository($this->db);
        $this->access = new StoreAccessService($this->memberships, $this->stores);
        $this->audit = new CompanyAuditRepository($this->db);
        $this->logos = new CompanyLogoService();
    }

    public function listChildren(int $usuarioId, int $empresaAtivaId): array
    {
        $this->access->assertAdmin($usuarioId, $empresaAtivaId);

        return $this->stores->findChildren($empresaAtivaId);
    }

    public function createStore(
        int $usuarioId,
        int $empresaAtivaId,
        array $data,
        ?array $logoFile = null
    ): int {
        $this->access->assertAdmin($usuarioId, $empresaAtivaId);
        $parent = $this->stores->findById($empresaAtivaId);

        if (!$parent || (int)$parent['ativo'] !== 1) {
            throw new RuntimeException('Empresa pai inválida ou inativa.');
        }

        $payload = $this->validateStoreData($empresaAtivaId, $data);
        $newLogo = null;

        $this->db->beginTransaction();
        try {
            $storeId = $this->stores->create($empresaAtivaId, $payload);

            $this->memberships->createMembership($usuarioId, $storeId, 'admin', false);
            $this->createDefaultSettings($storeId, $payload);

            if ($this->hasUploadedFile($logoFile)) {
                $newLogo = $this->logos->upload($storeId, $logoFile);
                $this->stores->updateLogo($storeId, $newLogo);
                $this->touchBranding($storeId);
            }

            $this->audit->record($usuarioId, $empresaAtivaId, $storeId, 'criar_loja');
            $this->db->commit();

            return $storeId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($newLogo !== null) {
                $this->logos->delete($newLogo);
            }

            throw $e;
        }
    }

    public function updateStore(
        int $usuarioId,
        int $empresaAtivaId,
        int $lojaId,
        array $data,
        ?array $logoFile = null
    ): bool {
        $this->access->assertAdmin($usuarioId, $empresaAtivaId);
        $store = $this->access->assertChildOf($empresaAtivaId, $lojaId);
        $payload = $this->validateStoreData($empresaAtivaId, $data, $lojaId);
        $newLogo = null;
        $oldLogo = trim((string)($store['logo'] ?? ''));

        $this->db->beginTransaction();
        try {
            $updated = $this->stores->update($empresaAtivaId, $lojaId, $payload);

            if ($this->hasUploadedFile($logoFile)) {
                $newLogo = $this->logos->upload($lojaId, $logoFile);
                $this->stores->updateLogo($lojaId, $newLogo);
                $this->touchBranding($lojaId);
            }

            $this->audit->record($usuarioId, $empresaAtivaId, $lojaId, 'editar_loja');
            $this->db->commit();

            if ($newLogo !== null && $oldLogo !== '' && $oldLogo !== $newLogo) {
                $this->logos->delete($oldLogo);
            }

            return $updated;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($newLogo !== null) {
                $this->logos->delete($newLogo);
            }

            throw $e;
        }
    }

    public function activateStore(int $usuarioId, int $empresaAtivaId, int $lojaId): bool
    {
        return $this->setStoreActive($usuarioId, $empresaAtivaId, $lojaId, true);
    }

    public function deactivateStore(int $usuarioId, int $empresaAtivaId, int $lojaId): bool
    {
        return $this->setStoreActive($usuarioId, $empresaAtivaId, $lojaId, false);
    }

    private function setStoreActive(int $usuarioId, int $empresaAtivaId, int $lojaId, bool $active): bool
    {
        $this->access->assertAdmin($usuarioId, $empresaAtivaId);
        $this->access->assertChildOf($empresaAtivaId, $lojaId);

        $this->db->beginTransaction();
        try {
            $updated = $this->stores->setActive($empresaAtivaId, $lojaId, $active);
            $this->audit->record($usuarioId, $empresaAtivaId, $lojaId, $active ? 'ativar_loja' : 'inativar_loja');
            $this->db->commit();

            return $updated;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function validateStoreData(int $empresaPaiId, array $data, ?int $ignoreId = null): array
    {
        $nome = trim((string)($data['nome'] ?? ''));
        $nomeFantasia = trim((string)($data['nome_fantasia'] ?? ''));
        $codigo = trim((string)($data['codigo'] ?? ''));
        $cpfCnpj = trim((string)($data['cpf_cnpj'] ?? ''));
        $telefone = trim((string)($data['telefone'] ?? ''));
        $endereco = trim((string)($data['endereco'] ?? ''));

        if ($nome === '' || mb_strlen($nome) < 2) {
            throw new InvalidArgumentException('Informe o nome da loja com pelo menos 2 caracteres.');
        }

        if (mb_strlen($nome) > 180) {
            throw new InvalidArgumentException('O nome da loja deve ter no máximo 180 caracteres.');
        }

        if ($nomeFantasia !== '' && mb_strlen($nomeFantasia) > 180) {
            throw new InvalidArgumentException('O nome fantasia deve ter no máximo 180 caracteres.');
        }

        if ($codigo !== '' && (!preg_match('/^[A-Za-z0-9_-]+$/', $codigo) || mb_strlen($codigo) > 50)) {
            throw new InvalidArgumentException('O código aceita até 50 caracteres: letras, números, hífen e underline.');
        }

        if ($codigo !== '' && $this->stores->codeExists($empresaPaiId, $codigo, $ignoreId)) {
            throw new InvalidArgumentException('Já existe uma loja com este código nesta empresa.');
        }

        if ($cpfCnpj !== '' && mb_strlen($cpfCnpj) > 20) {
            throw new InvalidArgumentException('O CPF/CNPJ deve ter no máximo 20 caracteres.');
        }

        if ($telefone !== '' && mb_strlen($telefone) > 30) {
            throw new InvalidArgumentException('O telefone deve ter no máximo 30 caracteres.');
        }

        if ($endereco !== '' && mb_strlen($endereco) > 255) {
            throw new InvalidArgumentException('O endereço deve ter no máximo 255 caracteres.');
        }

        return [
            'nome' => $nome,
            'nome_fantasia' => $nomeFantasia !== '' ? $nomeFantasia : null,
            'codigo' => $codigo !== '' ? $codigo : null,
            'cpf_cnpj' => $cpfCnpj !== '' ? $cpfCnpj : null,
            'telefone' => $telefone !== '' ? $telefone : null,
            'endereco' => $endereco !== '' ? $endereco : null,
        ];
    }

    private function hasUploadedFile(?array $file): bool
    {
        return is_array($file)
            && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function createDefaultSettings(int $empresaId, array $data): void
    {
        $appName = trim((string)($data['nome_fantasia'] ?? '')) !== ''
            ? trim((string)$data['nome_fantasia'])
            : trim((string)$data['nome']);

        $stmt = $this->db->prepare(
            'INSERT INTO configuracoes (empresa_id, chave, valor)
             VALUES (:empresa_id, :chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );

        foreach ([
            'app_name' => $appName,
            'app_short_name' => mb_substr($appName, 0, 40),
            'branding_updated_at' => date('Y-m-d H:i:s'),
        ] as $key => $value) {
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':chave' => $key,
                ':valor' => $value,
            ]);
        }
    }

    private function touchBranding(int $empresaId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO configuracoes (empresa_id, chave, valor)
             VALUES (:empresa_id, :chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':chave' => 'branding_updated_at',
            ':valor' => date('Y-m-d H:i:s'),
        ]);
    }
}
