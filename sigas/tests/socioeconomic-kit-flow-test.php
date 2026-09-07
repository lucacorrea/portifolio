<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function skf_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) $failures[] = $message;
}

function skf_read(string $path): string
{
    $content = file_get_contents($path);
    return is_string($content) ? $content : '';
}

$migration = skf_read($root . '/database/migrations/20260907_016_prontuario_socioeconomico_kit_maternidade.sql');
$membersMigration = skf_read($root . '/database/migrations/20260907_017_prontuario_socioeconomico_composicao_familiar.sql');
$socialService = skf_read($root . '/app/Services/SocialRegistryService.php');
$benefitService = skf_read($root . '/app/Services/BenefitRequestService.php');
$kitService = skf_read($root . '/app/Services/KitMaternityService.php');
$kitRepository = skf_read($root . '/app/Repositories/KitMaternityRepository.php');
$personRepository = skf_read($root . '/app/Repositories/PersonRegistryRepository.php');
$profileRepository = skf_read($root . '/app/Repositories/SocioeconomicRepository.php');
$screen = skf_read($root . '/prontuario-socioeconomico.php');
$kitRuntime = skf_read($root . '/frontend/modules/kit-maternidade/lib/runtime.php');
$kitLayout = skf_read($root . '/kit-maternidade/_layout.php');
$apiCommon = skf_read($root . '/api/_common.php');
$kitApi = skf_read($root . '/api/kit-maternidade/operacao.php');
$socioSaveApi = skf_read($root . '/api/socioeconomico/salvar.php');

foreach ([
    'pessoa_socioeconomico',
    'pessoa_socioeconomico_historico',
    'beneficio_solicitacoes',
    'kit_maternidade_solicitacoes',
    'kit_maternidade_acompanhamentos',
    'kit_maternidade_avaliacoes',
    'kit_maternidade_entregas',
] as $table) {
    skf_assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS ' . $table), 'migration deve criar ' . $table);
}
skf_assert(str_contains($membersMigration, 'pessoa_socioeconomico_membros'), 'composição familiar socioeconômica deve existir sem exigir CPF');
skf_assert(str_contains($membersMigration, 'nome VARCHAR(160) NOT NULL'), 'membro da entrevista deve aceitar identificação por nome');
skf_assert(!str_contains($membersMigration, 'cpf '), 'composição da entrevista não deve obrigar CPF de dependentes');

skf_assert(str_contains($migration, 'UNIQUE KEY uk_pessoa_socioeconomico_pessoa (pessoa_id)'), 'deve haver um prontuário atual por pessoa');
skf_assert(str_contains($migration, 'beneficio_codigo'), 'solicitação central deve identificar o benefício');
skf_assert(!str_contains($migration, 'UNIQUE KEY uk_beneficio_solicitacoes_pessoa'), 'pessoa não pode ser limitada a um único benefício');
skf_assert(str_contains($benefitService, 'findOpenByPersonAndCode'), 'duplicidade deve ser bloqueada somente para a mesma solicitação aberta');
skf_assert(str_contains($benefitService, "'apto', 'nao_apto', 'pendente'"), 'decisão deve ser explícita e humana');
skf_assert(str_contains($benefitService, 'O parecer/motivo da decisão é obrigatório'), 'decisão deve exigir fundamentação');

skf_assert(str_contains($socialService, 'consultCpf($cpf'), 'prontuário deve conseguir consultar o ANEXO');
skf_assert(str_contains($socialService, 'read_only_origin'), 'integração ANEXO deve declarar origem somente leitura');
skf_assert(str_contains($socialService, 'age_days'), 'prontuário deve sinalizar idade da entrevista para revisão');
skf_assert(str_contains($profileRepository, 'pessoa_socioeconomico_historico'), 'alterações socioeconômicas devem preservar histórico');
skf_assert(str_contains($profileRepository, 'replaceMembers'), 'composição familiar da entrevista deve ser versionável');

skf_assert(str_contains($personRepository, 'WHERE cpf = :cpf LIMIT 1 FOR UPDATE'), 'cadastro central deve usar CPF para evitar duplicação concorrente');
skf_assert(str_contains($personRepository, "str_contains(\$raw, ',') && str_contains(\$raw, '.')"), 'parser monetário deve distinguir formato brasileiro/decimal');

skf_assert(str_contains($kitService, 'A avaliação não pode ser concluída sem o formulário socioeconômico'), 'todas as candidatas devem possuir socioeconômico antes da decisão');
skf_assert(str_contains($kitService, "'visita', 'reuniao', 'contato', 'orientacao', 'retorno', 'outro'"), 'acompanhamento deve registrar tipos operacionais');
skf_assert(str_contains($kitService, 'gestacao_risco = 1'), 'risco identificado durante acompanhamento deve atualizar a ficha');
skf_assert(str_contains($kitService, "(string) (\$request['decisao'] ?? '') !== 'apto'"), 'entrega deve exigir decisão apta');
skf_assert(!str_contains($kitService, 'renda_per_capita <'), 'renda não pode gerar decisão automática');

foreach (['visitas', 'reunioes', 'reunioes_presentes', 'semanas_gestacao', 'responsavel_tecnico_nome', 'gestacao_risco'] as $field) {
    skf_assert(str_contains($kitRepository, $field), 'consulta do Kit deve expor ' . $field);
}

foreach (['Prontuário Socioeconômico', 'Composição familiar', 'Condições habitacionais', 'Vulnerabilidades', 'Este formulário não aprova benefício'] as $text) {
    skf_assert(str_contains($screen, $text), 'tela socioeconômica deve conter: ' . $text);
}
skf_assert(str_contains($screen, 'Usar dados do ANEXO'), 'tela deve oferecer importação/conferência do ANEXO');
skf_assert(str_contains($kitRuntime, 'visitas'), 'front do Kit deve mostrar contagem de visitas');
skf_assert(str_contains($kitRuntime, 'presencas'), 'front do Kit deve mostrar participação em reuniões');
skf_assert(str_contains($kitRuntime, 'Responsável'), 'front do Kit deve identificar responsável técnico');
skf_assert(str_contains($kitRuntime, 'Gestação de risco'), 'front do Kit deve destacar risco');
skf_assert(str_contains($kitLayout, 'kit-maternidade-flow.js'), 'layout do Kit deve carregar interação operacional real');

skf_assert(str_contains($apiCommon, 'sigas_api_require_csrf'), 'APIs operacionais devem validar CSRF');
skf_assert(str_contains($kitApi, "'kit_maternidade.avaliar'"), 'API do Kit deve separar permissão de avaliação');
skf_assert(str_contains($kitApi, "'kit_maternidade.entregar'"), 'API do Kit deve separar permissão de entrega');
skf_assert(str_contains($socioSaveApi, "'socioeconomico.editar'"), 'salvamento do prontuário deve exigir permissão própria');
skf_assert(str_contains($socioSaveApi, "'socioeconomico.importar_anexo'"), 'dados com origem ANEXO devem exigir permissão de importação');

foreach ([
    $root . '/scripts/preflight-socioeconomic-kit.php',
    $root . '/scripts/verify-socioeconomic-kit.php',
    $root . '/assets/css/prontuario-socioeconomico.css',
    $root . '/assets/js/prontuario-socioeconomico.js',
    $root . '/assets/js/modules/kit-maternidade-flow.js',
] as $requiredFile) {
    skf_assert(is_file($requiredFile), 'arquivo obrigatório ausente: ' . basename($requiredFile));
}

if ($failures === []) {
    echo 'PASS socioeconomic-kit-flow-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) echo 'FAIL: ' . $failure . PHP_EOL;
echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
