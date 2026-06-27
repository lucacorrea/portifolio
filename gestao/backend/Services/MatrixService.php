<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\CompanyAuditRepository;
use App\Repositories\MatrixRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class MatrixService
{
    private PDO $db;
    private MatrixRepository $matrices;
    private PlatformAuthorizationService $platform;
    private MatrixAdminService $admins;
    private PlatformOwnerProvisioningService $ownerProvisioning;
    private CompanyAuditRepository $audit;
    private CompanyLogoService $logos;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
        $this->matrices = new MatrixRepository($this->db);
        $this->platform = new PlatformAuthorizationService();
        $this->admins = new MatrixAdminService($this->db);
        $this->ownerProvisioning = new PlatformOwnerProvisioningService($this->db);
        $this->audit = new CompanyAuditRepository($this->db);
        $this->logos = new CompanyLogoService();
    }

    public function listMatrices(int $platformUserId): array
    {
        $this->platform->assertPlatformOwner($platformUserId);

        return $this->matrices->listMatrices();
    }

    public function createMatrix(int $platformUserId, array $data, ?array $logoFile = null): int
    {
        $this->platform->assertPlatformOwner($platformUserId);
        $payload = $this->validateMatrixData($data);
        $adminEmail = mb_strtolower(trim((string)($data['admin_email'] ?? '')));
        if ($adminEmail === '') {
            throw new InvalidArgumentException('O e-mail do administrador principal é obrigatório.');
        }

        $newLogo = null;

        $this->db->beginTransaction();
        try {
            $matrixId = $this->matrices->createMatrix($payload);

            $this->admins->assignPrimaryAdminInsideTransaction($platformUserId, $matrixId, $adminEmail, [
                'nome' => $data['admin_nome'] ?? '',
                'telefone' => $data['admin_telefone'] ?? '',
                'senha' => $data['admin_senha'] ?? '',
                'senha_confirmacao' => $data['admin_senha_confirmacao'] ?? '',
            ]);

            $this->createDefaultSettings($matrixId, $payload);

            if ($this->hasUploadedFile($logoFile)) {
                $newLogo = $this->logos->upload($matrixId, $logoFile);
                $this->matrices->updateLogo($matrixId, $newLogo);
                $this->touchBranding($matrixId);
            }

            $this->ownerProvisioning->linkExistingOwnersToCompany($matrixId);
            $this->audit->record($platformUserId, $matrixId, $matrixId, 'criar_matriz');
            $this->db->commit();

            return $matrixId;
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

    public function updateMatrix(int $platformUserId, int $matrixId, array $data, ?array $logoFile = null): void
    {
        $this->platform->assertPlatformOwner($platformUserId);
        $matrix = $this->matrices->findMatrixById($matrixId);
        if (!$matrix) {
            throw new RuntimeException('Matriz não encontrada.');
        }

        $payload = $this->validateMatrixData($data);
        $newLogo = null;
        $oldLogo = trim((string)($matrix['logo'] ?? ''));

        $this->db->beginTransaction();
        try {
            $this->matrices->updateMatrix($matrixId, $payload);

            if ($this->hasUploadedFile($logoFile)) {
                $newLogo = $this->logos->upload($matrixId, $logoFile);
                $this->matrices->updateLogo($matrixId, $newLogo);
                $this->touchBranding($matrixId);
            }

            if (trim((string)($data['admin_email'] ?? '')) !== '') {
                $this->admins->assignPrimaryAdminInsideTransaction($platformUserId, $matrixId, (string)$data['admin_email'], [
                    'nome' => $data['admin_nome'] ?? '',
                    'telefone' => $data['admin_telefone'] ?? '',
                    'senha' => $data['admin_senha'] ?? '',
                    'senha_confirmacao' => $data['admin_senha_confirmacao'] ?? '',
                ], true);
            }

            $this->audit->record($platformUserId, $matrixId, $matrixId, 'editar_matriz');
            $this->db->commit();

            if ($newLogo !== null && $oldLogo !== '' && $oldLogo !== $newLogo) {
                $this->logos->delete($oldLogo);
            }
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

    public function setMatrixActive(int $platformUserId, int $matrixId, bool $active): void
    {
        $this->platform->assertPlatformOwner($platformUserId);
        if (!$this->matrices->setMatrixActive($matrixId, $active)) {
            throw new RuntimeException('Matriz não encontrada.');
        }

        $this->audit->record($platformUserId, $matrixId, $matrixId, $active ? 'ativar_matriz' : 'inativar_matriz');
    }

    public function enterMatrix(int $platformUserId, int $matrixId): void
    {
        $this->platform->assertPlatformOwner($platformUserId);
        $this->ownerProvisioning->linkExistingOwnersToCompany($matrixId);
    }

    public function missingOwnerEmails(): array
    {
        return $this->ownerProvisioning->missingOwnerEmails();
    }

    private function validateMatrixData(array $data): array
    {
        $nome = trim((string)($data['nome'] ?? ''));
        $nomeFantasia = trim((string)($data['nome_fantasia'] ?? ''));
        $codigo = trim((string)($data['codigo'] ?? ''));
        $cpfCnpj = trim((string)($data['cpf_cnpj'] ?? ''));
        $telefone = trim((string)($data['telefone'] ?? ''));
        $endereco = trim((string)($data['endereco'] ?? ''));

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 180) {
            throw new InvalidArgumentException('Informe a razão social da matriz.');
        }

        if ($nomeFantasia !== '' && mb_strlen($nomeFantasia) > 180) {
            throw new InvalidArgumentException('O nome fantasia deve ter no máximo 180 caracteres.');
        }

        if ($codigo !== '' && (!preg_match('/^[A-Za-z0-9_-]+$/', $codigo) || mb_strlen($codigo) > 50)) {
            throw new InvalidArgumentException('O código aceita até 50 caracteres: letras, números, hífen e underline.');
        }

        if ($cpfCnpj !== '' && mb_strlen($cpfCnpj) > 20) {
            throw new InvalidArgumentException('O CPF/CNPJ deve ter no máximo 20 caracteres.');
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

    private function hasUploadedFile(?array $file): bool
    {
        return is_array($file)
            && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }
}
