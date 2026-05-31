<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\CompanyRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Security\Permission;

final class SettingController
{
    public function __construct(
        private ?CompanyRepository $companies = null,
        private ?SettingRepository $settings = null,
        private ?UserRepository $users = null
    ) {
        $this->companies ??= new CompanyRepository();
        $this->settings ??= new SettingRepository();
        $this->users ??= new UserRepository();
    }

    public function list(): void
    {
        Auth::requireLogin();

        $empresaId = (int)Auth::user()['empresa_id'];
        $company = $this->companies->findById($empresaId);

        if (!$company) {
            Response::json(['success' => false, 'message' => 'Empresa não encontrada.'], 404);
        }

        $settings = $this->settings->getAll($empresaId);

        Response::json([
            'success' => true,
            'data' => [
                'settings' => $this->formatSettings($company, $settings),
                'users' => array_map([$this, 'formatUser'], $this->users->findByCompany($empresaId)),
            ],
        ]);
    }

    public function save(Request $request, array $payload): void
    {
        Auth::requireLogin();

        $user = Auth::user();

        if (!Permission::canManageSettings($user)) {
            Response::json(['success' => false, 'message' => 'Usuário sem permissão para alterar configurações.'], 403);
        }

        if (!Csrf::validate((string)($payload['csrf_token'] ?? $request->post('csrf_token', '')))) {
            Response::json(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 419);
        }

        $section = (string)($payload['section'] ?? $request->post('section', ''));

        if (!in_array($section, ['company', 'receipt', 'due'], true)) {
            Response::json(['success' => false, 'message' => 'Configuração inválida.'], 422);
        }

        $empresaId = (int)$user['empresa_id'];
        $db = $this->settings->connection();

        try {
            $db->beginTransaction();

            match ($section) {
                'company' => $this->saveCompany($empresaId, $payload),
                'receipt' => $this->saveReceipt($empresaId, $payload),
                'due' => $this->saveDueRules($empresaId, $payload),
            };

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Response::json(['success' => false, 'message' => 'Não foi possível salvar a configuração.'], 500);
        }

        Response::json(['success' => true, 'message' => 'Configuração salva com sucesso.']);
    }

    private function saveCompany(int $empresaId, array $payload): void
    {
        $nome = trim((string)($payload['companyName'] ?? ''));
        $telefone = trim((string)($payload['companyPhone'] ?? ''));
        $endereco = trim((string)($payload['companyAddress'] ?? ''));

        if (!Validator::required($nome) || !Validator::max($nome, 180)) {
            Response::json(['success' => false, 'message' => 'Informe um nome de empresa válido.'], 422);
        }

        if (!Validator::max($telefone, 30) || !Validator::max($endereco, 255)) {
            Response::json(['success' => false, 'message' => 'Telefone ou endereço acima do limite permitido.'], 422);
        }

        $this->companies->updateProfile($empresaId, [
            'nome' => $nome,
            'telefone' => $telefone,
            'endereco' => $endereco,
        ]);
    }

    private function saveReceipt(int $empresaId, array $payload): void
    {
        $mode = (string)($payload['receiptMode'] ?? '');
        $template = (string)($payload['receiptTemplate'] ?? '');
        $allowedModes = ['perguntar', 'sempre', 'nunca'];
        $allowedTemplates = ['detalhado', 'simples'];

        if (!in_array($mode, $allowedModes, true) || !in_array($template, $allowedTemplates, true)) {
            Response::json(['success' => false, 'message' => 'Opção de comprovante inválida.'], 422);
        }

        $this->settings->upsertMany($empresaId, [
            'comprovante_modo' => $mode,
            'comprovante_modelo' => $template,
        ]);
    }

    private function saveDueRules(int $empresaId, array $payload): void
    {
        $expirationDays = (int)($payload['expirationAlertDays'] ?? 0);
        $debtDays = (int)($payload['debtDueDays'] ?? 0);

        if ($expirationDays < 0 || $expirationDays > 365 || $debtDays < 1 || $debtDays > 365) {
            Response::json(['success' => false, 'message' => 'Prazos devem estar entre 0 e 365 dias.'], 422);
        }

        $this->settings->upsertMany($empresaId, [
            'alerta_validade_dias' => (string)$expirationDays,
            'prazo_divida_dias' => (string)$debtDays,
        ]);
    }

    private function formatSettings(array $company, array $settings): array
    {
        return [
            'companyName' => $company['nome'] ?? '',
            'companyPhone' => $company['telefone'] ?? '',
            'companyAddress' => $company['endereco'] ?? '',
            'receiptMode' => $settings['comprovante_modo'] ?? 'perguntar',
            'receiptTemplate' => $settings['comprovante_modelo'] ?? 'detalhado',
            'expirationAlertDays' => (int)($settings['alerta_validade_dias'] ?? 7),
            'debtDueDays' => (int)($settings['prazo_divida_dias'] ?? 30),
            'blockExpiredProducts' => ((string)($settings['bloquear_produto_vencido'] ?? '1')) === '1',
            'blockNegativeStock' => ((string)($settings['bloquear_estoque_negativo'] ?? '1')) === '1',
        ];
    }

    private function formatUser(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'name' => $user['nome'],
            'email' => $user['email'],
            'role' => ucfirst((string)$user['nivel']),
            'status' => ((int)$user['ativo'] === 1) ? 'Ativo' : 'Inativo',
        ];
    }
}
