<?php

declare(strict_types=1);

function weeklyPanelAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$page = file_get_contents(dirname(__DIR__) . '/pages/painel-semanal.php');
$script = file_get_contents(dirname(__DIR__) . '/assets/js/painel-semanal.js');
$styles = file_get_contents(dirname(__DIR__) . '/assets/css/dashboard.css');

foreach ([$page, $script, $styles] as $source) {
    weeklyPanelAssert(is_string($source), 'Arquivos do painel semanal devem ser legíveis.');
}

$compactPage = preg_replace('/\s+/', '', $page);
weeklyPanelAssert(is_string($compactPage), 'Página do painel semanal deve permitir inspeção estrutural.');

weeklyPanelAssert(str_contains($compactPage, "can('os.visualizar')"), 'Detalhes completos devem respeitar a permissão de visualizar OS.');
weeklyPanelAssert(str_contains($page, 'weekly-card-header'), 'Card semanal deve ter cabeçalho compacto.');
weeklyPanelAssert(str_contains($page, 'weekly-card-footer'), 'Card semanal deve ter rodapé compacto.');
weeklyPanelAssert(str_contains($page, '<?php if ($canViewOrder): ?>'), 'Link da OS deve depender da permissão calculada.');
weeklyPanelAssert(str_contains($page, 'ordens-servico.php?search='), 'Card deve abrir a OS correspondente sem expor uma listagem irrestrita.');
weeklyPanelAssert(str_contains($page, 'weekly-planning-card is-order'), 'OS deve ser distinguida do planejamento ainda não confirmado.');

weeklyPanelAssert(str_contains($script, "'.js-weekly-confirm'"), 'Confirmação do planejamento deve ser ligada pelo controlador atual.');
weeklyPanelAssert(str_contains($script, "recoveryModal === 'confirm'"), 'Falha de confirmação deve restaurar a modal correta.');

$styleSources = $page . "\n" . $styles;
foreach (['weekly-planning-card', 'weekly-card-footer', 'weekly-card-client'] as $className) {
    weeklyPanelAssert(str_contains($styleSources, '.' . $className), 'Estilo obrigatório ausente: ' . $className);
}

echo "WeeklyPanelUiTest: OK\n";
