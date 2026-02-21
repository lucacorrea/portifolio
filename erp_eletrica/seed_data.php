<?php
/**
 * ERP Elétrica - Database Seeder
 * Populates the system with dummy retail data for testing and demonstration.
 */

require_once 'config.php';

$db = \App\Config\Database::getInstance()->getConnection();

echo "Starting seeding process...\n";

// 1. Seed Categories & Products
$categories = ['Fios e Cabos', 'Iluminação', 'Disjuntores', 'Tomadas e Interruptores', 'Eletrodutos', 'Ferramentas'];
$products_data = [
    'Fios e Cabos' => [
        ['Cabo Flexível 2,5mm Azul 100m', 189.90, 245.00],
        ['Cabo Flexível 4,0mm Preto 100m', 295.00, 380.00],
        ['Cabo Flexível 6,0mm Verde 100m', 420.00, 550.00],
        ['Cabo PP 2x1,5mm 50m', 150.00, 210.00]
    ],
    'Iluminação' => [
        ['Lâmpada LED 9W Branca Bivolt', 8.50, 15.90],
        ['Painel LED 18W Quadrado Embutir', 22.00, 45.00],
        ['Refletor LED 50W SMD Preto', 45.00, 89.00],
        ['Fita LED 5050 RGB 5m', 35.00, 75.00]
    ],
    'Disjuntores' => [
        ['Disjuntor Monopolar 20A DIN', 12.00, 22.50],
        ['Disjuntor Bipolar 40A DIN', 45.00, 85.00],
        ['Disjuntor Tripolar 63A DIN', 95.00, 165.00],
        ['IDR Tetrapolar 40A 30mA', 180.00, 290.00]
    ],
    'Tomadas e Interruptores' => [
        ['Conjunto Tomada 10A Branco', 7.50, 14.90],
        ['Interruptor Simples 1 Tecla', 6.80, 12.50],
        ['Conjunto 2 Tomadas + 1 Interruptor', 15.00, 28.00],
        ['Módulo Tomada USB 2A', 35.00, 65.00]
    ],
    'Ferramentas' => [
        ['Alicate Amperímetro Digital ET-3200', 120.00, 210.00],
        ['Alicate Universal 8 Pol Isolado', 35.00, 65.00],
        ['Multímetro Digital Profissional', 85.00, 145.00],
        ['Passa Fio Profissional 20m', 22.00, 45.00]
    ]
];

foreach ($products_data as $cat => $items) {
    foreach ($items as $item) {
        $sku = 'EL-' . rand(1000, 9999);
        $stmt = $db->prepare("INSERT IGNORE INTO produtos (codigo, nome, categoria, preco_custo, preco_venda, quantidade, estoque_minimo, unidade, filial_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sku, $item[0], $cat, $item[1], $item[2], rand(30, 200), 10, 'UN', 1]);
        echo "Inserted product: {$item[0]}\n";
    }
}

// 2. Seed Clients
$clientes = [
    ['João Instalador Ltda', 'João Silva', '12.345.678/0001-90', 'fisica'],
    ['Construtora Alfa', 'Carlos Ramos', '22.333.444/0001-55', 'juridica'],
    ['Maria Souza Eletro', 'Maria Souza', '33.222.111/0001-00', 'juridica'],
    ['Condomínio Solaris', 'Síndico José', '44.555.666/0001-88', 'juridica'],
    ['Pedro Eletricista Autônomo', 'Pedro Santos', '123.456.789-00', 'fisica']
];

foreach ($clientes as $c) {
    $stmt = $db->prepare("INSERT IGNORE INTO clientes (nome, contato_nome, cpf_cnpj, tipo, email, telefone, filial_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$c[0], $c[1], $c[2], $c[3], strtolower(str_replace(' ', '.', $c[1])) . "@exemplo.com", "(11) 98888-7777", 1]);
    echo "Inserted client: {$c[0]}\n";
}

// 3. Seed Suppliers
$fornecedores = [
    ['Weg Equipamentos Elétricos', 'contato@weg.net', '01.234.567/0001-11'],
    ['Schneider Electric Brasil', 'suporte@schneider.com.br', '02.345.678/0001-22'],
    ['Prysmian Group - Cabos', 'vendas@prysmian.com', '03.456.789/0001-33'],
    ['Tigre Conexões', 'atendimento@tigre.com.br', '04.567.890/0001-44']
];

foreach ($fornecedores as $f) {
    $stmt = $db->prepare("INSERT IGNORE INTO fornecedores (nome_fantasia, email, cnpj) VALUES (?, ?, ?)");
    $stmt->execute([$f[0], $f[1], $f[2]]);
    echo "Inserted supplier: {$f[0]}\n";
}

echo "\nSeeding completed successfully! 🚀\n";
if (php_sapi_name() !== 'cli') {
    echo "<br><a href='index.php'>Voltar ao Sistema</a>";
}
