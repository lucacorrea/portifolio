<?php

declare(strict_types=1);

function weeklyPanelAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$page = file_get_contents(dirname(__DIR__) . '/pages/painel-semanal.php');
$script = file_get_contents(dirname(__DIR__) . '/assets/js/painel-semanal.js');
$styles = file_get_contents(dirname(__DIR__) . '/assets/css/dashboard.css');
$detailsAction = file_get_contents(dirname(__DIR__) . '/actions/os-detalhes.php');

foreach ([$page, $script, $styles, $detailsAction] as $source) {
    weeklyPanelAssert(is_string($source), 'Arquivos do painel semanal devem ser legíveis.');
}

weeklyPanelAssert(str_contains($page, "can('os.visualizar')"), 'Detalhes completos devem respeitar a permissão de visualizar OS.');
weeklyPanelAssert(str_contains($page, 'week-service-header'), 'Card semanal deve ter cabeçalho compacto.');
weeklyPanelAssert(str_contains($page, 'week-service-footer'), 'Card semanal deve ter rodapé compacto.');
weeklyPanelAssert((bool) preg_match('/record-actions-source[\s\S]*?js-week-details[\s\S]*?<\/ul>/', $page), 'Ver detalhes deve ficar dentro da modal de ações do card.');
weeklyPanelAssert(str_contains($page, "can('os.excluir')"), 'Exclusão de OS deve respeitar sua permissão específica.');
weeklyPanelAssert((bool) preg_match('/record-actions-source[\s\S]*?js-week-delete[\s\S]*?<\/ul>/', $page), 'Excluir OS deve ficar dentro da modal de ações do card.');
weeklyPanelAssert(str_contains($page, "\$order->status() !== 'finalizada'"), 'OS finalizada não deve oferecer exclusão antes do estorno.');
weeklyPanelAssert(str_contains($page, 'action="actions/os-excluir.php"'), 'Exclusão semanal deve reutilizar a ação auditada de OS.');
weeklyPanelAssert(!str_contains($page, 'week-delete-reason') && !str_contains($page, 'name="motivo"'), 'Exclusão semanal não deve exigir motivo.');
weeklyPanelAssert(str_contains($page, 'modal-week-details'), 'Painel deve manter detalhes em modal sem sair da página.');
weeklyPanelAssert(str_contains($page, 'data-record-actions'), 'Card deve preservar o diálogo global de ações.');
weeklyPanelAssert(str_contains($page, 'table-action-dropdown'), 'Card deve preservar o dropdown de ações padronizado.');

weeklyPanelAssert(str_contains($script, "event.target.closest?.('.js-week-details"), 'Detalhes devem usar evento delegado após filtros AJAX.');
weeklyPanelAssert(str_contains($script, "fetch('actions/os-detalhes.php?id='"), 'Modal deve carregar a OS pelo endpoint autorizado.');
weeklyPanelAssert(str_contains($script, 'request !== detailRequest'), 'Resposta antiga não pode sobrescrever detalhes de outro card.');
weeklyPanelAssert(str_contains($script, "openOrder.removeAttribute('href')"), 'Link para abrir a OS deve ser neutralizado enquanto os detalhes carregam.');
weeklyPanelAssert(str_contains($script, "button.classList.contains('js-week-delete')"), 'Exclusão semanal deve preencher sua modal por evento delegado.');
weeklyPanelAssert(!str_contains($script, '.innerHTML'), 'Detalhes da OS não podem renderizar dados com innerHTML.');

foreach (['week-service-footer', 'week-details-team', 'week-details-loading'] as $className) {
    weeklyPanelAssert(str_contains($styles, '.' . $className), 'Estilo obrigatório ausente: ' . $className);
}

foreach (['client_phone', 'client_whatsapp', 'client_address', 'client_city', 'created_at', 'updated_at'] as $field) {
    weeklyPanelAssert(str_contains($detailsAction, "'" . $field . "'"), 'Detalhe completo deve fornecer o campo ' . $field . '.');
}
weeklyPanelAssert(str_contains($detailsAction, 'Cache-Control: no-store'), 'Resposta com dados pessoais não deve ser armazenada em cache.');
weeklyPanelAssert(str_contains($detailsAction, 'X-Content-Type-Options: nosniff'), 'Resposta JSON deve impedir interpretação de conteúdo indevida.');

echo "WeeklyPanelUiTest: OK\n";
