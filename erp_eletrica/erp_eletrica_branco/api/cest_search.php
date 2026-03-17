<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado.']);
    exit;
}

$search = strtolower(trim($_GET['search'] ?? ''));

if (strlen($search) < 2) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

// Base local de CEST - Tabela oficial conforme Convênio ICMS 92/2015 e atualizações
// Foco em materiais elétricos, construção civil, e uso geral
$cest = [
    // Segmento 01 - Autopeças
    ['codigo' => '0100100', 'descricao' => 'Acumuladores elétricos de chumbo do tipo usado para arranque de motores de pistão', 'ncm_ref' => '85071000'],
    ['codigo' => '0100200', 'descricao' => 'Outros acumuladores elétricos de chumbo', 'ncm_ref' => '85072000'],
    
    // Segmento 10 - Energia Elétrica (materiais de instalação)
    ['codigo' => '1000100', 'descricao' => 'Fios, cabos e outros condutores elétricos isolados para tensões não superiores a 1.000v', 'ncm_ref' => '85444200'],
    ['codigo' => '1000200', 'descricao' => 'Fios, cabos e outros condutores elétricos isolados para tensões superiores a 1.000v', 'ncm_ref' => '85444900'],
    ['codigo' => '1000300', 'descricao' => 'Fios de cobre isolados para bobinagem', 'ncm_ref' => '85441100'],
    ['codigo' => '1000400', 'descricao' => 'Plugues, tomadas, interruptores e outros dispositivos elétricos', 'ncm_ref' => '85365000'],
    ['codigo' => '1000500', 'descricao' => 'Disjuntores para tensão não superior a 1.000v', 'ncm_ref' => '85362000'],

    // Segmento 17 - Materiais de construção e congêneres
    ['codigo' => '1700100', 'descricao' => 'Cimento Portland, mesmo colorido, mesmo clínquer de cimento Portland', 'ncm_ref' => '25231000'],
    ['codigo' => '1700200', 'descricao' => 'Cimento branco, mesmo artificial', 'ncm_ref' => '25232000'],
    ['codigo' => '1700300', 'descricao' => 'Outros cimentos hidráulicos', 'ncm_ref' => '25239000'],
    ['codigo' => '1700900', 'descricao' => 'Tubos e perfis ocos, de ferro fundido, ferro ou aço', 'ncm_ref' => '73040000'],
    ['codigo' => '1701000', 'descricao' => 'Acessórios para tubos (por ex., uniões, cotovelos, mangas/luvas), de ferro fundido, ferro ou aço', 'ncm_ref' => '73070000'],
    ['codigo' => '1701100', 'descricao' => 'Torneiras, válvulas e dispositivos semelhantes', 'ncm_ref' => '84818000'],
    ['codigo' => '1701200', 'descricao' => 'Aparelhos de ar condicionado com capacidade de refrigeração inferior ou igual a 30.000 frigorias/hora', 'ncm_ref' => '84151000'],
    ['codigo' => '1701300', 'descricao' => 'Outros aparelhos de ar condicionado', 'ncm_ref' => '84159000'],
    ['codigo' => '1701400', 'descricao' => 'Fios e cabos elétricos para instalações de baixa tensão', 'ncm_ref' => '85444200'],
    ['codigo' => '1701500', 'descricao' => 'Eletrodutos de PVC rígido rosqueável', 'ncm_ref' => '39173200'],
    ['codigo' => '1701600', 'descricao' => 'Eletrodutos de PVC rígido corrugado', 'ncm_ref' => '39172300'],
    ['codigo' => '1701700', 'descricao' => 'Eletrodutos de aço carbono', 'ncm_ref' => '73063000'],
    ['codigo' => '1701800', 'descricao' => 'Caixas de passagem, de embutir ou de sobrepor, de PVC ou metal', 'ncm_ref' => '39259000'],
    ['codigo' => '1701900', 'descricao' => 'Interruptores, tomadas, plugues e similares para instalação fixa', 'ncm_ref' => '85365000'],
    ['codigo' => '1702000', 'descricao' => 'Disjuntores, fusíveis e outros dispositivos de proteção para circuitos elétricos', 'ncm_ref' => '85361000'],
    ['codigo' => '1702100', 'descricao' => 'Quadros de distribuição elétrica, painéis elétricos e semelhantes', 'ncm_ref' => '85371000'],
    ['codigo' => '1702200', 'descricao' => 'Luminárias, lâmpadas e outros dispositivos de iluminação', 'ncm_ref' => '94052000'],
    ['codigo' => '1702300', 'descricao' => 'Suportes, buchas, abraçadeiras e acessórios para eletrodutos', 'ncm_ref' => '73269000'],
    ['codigo' => '1702400', 'descricao' => 'Conduletes e caixinhas de passagem plástica', 'ncm_ref' => '39269000'],
    ['codigo' => '1702500', 'descricao' => 'Calhas e perfilados para instalação elétrica', 'ncm_ref' => '73089000'],
    ['codigo' => '1702600', 'descricao' => 'Bandejas e leitos para cabos', 'ncm_ref' => '73089000'],
    ['codigo' => '1702700', 'descricao' => 'Registros, válvulas e similares para instalação hidráulica', 'ncm_ref' => '84818000'],
    ['codigo' => '1702800', 'descricao' => 'Tubos de PVC rígido e conexões para instalação hidráulica', 'ncm_ref' => '39172100'],
    ['codigo' => '1702900', 'descricao' => 'Tubos de cobre e conexões para instalação hidráulica', 'ncm_ref' => '74112100'],
    ['codigo' => '1703000', 'descricao' => 'Fitas isolantes e de vedação para instalações elétricas', 'ncm_ref' => '39191000'],
    ['codigo' => '1703100', 'descricao' => 'Caixas e quadros de força/distribuição elétrica, prontos para instalação', 'ncm_ref' => '85371000'],
    ['codigo' => '1703200', 'descricao' => 'Relés, contactores e interruptores automáticos para proteção de circuitos elétricos', 'ncm_ref' => '85364900'],
    ['codigo' => '1703300', 'descricao' => 'Cabos de energia, armados ou não, para distribuição e transmissão', 'ncm_ref' => '85444900'],
    ['codigo' => '1703400', 'descricao' => 'Transformadores elétricos de potência', 'ncm_ref' => '85042100'],
    ['codigo' => '1703500', 'descricao' => 'Capacitores e bancos de capacitores elétricos', 'ncm_ref' => '85321000'],
    
    // Segmento 21 - Produtos eletrônicos, eletroeletrônicos e eletrodomésticos
    ['codigo' => '2100100', 'descricao' => 'Aparelhos receptores de televisão', 'ncm_ref' => '85287200'],
    ['codigo' => '2100200', 'descricao' => 'Monitores de vídeo', 'ncm_ref' => '85285200'],
    ['codigo' => '2100300', 'descricao' => 'Aparelhos celulares e smartphones', 'ncm_ref' => '85171400'],
    ['codigo' => '2100400', 'descricao' => 'Computadores portáteis (notebooks, tablets)', 'ncm_ref' => '84713000'],
    ['codigo' => '2100500', 'descricao' => 'Máquinas automáticas para processamento de dados', 'ncm_ref' => '84713000'],
    ['codigo' => '2100600', 'descricao' => 'Impressoras, copiadoras e fax', 'ncm_ref' => '84433200'],
    ['codigo' => '2100700', 'descricao' => 'Roteadores, switches e hubs de rede', 'ncm_ref' => '85176200'],
    ['codigo' => '2100800', 'descricao' => 'Câmeras fotográficas digitais e filmadoras', 'ncm_ref' => '85258000'],
    ['codigo' => '2100900', 'descricao' => 'Aparelhos de som, caixas acústicas e amplificadores', 'ncm_ref' => '85182100'],
    ['codigo' => '2101000', 'descricao' => 'Refrigeradores domésticos e combinados', 'ncm_ref' => '84181000'],
    ['codigo' => '2101100', 'descricao' => 'Máquinas de lavar roupa', 'ncm_ref' => '84501100'],
    ['codigo' => '2101200', 'descricao' => 'Fogões domésticos e fornos', 'ncm_ref' => '73211100'],
    ['codigo' => '2101300', 'descricao' => 'Micro-ondas', 'ncm_ref' => '85166000'],
    ['codigo' => '2101400', 'descricao' => 'Pequenos aparelhos eletrodomésticos (liquidificadores, batedeiras, etc.)', 'ncm_ref' => '85094000'],
    ['codigo' => '2101500', 'descricao' => 'Aparelhos de barbear e escovas de dente elétrica', 'ncm_ref' => '85106000'],
    ['codigo' => '2101600', 'descricao' => 'Secadores de cabelo e chapinhas', 'ncm_ref' => '85166000'],
    ['codigo' => '2101700', 'descricao' => 'Lâmpadas LED e luminárias', 'ncm_ref' => '85395000'],
    ['codigo' => '2101800', 'descricao' => 'Projetores e retroprojetores', 'ncm_ref' => '85286200'],
    ['codigo' => '2101900', 'descricao' => 'GPS e outros dispositivos de navegação', 'ncm_ref' => '85269200'],
    ['codigo' => '2102000', 'descricao' => 'Consoles e jogos eletrônicos', 'ncm_ref' => '95043000'],
    ['codigo' => '2102100', 'descricao' => 'Carregadores e fontes de alimentação para celulares e dispositivos', 'ncm_ref' => '85044000'],
    ['codigo' => '2102200', 'descricao' => 'Cabos HDMI, USB, e outros cabos de dados/sinal', 'ncm_ref' => '85444200'],
    ['codigo' => '2102300', 'descricao' => 'Pen drives, cartões de memória e HDs externos', 'ncm_ref' => '84717010'],
    ['codigo' => '2102400', 'descricao' => 'Motores elétricos de baixa tensão (até 37,5W)', 'ncm_ref' => '85011000'],
    ['codigo' => '2102500', 'descricao' => 'Motores elétricos de potência superior a 37,5W', 'ncm_ref' => '85012000'],
    ['codigo' => '2102600', 'descricao' => 'Geradores e grupos eletrogêneos', 'ncm_ref' => '85016400'],
    ['codigo' => '2102700', 'descricao' => 'Inversores de frequência (VFD) e soft-starters', 'ncm_ref' => '85044000'],
    ['codigo' => '2102800', 'descricao' => 'Nobreaks (UPS) e estabilizadores de tensão', 'ncm_ref' => '85044000'],
    ['codigo' => '2102900', 'descricao' => 'Medidores de consumo elétrico, amperímetros, voltímetros e multímetros', 'ncm_ref' => '90302000'],

    // Segmento 23 - Ferramentas
    ['codigo' => '2300100', 'descricao' => 'Ferramentas de mão (chaves de fenda, alicates, etc.)', 'ncm_ref' => '82050000'],
    ['codigo' => '2300200', 'descricao' => 'Ferramentas elétricas portáteis (furadeiras, parafusadeiras)', 'ncm_ref' => '84672100'],
    ['codigo' => '2300300', 'descricao' => 'Serras elétricas portáteis', 'ncm_ref' => '84672200'],
    ['codigo' => '2300400', 'descricao' => 'Equipamentos de soldagem elétrica', 'ncm_ref' => '85151100'],
    ['codigo' => '2300500', 'descricao' => 'Alicates de crimpar, stripper e ferramentas especiais p/ elétrica', 'ncm_ref' => '82060000'],
    ['codigo' => '2300600', 'descricao' => 'Fitas métricas, esquadros e instrumentos de medição', 'ncm_ref' => '90262000'],
];

// Filter by search term (code or description)
$results = array_values(array_filter($cest, function($item) use ($search) {
    return str_contains(strtolower($item['descricao']), $search)
        || str_contains($item['codigo'], $search)
        || str_contains(strtolower($item['ncm_ref']), $search);
}));

// Limit results
$results = array_slice($results, 0, 20);

echo json_encode($results, JSON_UNESCAPED_UNICODE);
