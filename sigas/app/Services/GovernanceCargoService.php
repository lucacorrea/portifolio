<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use App\Repositories\CargoRepository;
use InvalidArgumentException;
use RuntimeException;

final class GovernanceCargoService
{
    public function __construct(
        private readonly CargoRepository $cargos,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array{schema_ready:bool,rows:list<array<string,mixed>>,stats:list<array<string,string>>} */
    public function page(): array
    {
        $schemaReady = $this->cargos->schemaReady();
        $rows = $schemaReady ? $this->cargos->all() : [];
        $active = 0;
        $inactive = 0;
        $users = 0;

        foreach ($rows as $row) {
            if ((int) ($row['ativo'] ?? 0) === 1) {
                $active++;
            } else {
                $inactive++;
            }
            $users += (int) ($row['usuarios'] ?? 0);
        }

        return [
            'schema_ready' => $schemaReady,
            'rows' => $rows,
            'stats' => [
                ['label' => 'Cargos cadastrados', 'value' => (string) count($rows), 'detail' => 'Catálogo institucional', 'icon' => 'person-badge'],
                ['label' => 'Ativos', 'value' => (string) $active, 'detail' => 'Disponíveis para novos usuários', 'icon' => 'check-circle'],
                ['label' => 'Inativos', 'value' => (string) $inactive, 'detail' => 'Bloqueados para novas atribuições', 'icon' => 'pause-circle'],
                ['label' => 'Usuários vinculados', 'value' => (string) $users, 'detail' => 'Contas usando cargos do catálogo', 'icon' => 'people'],
            ],
        ];
    }

    /** @return list<array{id:int,nome:string,slug:string}> */
    public function activeOptions(): array
    {
        return $this->cargos->activeOptions();
    }

    public function create(User $operator, string $name, ?string $description): int
    {
        $this->ensureSchema();
        [$name, $description] = $this->normalize($name, $description);
        $slug = $this->slug($name);

        if ($this->cargos->nameExists($name)) {
            throw new InvalidArgumentException('Já existe um cargo com esse nome.');
        }
        if ($this->cargos->slugExists($slug)) {
            $slug .= '-' . substr(hash('sha256', mb_strtolower($name)), 0, 6);
        }

        return Database::transaction(function () use ($operator, $name, $description, $slug): int {
            $id = $this->cargos->create($name, $slug, $description);
            $this->audit->record(
                $operator->id,
                null,
                'cargo_criado',
                'governanca_acessos',
                'Cargo institucional cadastrado.',
                null,
                [
                    'cargo_id' => $id,
                    'nome' => $name,
                    'slug' => $slug,
                    'descricao' => $description,
                    'ativo' => 1,
                ]
            );
            return $id;
        });
    }

    public function update(User $operator, int $id, string $name, ?string $description): void
    {
        $this->ensureSchema();
        $before = $this->cargos->findById($id);
        if ($before === null) {
            throw new InvalidArgumentException('Cargo não encontrado.');
        }

        [$name, $description] = $this->normalize($name, $description);
        $slug = $this->slug($name);

        if ($this->cargos->nameExists($name, $id)) {
            throw new InvalidArgumentException('Já existe outro cargo com esse nome.');
        }
        if ($this->cargos->slugExists($slug, $id)) {
            $slug .= '-' . substr(hash('sha256', mb_strtolower($name)), 0, 6);
        }

        Database::transaction(function () use ($operator, $id, $name, $description, $slug, $before): void {
            $oldName = (string) ($before['nome'] ?? '');
            $this->cargos->update($id, $name, $slug, $description);
            $affectedUsers = $this->cargos->renameUsers($oldName, $name);

            $this->audit->record(
                $operator->id,
                null,
                'cargo_atualizado',
                'governanca_acessos',
                'Cargo institucional atualizado e usuários sincronizados.',
                [
                    'cargo_id' => $id,
                    'nome' => $oldName,
                    'slug' => (string) ($before['slug'] ?? ''),
                    'descricao' => $before['descricao'] ?? null,
                ],
                [
                    'cargo_id' => $id,
                    'nome' => $name,
                    'slug' => $slug,
                    'descricao' => $description,
                    'usuarios_atualizados' => $affectedUsers,
                ]
            );
        });
    }

    public function setActive(User $operator, int $id, bool $active): void
    {
        $this->ensureSchema();
        $before = $this->cargos->findById($id);
        if ($before === null) {
            throw new InvalidArgumentException('Cargo não encontrado.');
        }

        $current = (int) ($before['ativo'] ?? 0) === 1;
        if ($current === $active) {
            return;
        }

        Database::transaction(function () use ($operator, $id, $active, $before): void {
            $this->cargos->setActive($id, $active);
            $this->audit->record(
                $operator->id,
                null,
                $active ? 'cargo_ativado' : 'cargo_inativado',
                'governanca_acessos',
                $active ? 'Cargo disponibilizado para novas atribuições.' : 'Cargo removido das novas atribuições.',
                [
                    'cargo_id' => $id,
                    'nome' => (string) ($before['nome'] ?? ''),
                    'ativo' => (int) ($before['ativo'] ?? 0),
                ],
                [
                    'cargo_id' => $id,
                    'nome' => (string) ($before['nome'] ?? ''),
                    'ativo' => $active ? 1 : 0,
                ]
            );
        });
    }

    public function activeName(int $id): string
    {
        $this->ensureSchema();
        $cargo = $this->cargos->findActiveById($id);
        if ($cargo === null) {
            throw new InvalidArgumentException('Selecione um cargo ativo cadastrado na Governança.');
        }

        return (string) $cargo['nome'];
    }

    private function ensureSchema(): void
    {
        if (!$this->cargos->schemaReady()) {
            throw new RuntimeException('O catálogo de cargos ainda não foi inicializado no banco de dados.');
        }
    }

    /** @return array{0:string,1:string|null} */
    private function normalize(string $name, ?string $description): array
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?: '';
        $description = trim((string) $description);
        $description = $description === '' ? null : preg_replace('/\s+/u', ' ', $description);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Informe um nome de cargo entre 2 e 120 caracteres.');
        }
        if ($description !== null && mb_strlen($description) > 255) {
            throw new InvalidArgumentException('A descrição do cargo deve ter no máximo 255 caracteres.');
        }

        return [$name, $description];
    }

    private function slug(string $name): string
    {
        $normalized = trim($name);
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) : false;
        $source = is_string($ascii) && $ascii !== '' ? $ascii : $normalized;
        $slug = mb_strtolower($source);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'cargo-' . substr(hash('sha256', mb_strtolower($name)), 0, 10);
        }

        return mb_substr($slug, 0, 120);
    }
}
