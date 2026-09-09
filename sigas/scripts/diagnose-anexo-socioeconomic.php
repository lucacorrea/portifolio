<?php

declare(strict_types=1);

use App\Core\Validator;
use App\Integrations\Anexo\AnexoDatabase;
use App\Integrations\Anexo\AnexoDatabaseConfig;
use App\Integrations\Anexo\AnexoEnvironment;
use App\Integrations\Anexo\AnexoIntegrationService;
use App\Integrations\Anexo\AnexoRepository;

require_once dirname(__DIR__) . '/bootstrap.php';

$cpf = isset($argv[1]) ? Validator::onlyDigits((string) $argv[1]) : '';
if ($cpf !== '' && !Validator::cpf($cpf)) {
    fwrite(STDERR, "CPF inválido. Use: php scripts/diagnose-anexo-socioeconomic.php 00000000000\n");
    exit(2);
}

$path = AnexoEnvironment::locate();
if ($path === null) {
    fwrite(STDERR, "[FAIL] Arquivo de ambiente do ANEXO não localizado.\n");
    exit(1);
}

try {
    $environment = AnexoEnvironment::load($path);
    $config = AnexoDatabaseConfig::fromEnvironment($environment);
    if (!$config->enabled()) {
        fwrite(STDERR, "[FAIL] Integração ANEXO está desativada.\n");
        exit(1);
    }

    $database = new AnexoDatabase($config);
    $pdo = $database->connection();

    echo "=== DIAGNÓSTICO ANEXO / SEMAS — SOMENTE LEITURA ===\n";
    echo "Ambiente: localizado e legível\n";
    echo "Banco: conexão OK\n\n";

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM solicitantes')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $field = trim((string) ($column['Field'] ?? ''));
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    $expected = [
        'id','nome','cpf','nis','telefone','bairro_id','genero','estado_civil','data_nascimento',
        'nacionalidade','naturalidade','rg','rg_emissao','rg_uf','endereco','numero','complemento','referencia',
        'tempo_anos','tempo_meses','grupo_tradicional','grupo_outros','pcd','pcd_tipo','bpc','bpc_valor',
        'pbf','pbf_valor','beneficio_municipal','beneficio_municipal_valor','beneficio_estadual',
        'beneficio_estadual_valor','renda_mensal_faixa','renda_mensal_outros','trabalho','renda_individual',
        'renda_familiar','total_rendimentos','tipificacao','total_moradores','total_familias','pcd_residencia',
        'total_pcd','situacao_imovel','situacao_imovel_valor','tipo_moradia','abastecimento','iluminacao','esgoto',
        'lixo','entorno','resumo_caso','conj_nome','conj_cpf','conj_nis','conj_rg','conj_nasc','created_at',
        'updated_at','responsavel'
    ];

    $present = [];
    $missing = [];
    foreach ($expected as $field) {
        (isset($columns[$field]) ? $present : $missing)[] = $field;
    }

    echo 'Colunas da tabela solicitantes: ' . count($columns) . "\n";
    echo 'Campos socioeconômicos reconhecidos: ' . count($present) . '/' . count($expected) . "\n";
    if ($missing !== []) {
        echo '[WARN] Campos esperados ausentes nesta versão: ' . implode(', ', $missing) . "\n";
    } else {
        echo "[OK] Todos os campos socioeconômicos conhecidos estão disponíveis.\n";
    }

    if ($cpf === '') {
        echo "\nInforme um CPF para analisar preenchimento:\n";
        echo "php scripts/diagnose-anexo-socioeconomic.php 00000000000\n";
        exit(0);
    }

    $repository = new AnexoRepository($database);
    $service = new AnexoIntegrationService($repository, 'enabled');
    $payload = $service->consultCpf($cpf);

    if (empty($payload['found']) || !is_array($payload['person'] ?? null)) {
        echo "\n[WARN] CPF não localizado no ANEXO.\n";
        exit(0);
    }

    $person = $payload['person'];
    $groups = [
        'Identificação' => ['name','nis','rg','rg_issued_at','rg_state','birth_date','gender','marital_status','nationality','birthplace','phone'],
        'Endereço' => ['district','street','number','complement','reference_point','residence_years','residence_months'],
        'Renda e trabalho' => ['work_status','individual_income','family_income','total_income','income_range','income_range_other','typification'],
        'Benefícios / PCD' => ['traditional_group','disability_status','disability_type','bpc_status','bpc_value','pbf_status','pbf_value','municipal_benefit_status','municipal_benefit_value','state_benefit_status','state_benefit_value'],
        'Família' => ['members_count','families_count','household_disability_status','household_disability_count','spouse_name','spouse_nis','spouse_rg','spouse_birth_date'],
        'Habitação' => ['housing_status','housing_cost','housing_material','water_supply','lighting','sewer','trash_destination','surroundings'],
        'Registro' => ['created_by','created_at','updated_at'],
    ];

    echo "\n=== PREENCHIMENTO DO CPF " . substr($cpf, 0, 3) . '.***.***-' . substr($cpf, -2) . " ===\n";
    foreach ($groups as $label => $keys) {
        $filled = [];
        $empty = [];
        foreach ($keys as $key) {
            $value = $person[$key] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $empty[] = $key;
            } else {
                $text = trim((string) $value);
                if (str_contains($key, 'cpf')) {
                    $text = '[presente]';
                }
                $filled[] = $key . '=' . mb_substr($text, 0, 120);
            }
        }
        echo "\n{$label}:\n";
        foreach ($filled as $item) {
            echo "  [OK] {$item}\n";
        }
        if ($empty !== []) {
            echo '  [--] sem valor: ' . implode(', ', $empty) . "\n";
        }
    }

    echo "\nFamiliares na tabela familiares: " . count((array) ($payload['familiares'] ?? [])) . "\n";
    echo "Solicitações históricas: " . count((array) ($payload['solicitacoes'] ?? [])) . "\n";
    echo "Entregas históricas: " . count((array) ($payload['historico_ajudas'] ?? [])) . "\n";
    echo "\nRESULTADO: DIAGNÓSTICO CONCLUÍDO. Nenhuma escrita foi executada no ANEXO.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] Não foi possível analisar o ANEXO: ' . $exception->getMessage() . "\n");
    exit(1);
}
