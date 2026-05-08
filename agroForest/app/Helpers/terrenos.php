<?php

function terrenos_lista(): array
{
    return [
        [
            'codigo' => 'TER-2026-001',
            'cliente' => 'Carlos Henrique',
            'documento' => '123.456.789-00',
            'telefone' => '(92) 99999-1020',
            'email' => 'carlos@email.com',
            'proprietario' => 'Carlos Henrique',
            'nome_imovel' => 'Sítio Castanheira',
            'tipo' => 'Privado',
            'uso' => 'Manejo agroflorestal',
            'tipologia' => 'Área rural produtiva',
            'status' => 'Georreferenciado',
            'endereco' => 'Ramal Castanheira, km 12',
            'bairro' => 'Zona Rural',
            'municipio' => 'Manaus',
            'uf' => 'AM',
            'zona_utm' => '20M',
            'datum' => 'SIRGAS 2000',
            'area_hectares' => 18.42,
            'perimetro_metros' => 1850.7,
            'matricula' => 'MAT-45872',
            'car' => 'AM-1302603-9A7B.C3D4.E5F6',
            'ccir' => '950.123.456.789-0',
            'data_cadastro' => '05/05/2026',
            'observacoes' => 'Área com vegetação preservada e uso agroflorestal em acompanhamento.',
            'confrontantes' => [
                ['lado' => 'Frente', 'nome' => 'Ramal Castanheira', 'distancia' => '420,50 m'],
                ['lado' => 'Fundo', 'nome' => 'Reserva Particular Boa Vista', 'distancia' => '418,30 m'],
                ['lado' => 'Direita', 'nome' => 'José Almeida', 'distancia' => '506,20 m'],
                ['lado' => 'Esquerda', 'nome' => 'Igarapé São Pedro', 'distancia' => '505,70 m'],
            ],
            'coordenadas' => [
                ['ponto' => 'P1', 'easting' => '827431.55', 'northing' => '9662184.10', 'latitude' => '-3.054910', 'longitude' => '-60.011430', 'x' => 68, 'y' => 72],
                ['ponto' => 'P2', 'easting' => '827852.05', 'northing' => '9662178.42', 'latitude' => '-3.054962', 'longitude' => '-60.007640', 'x' => 46, 'y' => 86],
                ['ponto' => 'P3', 'easting' => '827864.77', 'northing' => '9661672.90', 'latitude' => '-3.059533', 'longitude' => '-60.007525', 'x' => 34, 'y' => 40],
                ['ponto' => 'P4', 'easting' => '827429.38', 'northing' => '9661680.36', 'latitude' => '-3.059465', 'longitude' => '-60.011446', 'x' => 52, 'y' => 18],
                ['ponto' => 'P5', 'easting' => '827640.12', 'northing' => '9661928.54', 'latitude' => '-3.057218', 'longitude' => '-60.009549', 'x' => 62, 'y' => 52],
            ],
        ],
        [
            'codigo' => 'TER-2026-002',
            'cliente' => 'Fernanda Martins',
            'documento' => '987.654.321-00',
            'telefone' => '(92) 99123-4088',
            'email' => 'fernanda@email.com',
            'proprietario' => 'Fernanda Martins',
            'nome_imovel' => 'Lote Rio Verde',
            'tipo' => 'Privado',
            'uso' => 'Regularização ambiental',
            'tipologia' => 'Lote rural',
            'status' => 'Em conferência',
            'endereco' => 'Estrada do Rio Verde, lote 24',
            'bairro' => 'Puraquequara',
            'municipio' => 'Manaus',
            'uf' => 'AM',
            'zona_utm' => '20M',
            'datum' => 'SIRGAS 2000',
            'area_hectares' => 9.76,
            'perimetro_metros' => 1264.9,
            'matricula' => 'MAT-66102',
            'car' => 'AM-1302603-11AA.22BB.33CC',
            'ccir' => '950.456.123.789-2',
            'data_cadastro' => '03/05/2026',
            'observacoes' => 'Aguardando conferência documental e validação dos pontos levantados em campo.',
            'confrontantes' => [
                ['lado' => 'Frente', 'nome' => 'Estrada do Rio Verde', 'distancia' => '266,40 m'],
                ['lado' => 'Fundo', 'nome' => 'Área remanescente privada', 'distancia' => '262,10 m'],
                ['lado' => 'Direita', 'nome' => 'Maria Santos', 'distancia' => '368,90 m'],
                ['lado' => 'Esquerda', 'nome' => 'Francisco Oliveira', 'distancia' => '367,50 m'],
            ],
            'coordenadas' => [
                ['ponto' => 'P1', 'easting' => '829104.33', 'northing' => '9659401.26', 'latitude' => '-3.080084', 'longitude' => '-59.996292', 'x' => 70, 'y' => 74],
                ['ponto' => 'P2', 'easting' => '829370.11', 'northing' => '9659398.70', 'latitude' => '-3.080107', 'longitude' => '-59.993901', 'x' => 50, 'y' => 86],
                ['ponto' => 'P3', 'easting' => '829377.48', 'northing' => '9659031.20', 'latitude' => '-3.083431', 'longitude' => '-59.993835', 'x' => 34, 'y' => 42],
                ['ponto' => 'P4', 'easting' => '829109.92', 'northing' => '9659034.64', 'latitude' => '-3.083400', 'longitude' => '-59.996243', 'x' => 52, 'y' => 18],
            ],
        ],
        [
            'codigo' => 'TER-2026-003',
            'cliente' => 'Ana Beatriz Costa',
            'documento' => '741.852.963-00',
            'telefone' => '(92) 98888-2451',
            'email' => 'ana.costa@email.com',
            'proprietario' => 'Ana Beatriz Costa',
            'nome_imovel' => 'Área São Francisco',
            'tipo' => 'Privado',
            'uso' => 'Cadastro para licenciamento',
            'tipologia' => 'Área de expansão rural',
            'status' => 'Pendente',
            'endereco' => 'Comunidade São Francisco, gleba 7',
            'bairro' => 'Tarumã',
            'municipio' => 'Manaus',
            'uf' => 'AM',
            'zona_utm' => '20M',
            'datum' => 'SIRGAS 2000',
            'area_hectares' => 5.31,
            'perimetro_metros' => 942.4,
            'matricula' => 'Em análise',
            'car' => 'Pendente',
            'ccir' => 'Pendente',
            'data_cadastro' => '30/04/2026',
            'observacoes' => 'Cadastro inicial aberto pela recepção, pendente de documentação fundiária.',
            'confrontantes' => [
                ['lado' => 'Frente', 'nome' => 'Via comunitária', 'distancia' => '180,00 m'],
                ['lado' => 'Fundo', 'nome' => 'Área de mata preservada', 'distancia' => '178,80 m'],
                ['lado' => 'Direita', 'nome' => 'Lote 08', 'distancia' => '291,20 m'],
                ['lado' => 'Esquerda', 'nome' => 'Lote 06', 'distancia' => '292,40 m'],
            ],
            'coordenadas' => [
                ['ponto' => 'P1', 'easting' => '825742.20', 'northing' => '9660324.80', 'latitude' => '-3.071715', 'longitude' => '-60.026546', 'x' => 69, 'y' => 72],
                ['ponto' => 'P2', 'easting' => '825921.66', 'northing' => '9660322.40', 'latitude' => '-3.071737', 'longitude' => '-60.024932', 'x' => 48, 'y' => 86],
                ['ponto' => 'P3', 'easting' => '825928.18', 'northing' => '9660031.28', 'latitude' => '-3.074371', 'longitude' => '-60.024873', 'x' => 34, 'y' => 40],
                ['ponto' => 'P4', 'easting' => '825739.94', 'northing' => '9660035.10', 'latitude' => '-3.074336', 'longitude' => '-60.026567', 'x' => 52, 'y' => 18],
            ],
        ],
    ];
}

function terreno_buscar_por_codigo(string $codigo): ?array
{
    foreach (terrenos_lista() as $terreno) {
        if (($terreno['codigo'] ?? '') === $codigo) {
            return $terreno;
        }
    }

    return null;
}

function terreno_status_classe(string $status): string
{
    return match ($status) {
        'Georreferenciado' => 'ok',
        'Pendente' => 'pending',
        'Irregular' => 'high',
        default => 'progress',
    };
}

function terreno_area_formatada(float $hectares): string
{
    return number_format($hectares, 2, ',', '.') . ' ha';
}

function terreno_medida_formatada(float $metros): string
{
    return number_format($metros, 2, ',', '.') . ' m';
}

function terreno_url(string $area, string $pagina, ?string $codigo = null): string
{
    $url = route_url($area, $pagina);

    if ($codigo !== null) {
        $url .= '&codigo=' . urlencode($codigo);
    }

    return $url;
}

function terrenos_indicadores(array $terrenos): array
{
    $indicadores = [
        'total' => count($terrenos),
        'georreferenciados' => 0,
        'pendentes' => 0,
        'area_total' => 0.0,
    ];

    foreach ($terrenos as $terreno) {
        $indicadores['area_total'] += (float) ($terreno['area_hectares'] ?? 0);

        if (($terreno['status'] ?? '') === 'Georreferenciado') {
            $indicadores['georreferenciados']++;
        }

        if (in_array($terreno['status'] ?? '', ['Pendente', 'Em conferência'], true)) {
            $indicadores['pendentes']++;
        }
    }

    return $indicadores;
}

function terreno_croqui_points(array $coordenadas): string
{
    $points = [];

    foreach ($coordenadas as $coordenada) {
        $points[] = sprintf('%s,%s', (float) ($coordenada['x'] ?? 50), (float) ($coordenada['y'] ?? 50));
    }

    return implode(' ', $points);
}
