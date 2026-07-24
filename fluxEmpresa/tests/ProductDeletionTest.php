<?php

declare(strict_types=1);

function productDeletionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$page = file_get_contents($root . '/pages/produtos.php');
$action = file_get_contents($root . '/actions/produto-excluir.php');
$service = file_get_contents($root . '/src/Catalog/Service/ProductManagementService.php');
$repository = file_get_contents($root . '/src/Catalog/Repository/ProductRepository.php');
$budgetRepository = file_get_contents($root . '/src/Sales/Repository/BudgetRepository.php');
$orderService = file_get_contents($root . '/src/ServiceOrder/Service/ServiceOrderManagementService.php');
$inventory = file_get_contents($root . '/src/Inventory/Service/InventoryManagementService.php');
$pointOfSale = file_get_contents($root . '/src/Finance/Service/PointOfSaleOperations.php');
$dashboard = file_get_contents($root . '/src/Dashboard/Repository/DashboardRepository.php');
$fiscal = file_get_contents($root . '/src/Fiscal/Repository/FiscalConfigurationRepository.php');

foreach ([$page, $action, $service, $repository, $budgetRepository, $orderService, $inventory, $pointOfSale, $dashboard, $fiscal] as $source) {
    productDeletionAssert(is_string($source), 'Arquivos da exclusão de produtos devem ser legíveis.');
}

productDeletionAssert(str_contains($page, "can('produto.excluir')"), 'Interface deve respeitar a permissão produto.excluir.');
productDeletionAssert((bool) preg_match('/dropdown-menu[\s\S]*?js-product-delete[\s\S]*?<\/ul>/', $page), 'Excluir produto deve ficar dentro da modal global de ações.');
productDeletionAssert(str_contains($page, 'action="actions/produto-excluir.php"'), 'Modal deve enviar para a ação de exclusão.');
productDeletionAssert(!str_contains($page, 'delete-product-reason') && !str_contains($page, 'name="motivo"'), 'Exclusão de produto não deve exigir motivo.');
productDeletionAssert(str_contains($action, "product_action_context('produto.excluir')"), 'Servidor deve exigir permissão de exclusão.');
productDeletionAssert(str_contains($action, 'product_require_post_request()'), 'Exclusão deve aceitar somente POST.');
productDeletionAssert(str_contains($service, 'deleteProduct'), 'Serviço deve expor exclusão lógica validada.');
productDeletionAssert(str_contains($repository, 'SELECT id, estoque, excluido_em') && str_contains($repository, 'FOR UPDATE'), 'Saldo deve ser validado com bloqueio transacional.');
productDeletionAssert(str_contains($repository, 'findByIdForUpdate'), 'OS deve poder bloquear o produto durante a validação.');
productDeletionAssert(str_contains($orderService, 'findByIdForUpdate'), 'Gravação de OS deve serializar o uso do produto com a exclusão.');
productDeletionAssert(str_contains($budgetRepository, 'lockProductReferences') && str_contains($budgetRepository, 'FOR UPDATE'), 'Gravação de orçamento deve serializar o uso do produto com a exclusão.');
productDeletionAssert(str_contains($repository, 'Produto com saldo não pode ser excluído'), 'Produto com saldo não pode ser excluído.');
productDeletionAssert(str_contains($repository, 'hasOperationalHistory'), 'Exclusão deve verificar vínculos históricos e operacionais.');
foreach (['ordem_servico_itens', 'orcamento_itens', 'estoque_autorizacoes', 'estoque_movimentacoes', 'venda_avulsa_itens'] as $historyTable) {
    productDeletionAssert(str_contains($repository, 'FROM ' . $historyTable), 'Exclusão deve bloquear vínculo na tabela ' . $historyTable . '.');
}
productDeletionAssert(str_contains($repository, 'excluido_em = CURRENT_TIMESTAMP'), 'Exclusão deve preservar auditoria temporal.');
productDeletionAssert(!str_contains(strtoupper($repository), 'DELETE FROM PRODUTOS'), 'Produto nunca deve ser apagado fisicamente.');
productDeletionAssert(str_contains($repository, "\$sql = 'SELECT COUNT(*) FROM produtos WHERE codigo_barras = :barcode'"), 'Código de barras excluído deve continuar reservado no histórico.');

foreach ([$inventory, $pointOfSale, $dashboard, $fiscal] as $operationalQuery) {
    productDeletionAssert(str_contains($operationalQuery, 'excluido_em IS NULL'), 'Consultas operacionais não podem reutilizar produto excluído.');
}

productDeletionAssert(str_contains($inventory, 'JOIN produtos produto ON produto.id = movimento.produto_id'), 'Estorno deve continuar encontrando produtos excluídos.');

echo "ProductDeletionTest: OK\n";
