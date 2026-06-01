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
use App\Security\Password;
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
            Response::fail('Empresa não encontrada.', [], 404);
        }

        $settings = $this->settings->getAll($empresaId);

        Response::success([
                'settings' => $this->formatSettings($company, $settings),
                'users' => array_map([$this, 'formatUser'], $this->users->findByCompany($empresaId)),
        ]);
    }

    public function save(Request $request, array $payload): void
    {
        Auth::requireLogin();

        $user = Auth::user();

        if (!Permission::canManageSettings($user)) {
            Response::fail('Usuário sem permissão para alterar configurações.', [], 403);
        }

        if (!Csrf::validate((string)($payload['csrf_token'] ?? $request->post('csrf_token', '')))) {
            Response::fail('Sessão expirada. Atualize a página e tente novamente.', [], 419);
        }

        $section = (string)($payload['section'] ?? $request->post('section', ''));

        if (!in_array($section, ['company', 'users', 'receipt', 'due', 'stock', 'payments', 'cash', 'security'], true)) {
            Response::fail('Configuração inválida.', [], 422);
        }

        $empresaId = (int)$user['empresa_id'];
        $db = $this->settings->connection();

        try {
            $db->beginTransaction();

            match ($section) {
                'company' => $this->saveCompany($empresaId, $payload),
                'users' => $this->saveUser($empresaId, $payload),
                'receipt' => $this->saveReceipt($empresaId, $payload),
                'due' => $this->saveDueRules($empresaId, $payload),
                'stock' => $this->saveStockRules($empresaId, $payload),
                'payments' => $this->savePaymentRules($empresaId, $payload),
                'cash' => $this->saveCashRules($empresaId, $payload),
                'security' => $this->saveSecurityRules($empresaId, $payload),
            };

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Response::fail('Não foi possível salvar a configuração.', [], 500);
        }

        Response::success([], 'Configuração salva com sucesso.');
    }

    private function saveCompany(int $empresaId, array $payload): void
    {
        $nome = trim((string)($payload['companyName'] ?? ''));
        $telefone = trim((string)($payload['companyPhone'] ?? ''));
        $endereco = trim((string)($payload['companyAddress'] ?? ''));

        if (!Validator::required($nome) || !Validator::max($nome, 180)) {
            Response::fail('Informe um nome de empresa válido.', [], 422);
        }

        if (!Validator::max($telefone, 30) || !Validator::max($endereco, 255)) {
            Response::fail('Telefone ou endereço acima do limite permitido.', [], 422);
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
            Response::fail('Opção de comprovante inválida.', [], 422);
        }

        $this->settings->upsertMany($empresaId, [
            'comprovante_modo' => $mode,
            'comprovante_modelo' => $template,
        ]);
    }

    private function saveUser(int $empresaId, array $payload): void
    {
        $name = trim((string)($payload['userName'] ?? ''));
        $email = mb_strtolower(trim((string)($payload['userEmail'] ?? '')));
        $password = (string)($payload['userPassword'] ?? '');
        $role = (string)($payload['userRole'] ?? 'operador');
        $allowedRoles = ['admin', 'gerente', 'operador', 'estoquista', 'leitor'];

        if (!Validator::required($name) || !Validator::max($name, 140)) {
            Response::fail('Informe um nome de usuário válido.', [], 422);
        }

        if (!Validator::email($email) || !Validator::max($email, 180)) {
            Response::fail('Informe um e-mail válido.', [], 422);
        }

        if (mb_strlen($password) < 8) {
            Response::fail('A senha deve ter pelo menos 8 caracteres.', [], 422);
        }

        if (!in_array($role, $allowedRoles, true)) {
            Response::fail('Perfil de usuário inválido.', [], 422);
        }

        if ($this->users->findByEmail($email)) {
            Response::fail('Já existe usuário com este e-mail.', [], 422);
        }

        $this->users->create($empresaId, [
            'nome' => $name,
            'email' => $email,
            'senha_hash' => Password::hash($password),
            'nivel' => $role,
            'ativo' => 1,
        ]);
    }

    private function saveDueRules(int $empresaId, array $payload): void
    {
        $expirationDays = (int)($payload['expirationAlertDays'] ?? 0);
        $debtDays = (int)($payload['debtDueDays'] ?? 0);

        if ($expirationDays < 0 || $expirationDays > 365 || $debtDays < 1 || $debtDays > 365) {
            Response::fail('Prazos devem estar entre 0 e 365 dias.', [], 422);
        }

        $this->settings->upsertMany($empresaId, [
            'alerta_validade_dias' => (string)$expirationDays,
            'prazo_divida_dias' => (string)$debtDays,
        ]);
    }

    private function saveStockRules(int $empresaId, array $payload): void
    {
        $defaultMinStock = (int)($payload['defaultMinStock'] ?? 0);

        if ($defaultMinStock < 0 || $defaultMinStock > 999999) {
            Response::fail('Estoque mínimo padrão inválido.', [], 422);
        }

        $this->settings->upsertMany($empresaId, [
            'estoque_minimo_padrao' => (string)$defaultMinStock,
            'bloquear_produto_vencido' => $this->boolValue($payload['blockExpiredProducts'] ?? true),
            'bloquear_estoque_negativo' => $this->boolValue($payload['blockNegativeStock'] ?? true),
            'alertar_estoque_baixo' => $this->boolValue($payload['lowStockAlert'] ?? true),
        ]);
    }

    private function savePaymentRules(int $empresaId, array $payload): void
    {
        $payments = [
            'pagamento_pix' => $payload['paymentPix'] ?? true,
            'pagamento_dinheiro' => $payload['paymentCash'] ?? true,
            'pagamento_credito' => $payload['paymentCredit'] ?? true,
            'pagamento_debito' => $payload['paymentDebit'] ?? true,
            'pagamento_conta_cliente' => $payload['paymentAccount'] ?? true,
            'pagamento_misto' => $payload['paymentMixed'] ?? true,
        ];

        if (!array_filter($payments, fn (mixed $value): bool => $this->toBool($value))) {
            Response::fail('Mantenha pelo menos uma forma de pagamento ativa.', [], 422);
        }

        $this->settings->upsertMany($empresaId, array_map([$this, 'boolValue'], $payments));
    }

    private function saveCashRules(int $empresaId, array $payload): void
    {
        $discountLimit = (int)($payload['discountLimitPercent'] ?? 0);

        if ($discountLimit < 0 || $discountLimit > 100) {
            Response::fail('Limite de desconto deve estar entre 0 e 100%.', [], 422);
        }

        $this->settings->upsertMany($empresaId, [
            'permitir_desconto' => $this->boolValue($payload['allowDiscount'] ?? true),
            'limite_desconto_percentual' => (string)$discountLimit,
            'exigir_cliente_conta' => $this->boolValue($payload['requireCustomerForAccount'] ?? true),
            'exigir_motivo_cancelamento' => $this->boolValue($payload['requireCancellationReason'] ?? true),
        ]);
    }

    private function saveSecurityRules(int $empresaId, array $payload): void
    {
        $this->settings->upsertMany($empresaId, [
            'auditoria_ativa' => $this->boolValue($payload['auditLogEnabled'] ?? true),
            'confirmar_exclusoes' => $this->boolValue($payload['confirmDeletes'] ?? true),
            'pin_operador_ativo' => $this->boolValue($payload['operatorPinEnabled'] ?? false),
            'notificacoes_ativas' => $this->boolValue($payload['notificationsEnabled'] ?? true),
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
            'defaultMinStock' => (int)($settings['estoque_minimo_padrao'] ?? 0),
            'blockExpiredProducts' => ((string)($settings['bloquear_produto_vencido'] ?? '1')) === '1',
            'blockNegativeStock' => ((string)($settings['bloquear_estoque_negativo'] ?? '1')) === '1',
            'lowStockAlert' => ((string)($settings['alertar_estoque_baixo'] ?? '1')) === '1',
            'paymentPix' => ((string)($settings['pagamento_pix'] ?? '1')) === '1',
            'paymentCash' => ((string)($settings['pagamento_dinheiro'] ?? '1')) === '1',
            'paymentCredit' => ((string)($settings['pagamento_credito'] ?? '1')) === '1',
            'paymentDebit' => ((string)($settings['pagamento_debito'] ?? '1')) === '1',
            'paymentAccount' => ((string)($settings['pagamento_conta_cliente'] ?? '1')) === '1',
            'paymentMixed' => ((string)($settings['pagamento_misto'] ?? '1')) === '1',
            'allowDiscount' => ((string)($settings['permitir_desconto'] ?? '1')) === '1',
            'discountLimitPercent' => (int)($settings['limite_desconto_percentual'] ?? 0),
            'requireCustomerForAccount' => ((string)($settings['exigir_cliente_conta'] ?? '1')) === '1',
            'requireCancellationReason' => ((string)($settings['exigir_motivo_cancelamento'] ?? '1')) === '1',
            'auditLogEnabled' => ((string)($settings['auditoria_ativa'] ?? '1')) === '1',
            'confirmDeletes' => ((string)($settings['confirmar_exclusoes'] ?? '1')) === '1',
            'operatorPinEnabled' => ((string)($settings['pin_operador_ativo'] ?? '0')) === '1',
            'notificationsEnabled' => ((string)($settings['notificacoes_ativas'] ?? '1')) === '1',
        ];
    }

    private function formatUser(array $user): array
    {
        return [
            'id' => (int)$user['id'],
            'name' => $user['nome'],
            'email' => $user['email'],
            'role' => ucfirst((string)$user['nivel']),
            'roleKey' => $user['nivel'],
            'status' => ((int)$user['ativo'] === 1) ? 'Ativo' : 'Inativo',
        ];
    }

    private function boolValue(mixed $value): string
    {
        return $this->toBool($value) ? '1' : '0';
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
