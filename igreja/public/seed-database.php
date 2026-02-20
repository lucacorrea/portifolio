<?php
/**
 * Script para popular o banco de dados com dados de exemplo
 * Execute uma única vez para adicionar membros de teste
 */

require_once 'database.php';

// Dados de exemplo para teste
$membros_exemplo = [
    [
        'nome_completo' => 'João Silva Santos',
        'data_nascimento' => '1985-03-15',
        'nacionalidade' => 'Brasileira',
        'naturalidade' => 'Manaus',
        'estado_uf' => 'AM',
        'sexo' => 'M',
        'tipo_sanguineo' => 'O+',
        'escolaridade' => 'Ensino Médio',
        'profissao' => 'Engenheiro',
        'rg' => '1234567',
        'cpf' => '12345678901',
        'titulo_eleitor' => '123456789',
        'ctp' => '',
        'cdi' => '',
        'filiacao_pai' => 'José Silva',
        'filiacao_mae' => 'Maria Santos',
        'estado_civil' => 'Casado',
        'conjuge' => 'Ana Silva',
        'filhos' => 2,
        'endereco_rua' => 'Avenida Joanico',
        'endereco_numero' => '195',
        'endereco_bairro' => 'Urucu',
        'endereco_cep' => '69460000',
        'endereco_cidade' => 'Coari',
        'endereco_uf' => 'AM',
        'telefone' => '92999999999',
        'tipo_integracao' => 'Batismo',
        'data_integracao' => '2023-01-15',
        'batismo_aguas' => '2023-01-15',
        'batismo_espirito_santo' => '2023-01-22',
        'procedencia' => 'Igreja Evangélica',
        'congregacao' => 'Urucu',
        'area' => 'Administrativa',
        'nucleo' => 'Centro'
    ],
    [
        'nome_completo' => 'Maria Oliveira Costa',
        'data_nascimento' => '1990-07-22',
        'nacionalidade' => 'Brasileira',
        'naturalidade' => 'Manaus',
        'estado_uf' => 'AM',
        'sexo' => 'F',
        'tipo_sanguineo' => 'A+',
        'escolaridade' => 'Ensino Superior',
        'profissao' => 'Professora',
        'rg' => '2345678',
        'cpf' => '23456789012',
        'titulo_eleitor' => '234567890',
        'ctp' => '',
        'cdi' => '',
        'filiacao_pai' => 'Carlos Oliveira',
        'filiacao_mae' => 'Francisca Costa',
        'estado_civil' => 'Solteira',
        'conjuge' => '',
        'filhos' => 0,
        'endereco_rua' => 'Rua das Flores',
        'endereco_numero' => '456',
        'endereco_bairro' => 'Centro',
        'endereco_cep' => '69460100',
        'endereco_cidade' => 'Coari',
        'endereco_uf' => 'AM',
        'telefone' => '92988888888',
        'tipo_integracao' => 'Mudança',
        'data_integracao' => '2023-06-10',
        'batismo_aguas' => '2020-05-20',
        'batismo_espirito_santo' => '2020-06-10',
        'procedencia' => 'Igreja Assembléia de Deus',
        'congregacao' => 'Centro',
        'area' => 'Educação',
        'nucleo' => 'Norte'
    ],
    [
        'nome_completo' => 'Pedro Ferreira Lima',
        'data_nascimento' => '1978-11-08',
        'nacionalidade' => 'Brasileira',
        'naturalidade' => 'Belém',
        'estado_uf' => 'PA',
        'sexo' => 'M',
        'tipo_sanguineo' => 'B+',
        'escolaridade' => 'Ensino Médio',
        'profissao' => 'Comerciante',
        'rg' => '3456789',
        'cpf' => '34567890123',
        'titulo_eleitor' => '345678901',
        'ctp' => '',
        'cdi' => '',
        'filiacao_pai' => 'Antonio Ferreira',
        'filiacao_mae' => 'Rosa Lima',
        'estado_civil' => 'Divorciado',
        'conjuge' => '',
        'filhos' => 1,
        'endereco_rua' => 'Rua Principal',
        'endereco_numero' => '789',
        'endereco_bairro' => 'Bairro Novo',
        'endereco_cep' => '69460200',
        'endereco_cidade' => 'Coari',
        'endereco_uf' => 'AM',
        'telefone' => '92987777777',
        'tipo_integracao' => 'Aclamação',
        'data_integracao' => '2022-09-03',
        'batismo_aguas' => '2015-03-10',
        'batismo_espirito_santo' => '2015-04-05',
        'procedencia' => 'Igreja Batista',
        'congregacao' => 'Bairro Novo',
        'area' => 'Comercial',
        'nucleo' => 'Leste'
    ],
    [
        'nome_completo' => 'Ana Paula Mendes',
        'data_nascimento' => '1995-02-14',
        'nacionalidade' => 'Brasileira',
        'naturalidade' => 'Manaus',
        'estado_uf' => 'AM',
        'sexo' => 'F',
        'tipo_sanguineo' => 'AB+',
        'escolaridade' => 'Ensino Superior',
        'profissao' => 'Enfermeira',
        'rg' => '4567890',
        'cpf' => '45678901234',
        'titulo_eleitor' => '456789012',
        'ctp' => '',
        'cdi' => '',
        'filiacao_pai' => 'Roberto Mendes',
        'filiacao_mae' => 'Lucia Silva',
        'estado_civil' => 'Casada',
        'conjuge' => 'Carlos Mendes',
        'filhos' => 1,
        'endereco_rua' => 'Avenida Brasil',
        'endereco_numero' => '321',
        'endereco_bairro' => 'Urucu',
        'endereco_cep' => '69460150',
        'endereco_cidade' => 'Coari',
        'endereco_uf' => 'AM',
        'telefone' => '92986666666',
        'tipo_integracao' => 'Batismo',
        'data_integracao' => '2024-01-20',
        'batismo_aguas' => '2024-01-20',
        'batismo_espirito_santo' => '2024-02-10',
        'procedencia' => 'Sem religião',
        'congregacao' => 'Urucu',
        'area' => 'Saúde',
        'nucleo' => 'Oeste'
    ],
    [
        'nome_completo' => 'Lucas Rodrigues Alves',
        'data_nascimento' => '2000-05-30',
        'nacionalidade' => 'Brasileira',
        'naturalidade' => 'Manaus',
        'estado_uf' => 'AM',
        'sexo' => 'M',
        'tipo_sanguineo' => 'O-',
        'escolaridade' => 'Ensino Superior Incompleto',
        'profissao' => 'Estudante',
        'rg' => '5678901',
        'cpf' => '56789012345',
        'titulo_eleitor' => '567890123',
        'ctp' => '',
        'cdi' => '',
        'filiacao_pai' => 'Marcos Rodrigues',
        'filiacao_mae' => 'Juliana Alves',
        'estado_civil' => 'Solteiro',
        'conjuge' => '',
        'filhos' => 0,
        'endereco_rua' => 'Rua da Paz',
        'endereco_numero' => '654',
        'endereco_bairro' => 'Vila Nova',
        'endereco_cep' => '69460300',
        'endereco_cidade' => 'Coari',
        'endereco_uf' => 'AM',
        'telefone' => '92985555555',
        'tipo_integracao' => 'Batismo',
        'data_integracao' => '2023-08-12',
        'batismo_aguas' => '2023-08-12',
        'batismo_espirito_santo' => '2023-09-02',
        'procedencia' => 'Criado na Igreja',
        'congregacao' => 'Vila Nova',
        'area' => 'Juventude',
        'nucleo' => 'Sul'
    ]
];

try {
    // Verificar se já existem dados
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM membros");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        echo "⚠️ Banco de dados já contém " . $result['total'] . " membro(s). Nenhum dado foi adicionado.\n";
        exit;
    }

    // Inserir dados de exemplo
    $stmt = $pdo->prepare("
        INSERT INTO membros (
            nome_completo, data_nascimento, nacionalidade, naturalidade, estado_uf,
            sexo, tipo_sanguineo, escolaridade, profissao, rg, cpf, titulo_eleitor,
            ctp, cdi, filiacao_pai, filiacao_mae, estado_civil, conjuge, filhos,
            endereco_rua, endereco_numero, endereco_bairro, endereco_cep,
            endereco_cidade, endereco_uf, telefone, tipo_integracao,
            data_integracao, batismo_aguas, batismo_espirito_santo,
            procedencia, congregacao, area, nucleo
        ) VALUES (
            :nome_completo, :data_nascimento, :nacionalidade, :naturalidade, :estado_uf,
            :sexo, :tipo_sanguineo, :escolaridade, :profissao, :rg, :cpf, :titulo_eleitor,
            :ctp, :cdi, :filiacao_pai, :filiacao_mae, :estado_civil, :conjuge, :filhos,
            :endereco_rua, :endereco_numero, :endereco_bairro, :endereco_cep,
            :endereco_cidade, :endereco_uf, :telefone, :tipo_integracao,
            :data_integracao, :batismo_aguas, :batismo_espirito_santo,
            :procedencia, :congregacao, :area, :nucleo
        )
    ");

    $inseridos = 0;
    foreach ($membros_exemplo as $membro) {
        $stmt->execute($membro);
        $inseridos++;
    }

    echo "✅ Sucesso! " . $inseridos . " membro(s) de exemplo foram adicionados ao banco de dados.\n\n";
    
    // Mostrar estatísticas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM membros");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Total de membros no banco: " . $result['total'] . "\n";
    
    // Mostrar distribuição por tipo de integração
    $stmt = $pdo->query("
        SELECT tipo_integracao, COUNT(*) as quantidade 
        FROM membros 
        GROUP BY tipo_integracao 
        ORDER BY quantidade DESC
    ");
    
    echo "\n📈 Distribuição por Tipo de Integração:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['tipo_integracao'] . ": " . $row['quantidade'] . "\n";
    }
    
    // Mostrar distribuição por sexo
    $stmt = $pdo->query("
        SELECT sexo, COUNT(*) as quantidade 
        FROM membros 
        GROUP BY sexo 
        ORDER BY quantidade DESC
    ");
    
    echo "\n👥 Distribuição por Sexo:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sexo = $row['sexo'] === 'M' ? 'Masculino' : 'Feminino';
        echo "  - " . $sexo . ": " . $row['quantidade'] . "\n";
    }

} catch (PDOException $e) {
    echo "❌ Erro ao inserir dados: " . $e->getMessage() . "\n";
    exit(1);
}
?>
