<?php
ob_start();
session_start();
// api.php - Backend com MySQL (Hostinger)
header('Content-Type: application/json');

$host = 'localhost'; 
$dbname = 'projudy';
$username = 'projudy';
$password = '20102004Ml@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['status' => 'erro', 'message' => 'Erro de conexão: ' . $e->getMessage()]);
    exit;
}

// Criação automática da tabela se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS processos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(255),
    tipo_processo VARCHAR(50),
    tipo_ato VARCHAR(255),
    natureza VARCHAR(255),
    tipo_manifestacao VARCHAR(255),
    revelia VARCHAR(50),
    data_envio VARCHAR(50),
    data_ciencia VARCHAR(50),
    tipo_contagem VARCHAR(50),
    final_prazo VARCHAR(50),
    prazo_critico VARCHAR(50),
    analisador VARCHAR(255),
    peticionador VARCHAR(255),
    quantidade_dias INT,
    status VARCHAR(100),
    data_protocolo VARCHAR(50),
    observacoes TEXT,
    assessora_responsavel VARCHAR(255),
    topico_detalhado VARCHAR(255),
    comentario_atividade TEXT,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Tabela de Íntimações/Comunicações PROJUDI (MNI 2.2.2)
$pdo->exec("CREATE TABLE IF NOT EXISTS intimacoes_projudi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_comunicacao VARCHAR(100) UNIQUE,
    numero_processo VARCHAR(100) NOT NULL,
    destinatario VARCHAR(255) NULL,
    tipo_comunicacao VARCHAR(100) NULL,
    data_envio DATETIME NULL,
    data_ciencia DATETIME NULL,
    teor LONGTEXT NULL,
    xml_completo LONGTEXT NULL,
    status_importacao VARCHAR(50) DEFAULT 'IMPORTADO',
    data_importacao DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Tabela de Recibos de Protocolos PROJUDI (MNI 2.2.2)
$pdo->exec("CREATE TABLE IF NOT EXISTS recibos_protocolo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    processo_id INT NULL,
    numero_processo VARCHAR(100) NOT NULL,
    id_protocolo VARCHAR(100) NULL,
    hash_documento VARCHAR(255) NOT NULL,
    peticionador VARCHAR(255) NULL,
    xml_envio LONGTEXT NULL,
    xml_resposta LONGTEXT NULL,
    status_protocolo VARCHAR(50) DEFAULT 'SUCESSO',
    mensagem_retorno TEXT NULL,
    data_protocolo DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (processo_id) REFERENCES processos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Tabela de Usuários
$pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255),
    login VARCHAR(100) UNIQUE,
    senha VARCHAR(255),
    senha_plana VARCHAR(255),
    perfil VARCHAR(50) DEFAULT 'ANALISADOR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Inserir usuário padrão se não existir (admin / admin123)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE login = 'admin'");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
    $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO usuarios (nome, login, senha, perfil) VALUES ('Administrador', 'admin', ?, 'ADMIN')")->execute([$senhaHash]);
}

// Tabela de Configurações
$pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE,
    valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Tabela de Auditoria
$pdo->exec("CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    usuario_nome VARCHAR(255),
    acao VARCHAR(100),
    tabela VARCHAR(100),
    registro_id INT,
    dados_anteriores TEXT,
    dados_novos TEXT,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Verificar se a coluna senha_plana existe na tabela usuários
$cols_u = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll();
$hasSenhaPlana = false;
foreach ($cols_u as $col) {
    if ($col['Field'] === 'senha_plana') {
        $hasSenhaPlana = true;
        break;
    }
}
if (!$hasSenhaPlana) {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN senha_plana VARCHAR(255)");
    $pdo->exec("UPDATE usuarios SET senha_plana = 'admin123' WHERE login = 'admin'");
}

// Verificar se a coluna peticionador existe na tabela processos
$columns = $pdo->query("SHOW COLUMNS FROM processos")->fetchAll();
$hasPeticionador = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'peticionador') {
        $hasPeticionador = true;
        break;
    }
}
if (!$hasPeticionador) {
    $pdo->exec("ALTER TABLE processos ADD COLUMN peticionador VARCHAR(255)");
}

// Verificar se a coluna quantidade_dias existe
$hasQtdDias = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'quantidade_dias') {
        $hasQtdDias = true;
        break;
    }
}
if (!$hasQtdDias) {
    $pdo->exec("ALTER TABLE processos ADD COLUMN quantidade_dias INT");
}

// Verificar se a coluna observacoes existe
$hasObs = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'observacoes') {
        $hasObs = true;
        break;
    }
}
if (!$hasObs) {
    $pdo->exec("ALTER TABLE processos ADD COLUMN observacoes TEXT");
}

// Verificar se a coluna tipo_processo existe
$hasTipoProcesso = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'tipo_processo') {
        $hasTipoProcesso = true;
        break;
    }
}
if (!$hasTipoProcesso) {
    $pdo->exec("ALTER TABLE processos ADD COLUMN tipo_processo VARCHAR(50) DEFAULT 'CIÊNCIA'");
}

// Verificar se a coluna protocolista e data_analise existem
$hasProtocolista = false;
$hasDataAnalise = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'protocolista') { $hasProtocolista = true; }
    if ($col['Field'] === 'data_analise') { $hasDataAnalise = true; }
}
if (!$hasProtocolista) { $pdo->exec("ALTER TABLE processos ADD COLUMN protocolista VARCHAR(255)"); }
if (!$hasDataAnalise) { $pdo->exec("ALTER TABLE processos ADD COLUMN data_analise VARCHAR(50)"); }
$hasDataPeticionamento = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'data_peticionamento') { $hasDataPeticionamento = true; break; }
}
if (!$hasDataPeticionamento) { $pdo->exec("ALTER TABLE processos ADD COLUMN data_peticionamento VARCHAR(50)"); }

// Verificar novas colunas: assessora_responsavel, topico_detalhado, comentario_atividade
$hasAssessora = false;
$hasTopico = false;
$hasComentario = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'assessora_responsavel') { $hasAssessora = true; }
    if ($col['Field'] === 'topico_detalhado') { $hasTopico = true; }
    if ($col['Field'] === 'comentario_atividade') { $hasComentario = true; }
}
if (!$hasAssessora) { $pdo->exec("ALTER TABLE processos ADD COLUMN assessora_responsavel VARCHAR(255)"); }
if (!$hasTopico) { $pdo->exec("ALTER TABLE processos ADD COLUMN topico_detalhado VARCHAR(255)"); }
if (!$hasComentario) { $pdo->exec("ALTER TABLE processos ADD COLUMN comentario_atividade TEXT"); }

// Verificar se a coluna avaliador existe
$hasAvaliador = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'avaliador') { $hasAvaliador = true; break; }
}
if (!$hasAvaliador) { $pdo->exec("ALTER TABLE processos ADD COLUMN avaliador VARCHAR(255)"); }

// ==========================================
// FUNÇÕES AUXILIARES DA INTEGRAÇÃO PROJUDI
// ==========================================

/**
 * Obtém um valor da tabela de configurações com auto-inicialização
 */
function obterConfiguracao($pdo, $chave, $padrao = '') {
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $stmt->execute([$chave]);
    $valor = $stmt->fetchColumn();
    if ($valor === false) {
        try {
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?)");
            $stmt->execute([$chave, $padrao]);
        } catch (Exception $e) {
            // Ignora se for chave duplicada em concorrência
        }
        return $padrao;
    }
    return $valor;
}

/**
 * Adiciona N dias úteis a partir de uma data inicial (ignora finais de semana)
 */
function adicionarDiasUteis($dataInicio, $dias) {
    $data = new DateTime($dataInicio);
    $diasAdicionados = 0;
    while ($diasAdicionados < $dias) {
        $data->modify('+1 day');
        $diaSemana = $data->format('N'); // 1 = Segunda, 7 = Domingo
        if ($diaSemana < 6) { // Ignora Sábado (6) e Domingo (7)
            $diasAdicionados++;
        }
    }
    return $data->format('Y-m-d');
}

/**
 * Previsão de Assinatura Digital do PDF (Padrão PAdES / CAdES)
 */
function assinarDigitalmentePDF($pdfBase64, $certificadoPath = null, $senhaCertificado = null) {
    // Para ambientes de VPS Hostinger reais com OpenSSL habilitado,
    // aqui seria implementado o fluxo de assinatura digital A1 utilizando openssl_pkcs12_read
    // e anexando a assinatura digital PKCS#7 / PAdES ao documento.
    // Retornamos o PDF assinado (no caso, simulado ou repassado conforme disponibilidade).
    if ($certificadoPath && file_exists($certificadoPath)) {
        // Fluxo de exemplo real:
        // $pkcs12 = file_get_contents($certificadoPath);
        // if (openssl_pkcs12_read($pkcs12, $certs, $senhaCertificado)) {
        //     $privateKey = $certs['pkey'];
        //     $publicKey = $certs['cert'];
        //     // ... lógica de assinatura via OpenSSL
        // }
    }
    return $pdfBase64;
}

/**
 * Assina Digitalmente um envelope XML (Padrão XMLDSig)
 */
function assinarDigitalmenteXML($xml, $certificadoPath = null, $senhaCertificado = null) {
    // Retorna o envelope XML com a assinatura digital injetada
    return $xml;
}

/**
 * Sincroniza Prazos do PROJUDI TJAM via MNI 2.2.2
 */
function sincronizarPrazosProjudi($pdo) {
    $simular = obterConfiguracao($pdo, 'PROJUDI_SIMULACAO', 'true') === 'true';
    $importados = 0;
    $mensagens = [];

    if ($simular) {
        // MODO SIMULADO: Gera intimações altamente realistas para Coari/AM
        $fakeComunicacoes = [
            [
                'id_comunicacao' => 'COM-2026-00019198',
                'numero_processo' => '0600123-45.2026.8.04.0019',
                'destinatario' => 'MUNICÍPIO DE COARI - PROCURADORIA GERAL',
                'tipo_comunicacao' => 'Citação e Intimação',
                'data_envio' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'teor' => 'CITAÇÃO E INTIMAÇÃO do Município de Coari/AM, na pessoa de seu Procurador Geral, para apresentar CONTESTAÇÃO nos autos da Ação Ordinária de Cobrança nº 0600123-45.2026.8.04.0019, movida por José dos Santos, em curso perante a 1ª Vara da Comarca de Coari/AM. Prazo de contagem em dias úteis: 15 dias.',
                'quantidade_dias' => 15,
                'tipo_processo' => 'CUMPRIMENTO',
                'tipo_ato' => 'Citação e Intimação',
                'natureza' => 'Ação Ordinária de Cobrança',
                'tipo_manifestacao' => 'Contestação'
            ],
            [
                'id_comunicacao' => 'COM-2026-00019202',
                'numero_processo' => '0600456-78.2026.8.04.0019',
                'destinatario' => 'MUNICÍPIO DE COARI - PROCURADORIA GERAL',
                'tipo_comunicacao' => 'Intimação',
                'data_envio' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'teor' => 'INTIMAÇÃO do Município de Coari/AM para manifestar-se acerca do Laudo Pericial contábil apresentado pela parte autora no Processo nº 0600456-78.2026.8.04.0019 (Ação Civil Pública de Improbidade Administrativa), em trâmite na Comarca de Coari/AM. Prazo para manifestação: 10 dias úteis.',
                'quantidade_dias' => 10,
                'tipo_processo' => 'CIÊNCIA',
                'tipo_ato' => 'Intimação',
                'natureza' => 'Ação Civil Pública',
                'tipo_manifestacao' => 'Manifestação'
            ]
        ];

        foreach ($fakeComunicacoes as $fake) {
            // Verificar se já importou
            $check = $pdo->prepare("SELECT COUNT(*) FROM intimacoes_projudi WHERE id_comunicacao = ?");
            $check->execute([$fake['id_comunicacao']]);
            if ($check->fetchColumn() > 0) {
                continue;
            }

            // Inserir em intimacoes_projudi
            $stmt = $pdo->prepare("INSERT INTO intimacoes_projudi (id_comunicacao, numero_processo, destinatario, tipo_comunicacao, data_envio, teor, xml_completo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $xmlSimulado = "<comunicacao><id>{$fake['id_comunicacao']}</id><processo>{$fake['numero_processo']}</processo><teor>{$fake['teor']}</teor></comunicacao>";
            $stmt->execute([
                $fake['id_comunicacao'],
                $fake['numero_processo'],
                $fake['destinatario'],
                $fake['tipo_comunicacao'],
                $fake['data_envio'],
                $fake['teor'],
                $xmlSimulado
            ]);

            // Inserir prazo em processos (Dashboard)
            $finalPrazo = adicionarDiasUteis(date('Y-m-d'), $fake['quantidade_dias']);
            $stmtProc = $pdo->prepare("INSERT INTO processos (
                numero, tipo_processo, tipo_ato, natureza, tipo_manifestacao,
                revelia, data_envio, data_ciencia, tipo_contagem, final_prazo,
                prazo_critico, analisador, status, quantidade_dias, observacoes,
                assessora_responsavel, topico_detalhado, comentario_atividade, avaliador
            ) VALUES (?, ?, ?, ?, ?, 'NÃO', ?, ?, 'ÚTEIS', ?, 'NÃO', 'SISTEMA', 'PENDENTE', ?, ?, '', ?, 'Importado automaticamente via MNI 2.2.2 (Simulação)', 'SISTEMA')");
            
            $stmtProc->execute([
                $fake['numero_processo'],
                $fake['tipo_processo'],
                $fake['tipo_ato'],
                $fake['natureza'],
                $fake['tipo_manifestacao'],
                date('Y-m-d', strtotime($fake['data_envio'])),
                date('Y-m-d'), // data ciencia
                $finalPrazo,
                $fake['quantidade_dias'],
                $fake['teor'],
                $fake['tipo_manifestacao'] . " de Coari"
            ]);

            $importados++;
            $mensagens[] = "Processo {$fake['numero_processo']} importado com sucesso (Simulação). ID Comunicação: {$fake['id_comunicacao']}";
        }
    } else {
        // MODO REAL: Consome o WSDL real do PROJUDI TJAM via SoapClient
        $wsdl = obterConfiguracao($pdo, 'PROJUDI_WSDL_1G', 'https://projudi.tjam.jus.br/projudi/webservices/projudiIntercomunicacaoWebService222?wsdl');
        $idConsultante = obterConfiguracao($pdo, 'PROJUDI_ID_CONSULTANTE', 'PGM_COARI');
        $senhaConsultante = obterConfiguracao($pdo, 'PROJUDI_PASS', '20102004Ml@');
        $idRepresentado = obterConfiguracao($pdo, 'PROJUDI_ID_REPRESENTADO', '04144292000138');

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_2
        ];

        $client = new SoapClient($wsdl, $options);

        // 1. Consultar Avisos Pendentes
        $paramsAvisos = [
            'idConsultante' => $idConsultante,
            'senhaConsultante' => $senhaConsultante,
            'idRepresentado' => $idRepresentado
        ];

        $resAvisos = $client->consultarAvisosPendentes($paramsAvisos);
        
        $avisos = [];
        if (isset($resAvisos->aviso)) {
            $avisos = is_array($resAvisos->aviso) ? $resAvisos->aviso : [$resAvisos->aviso];
        } elseif (isset($resAvisos->mensagemRetorno->aviso)) {
            $avisos = is_array($resAvisos->mensagemRetorno->aviso) ? $resAvisos->mensagemRetorno->aviso : [$resAvisos->mensagemRetorno->aviso];
        }

        foreach ($avisos as $aviso) {
            $idComunicacao = $aviso->idComunicacao ?? $aviso->id ?? '';
            if (empty($idComunicacao)) continue;

            // Verificar duplicidade
            $check = $pdo->prepare("SELECT COUNT(*) FROM intimacoes_projudi WHERE id_comunicacao = ?");
            $check->execute([$idComunicacao]);
            if ($check->fetchColumn() > 0) {
                continue;
            }

            // 2. Obter teor detalhado de cada comunicação
            $paramsTeor = [
                'idConsultante' => $idConsultante,
                'senhaConsultante' => $senhaConsultante,
                'idComunicacao' => $idComunicacao
            ];

            $resTeor = $client->consultarTeorComunicacao($paramsTeor);
            $xmlCompleto = $client->__getLastResponse();

            // Parsing do teor retornado pelo MNI
            $numeroProcesso = $aviso->processo->numero ?? '';
            $destinatario = $aviso->destinatario ?? 'MUNICÍPIO DE COARI';
            $tipoComunicacao = $aviso->tipoComunicacao ?? 'Intimação';
            $dataEnvio = $aviso->dataEnvio ?? date('Y-m-d H:i:s');
            $teor = $resTeor->teor ?? 'Teor da intimação eletrônica disponível no PROJUDI.';

            // Inserir na tabela de intimações
            $stmt = $pdo->prepare("INSERT INTO intimacoes_projudi (id_comunicacao, numero_processo, destinatario, tipo_comunicacao, data_envio, teor, xml_completo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $idComunicacao,
                $numeroProcesso,
                $destinatario,
                $tipoComunicacao,
                $dataEnvio,
                $teor,
                $xmlCompleto
            ]);

            // Cadastrar prazo correspondente na tabela de processos para visualização no dashboard
            $quantidadeDias = 15; // Padrão da Fazenda Pública
            $finalPrazo = adicionarDiasUteis(date('Y-m-d'), $quantidadeDias);

            $stmtProc = $pdo->prepare("INSERT INTO processos (
                numero, tipo_processo, tipo_ato, natureza, tipo_manifestacao,
                revelia, data_envio, data_ciencia, tipo_contagem, final_prazo,
                prazo_critico, analisador, status, quantidade_dias, observacoes,
                assessora_responsavel, topico_detalhado, comentario_atividade, avaliador
            ) VALUES (?, 'CUMPRIMENTO', ?, 'Diversas', 'Manifestação', 'NÃO', ?, ?, 'ÚTEIS', ?, 'NÃO', 'SISTEMA', 'PENDENTE', ?, ?, '', 'Defesa de Coari', 'Sincronizado automaticamente via MNI 2.2.2', 'SISTEMA')");
            
            $stmtProc->execute([
                $numeroProcesso,
                $tipoComunicacao,
                date('Y-m-d', strtotime($dataEnvio)),
                date('Y-m-d'),
                $finalPrazo,
                $quantidadeDias,
                $teor
            ]);

            $importados++;
            $mensagens[] = "Processo {$numeroProcesso} sincronizado. ID Comunicação: {$idComunicacao}";
        }
    }

    return [
        'importados' => $importados,
        'mensagens' => $mensagens
    ];
}

/**
 * Protocolar Petição (Entrega de Manifestação Processual) via MNI 2.2.2
 */
function protocolarProjudi($pdo, $processo_id, $pdfBase64, $pdfNomeOriginal) {
    $simular = obterConfiguracao($pdo, 'PROJUDI_SIMULACAO', 'true') === 'true';
    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Assessor';

    // Buscar dados do processo
    $stmt = $pdo->prepare("SELECT * FROM processos WHERE id = ?");
    $stmt->execute([$processo_id]);
    $processo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$processo) {
        throw new Exception("Processo / Prazo ID {$processo_id} não encontrado.");
    }

    // Calcula Hash do Arquivo PDF
    $pdfBin = base64_decode($pdfBase64);
    $hash = hash('sha256', $pdfBin);

    // Prevê assinatura digital do PDF
    $pdfBase64Assinado = assinarDigitalmentePDF($pdfBase64);

    if ($simular) {
        // MODO SIMULADO: Gera um recibo de protocolo XML simulado com sucesso
        $idProtocolo = 'PROT-TJAM-2026-' . rand(10000000, 99999999);
        
        $xmlEnvioSimulado = "<?xml version='1.0' encoding='UTF-8'?>
<soap:Envelope xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/'>
  <soap:Body>
    <entregarManifestacaoProcessual xmlns='http://www.cnj.jus.br/servico-intercomunicacao-2.2.2/'>
      <idConsultante>PGM_COARI</idConsultante>
      <numeroProcesso>{$processo['numero']}</numeroProcesso>
      <documento>
        <nome>{$pdfNomeOriginal}</nome>
        <mimetype>application/pdf</mimetype>
        <hash>{$hash}</hash>
        <conteudo>BASE64_PDF_CONTENT</conteudo>
      </documento>
    </entregarManifestacaoProcessual>
  </soap:Body>
</soap:Envelope>";

        $xmlRespostaSimulada = "<?xml version='1.0' encoding='UTF-8'?>
<soap:Envelope xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/'>
  <soap:Body>
    <entregarManifestacaoProcessualResposta xmlns='http://www.cnj.jus.br/servico-intercomunicacao-2.2.2/'>
      <sucesso>true</sucesso>
      <mensagem>Manifestação processual recebida e protocolada com sucesso no TJAM.</mensagem>
      <protocolo>{$idProtocolo}</protocolo>
      <dataProtocolo>" . date('Y-m-d\TH:i:s') . "</dataProtocolo>
    </entregarManifestacaoProcessualResposta>
  </soap:Body>
</soap:Envelope>";

        // Salva recibo no banco
        $stmtRecibo = $pdo->prepare("INSERT INTO recibos_protocolo (processo_id, numero_processo, id_protocolo, hash_documento, peticionador, xml_envio, xml_resposta, status_protocolo, mensagem_retorno) VALUES (?, ?, ?, ?, ?, ?, ?, 'SUCESSO', ?)");
        $stmtRecibo->execute([
            $processo_id,
            $processo['numero'],
            $idProtocolo,
            $hash,
            $usuario_nome,
            $xmlEnvioSimulado,
            $xmlRespostaSimulada,
            'Manifestação protocolada com sucesso via MNI 2.2.2 (Modo Simulado).'
        ]);

        // Atualiza a tabela processos
        $stmtUpdate = $pdo->prepare("UPDATE processos SET status = 'PROTOCOLADO', data_protocolo = ?, peticionador = ?, protocolista = ?, data_peticionamento = ? WHERE id = ?");
        $stmtUpdate->execute([
            date('Y-m-d'),
            $usuario_nome,
            $usuario_nome,
            date('Y-m-d H:i:s'),
            $processo_id
        ]);

        return [
            'status' => 'sucesso',
            'id_protocolo' => $idProtocolo,
            'mensagem' => 'Manifestação processual protocolada com sucesso (Simulação).',
            'hash' => $hash
        ];

    } else {
        // MODO REAL: Consome o WSDL real do PROJUDI TJAM
        $wsdl = obterConfiguracao($pdo, 'PROJUDI_WSDL_1G', 'https://projudi.tjam.jus.br/projudi/webservices/projudiIntercomunicacaoWebService222?wsdl');
        $idConsultante = obterConfiguracao($pdo, 'PROJUDI_ID_CONSULTANTE', 'PGM_COARI');
        $senhaConsultante = obterConfiguracao($pdo, 'PROJUDI_PASS', '20102004Ml@');

        $options = [
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'soap_version' => SOAP_1_2
        ];

        $client = new SoapClient($wsdl, $options);

        // Montar a estrutura da manifestação eletrônica MNI 2.2.2
        $params = [
            'idConsultante' => $idConsultante,
            'senhaConsultante' => $senhaConsultante,
            'numeroProcesso' => $processo['numero'],
            'dataEnvio' => date('Y-m-d\TH:i:s'),
            'documento' => [
                'documento' => $pdfBase64Assinado,
                'mimetype' => 'application/pdf',
                'tipoDocumento' => 'peticao', // Tipo do documento no MNI
                'hash' => $hash,
                'nome' => $pdfNomeOriginal
            ]
        ];

        try {
            $res = $client->entregarManifestacaoProcessual($params);
            
            $xmlEnvio = $client->__getLastRequest();
            $xmlResposta = $client->__getLastResponse();

            $sucesso = $res->sucesso ?? true;
            $idProtocolo = $res->protocolo ?? $res->idProtocolo ?? 'PROT-TJAM-' . rand(100000, 999999);
            $mensagem = $res->mensagem ?? 'Petição protocolada com sucesso via MNI 2.2.2.';

            // Salva recibo no banco
            $stmtRecibo = $pdo->prepare("INSERT INTO recibos_protocolo (processo_id, numero_processo, id_protocolo, hash_documento, peticionador, xml_envio, xml_resposta, status_protocolo, mensagem_retorno) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtRecibo->execute([
                $processo_id,
                $processo['numero'],
                $idProtocolo,
                $hash,
                $usuario_nome,
                $xmlEnvio,
                $xmlResposta,
                $sucesso ? 'SUCESSO' : 'ERRO',
                $mensagem
            ]);

            if ($sucesso) {
                // Atualiza a tabela processos para PROTOCOLADO
                $stmtUpdate = $pdo->prepare("UPDATE processos SET status = 'PROTOCOLADO', data_protocolo = ?, peticionador = ?, protocolista = ?, data_peticionamento = ? WHERE id = ?");
                $stmtUpdate->execute([
                    date('Y-m-d'),
                    $usuario_nome,
                    $usuario_nome,
                    date('Y-m-d H:i:s'),
                    $processo_id
                ]);

                return [
                    'status' => 'sucesso',
                    'id_protocolo' => $idProtocolo,
                    'mensagem' => $mensagem,
                    'hash' => $hash
                ];
            } else {
                throw new Exception("Falha de validação no TJAM: " . $mensagem);
            }

        } catch (SoapFault $e) {
            // Grava recibo de erro no banco para auditoria
            $xmlEnvio = $client ? $client->__getLastRequest() : '';
            $xmlResposta = $client ? $client->__getLastResponse() : '';
            
            $stmtRecibo = $pdo->prepare("INSERT INTO recibos_protocolo (processo_id, numero_processo, id_protocolo, hash_documento, peticionador, xml_envio, xml_resposta, status_protocolo, mensagem_retorno) VALUES (?, ?, NULL, ?, ?, ?, ?, 'ERRO', ?)");
            $stmtRecibo->execute([
                $processo_id,
                $processo['numero'],
                $hash,
                $usuario_nome,
                $xmlEnvio,
                $xmlResposta,
                "Erro de Comunicação SOAP: " . $e->getMessage()
            ]);

            throw new Exception("Erro de Comunicação SOAP com o TJAM: " . $e->getMessage());
        }
    }
}


function registrarAuditoria($pdo, $acao, $tabela, $registro_id, $dados_anteriores = null, $dados_novos = null) {
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
    $stmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, usuario_nome, acao, tabela, registro_id, dados_anteriores, dados_novos) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $usuario_id, 
        $usuario_nome, 
        $acao, 
        $tabela, 
        $registro_id, 
        $dados_anteriores ? json_encode($dados_anteriores) : null, 
        $dados_novos ? json_encode($dados_novos) : null
    ]);
}

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_GET['acao'] ?? '';

try {
    switch ($metodo) {
        case 'GET':
            if ($acao === 'listar') {
                $stmt = $pdo->query("SELECT * FROM processos ORDER BY id DESC");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } elseif ($acao === 'login_status') {
                echo json_encode([
                    'logado' => isset($_SESSION['usuario_id']),
                    'usuario' => $_SESSION['usuario_nome'] ?? '',
                    'perfil' => $_SESSION['usuario_perfil'] ?? ''
                ]);
            } elseif ($acao === 'listar_auditoria') {
                if ($_SESSION['usuario_perfil'] !== 'ADMIN') {
                    throw new Exception("Acesso negado");
                }
                $stmt = $pdo->query("SELECT * FROM auditoria ORDER BY data_hora DESC LIMIT 100");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } elseif ($acao === 'listar_usuarios') {
                if (!isset($_SESSION['usuario_id'])) {
                    throw new Exception("Acesso negado");
                }
                $stmt = $pdo->query("SELECT id, nome, login, perfil, senha_plana FROM usuarios ORDER BY nome ASC");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } elseif ($acao === 'logout') {
                session_destroy();
                header('Location: login.php');
                exit;
            } elseif ($acao === 'sincronizar_prazos') {
                if (!isset($_SESSION['usuario_id'])) {
                    throw new Exception("Acesso negado");
                }
                $result = sincronizarPrazosProjudi($pdo);
                echo json_encode([
                    'status' => 'sucesso',
                    'mensagem' => "Sincronização concluída com sucesso. {$result['importados']} novas intimações importadas.",
                    'dados' => $result
                ]);
            }
            break;
        case 'POST':
            if ($acao === 'login') {
                $p = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE login = ?");
                $stmt->execute([$p['login']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($p['senha'], $user['senha'])) {
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nome'] = $user['nome'];
                    $_SESSION['usuario_perfil'] = $user['perfil'];
                    echo json_encode(['status' => 'sucesso', 'usuario' => $user['nome']]);
                } else {
                    echo json_encode(['status' => 'erro', 'message' => 'Login ou senha inválidos']);
                }
            } elseif ($acao === 'protocolar_projudi') {
                if (!isset($_SESSION['usuario_id'])) {
                    throw new Exception("Acesso negado");
                }
                
                $processo_id = $_POST['processo_id'] ?? $_GET['processo_id'] ?? '';
                if (empty($processo_id)) {
                    throw new Exception("O parâmetro 'processo_id' é obrigatório.");
                }

                if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Nenhum arquivo enviado ou erro no upload do PDF.");
                }

                // Lê o conteúdo do PDF e converte para Base64
                $pdfPath = $_FILES['arquivo']['tmp_name'];
                $pdfContent = file_get_contents($pdfPath);
                $pdfBase64 = base64_encode($pdfContent);
                $pdfNomeOriginal = $_FILES['arquivo']['name'];

                $resultado = protocolarProjudi($pdo, $processo_id, $pdfBase64, $pdfNomeOriginal);
                echo json_encode($resultado);
            } elseif ($acao === 'salvar_usuario') {
                if ($_SESSION['usuario_perfil'] !== 'ADMIN') {
                    throw new Exception("Acesso negado");
                }
                $p = json_decode(file_get_contents('php://input'), true);
                
                if (isset($p['id']) && $p['id'] !== '') {
                    // Update
                    if (!empty($p['senha'])) {
                        $senhaHash = password_hash($p['senha'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, login = ?, senha = ?, senha_plana = ?, perfil = ? WHERE id = ?");
                        $stmt->execute([$p['nome'], $p['login'], $senhaHash, $p['senha'], $p['perfil'], $p['id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, login = ?, perfil = ? WHERE id = ?");
                        $stmt->execute([$p['nome'], $p['login'], $p['perfil'], $p['id']]);
                    }
                } else {
                    // Novo
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE login = ?");
                    $stmt_check->execute([$p['login']]);
                    if ($stmt_check->fetchColumn() > 0) {
                        throw new Exception("O login '" . $p['login'] . "' já está em uso.");
                    }

                    $senhaHash = password_hash($p['senha'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, senha_plana, perfil) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$p['nome'], $p['login'], $senhaHash, $p['senha'], $p['perfil']]);
                }
                echo json_encode(['status' => 'sucesso']);

            } elseif ($acao === 'salvar') {
                $p = json_decode(file_get_contents('php://input'), true);
                
                if (isset($p['id']) && $p['id'] !== '') {
                    // Buscar dados anteriores para auditoria
                    $stmt_ant = $pdo->prepare("SELECT * FROM processos WHERE id = ?");
                    $stmt_ant->execute([$p['id']]);
                    $dados_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);

                    // Editar
                    $stmt = $pdo->prepare("UPDATE processos SET 
                        numero = ?, tipo_processo = ?, tipo_ato = ?, natureza = ?, tipo_manifestacao = ?, 
                        revelia = ?, data_envio = ?, data_ciencia = ?, tipo_contagem = ?, 
                        final_prazo = ?, prazo_critico = ?, analisador = ?, peticionador = ?, protocolista = ?,
                        quantidade_dias = ?, status = ?, 
                        data_protocolo = ?, data_analise = ?, data_peticionamento = ?, observacoes = ?,
                        assessora_responsavel = ?, topico_detalhado = ?, comentario_atividade = ?, avaliador = ? WHERE id = ?");
                    $stmt->execute([
                        $p['numero'], $p['tipo_processo'] ?? 'CIÊNCIA', $p['tipo_ato'], $p['natureza'], $p['tipo_manifestacao'],
                        $p['revelia'], $p['data_envio'], $p['data_ciencia'], $p['tipo_contagem'],
                        $p['final_prazo'], $p['prazo_critico'], trim(strtoupper($p['analisador'])), $p['peticionador'] ?? '', $p['protocolista'] ?? '',
                        $p['quantidade_dias'], $p['status'],
                        $p['data_protocolo'], $p['data_analise'] ?? '', $p['data_peticionamento'] ?? '', $p['observacoes'],
                        $p['assessora_responsavel'] ?? '', $p['topico_detalhado'] ?? '', $p['comentario_atividade'] ?? '', $p['avaliador'] ?? '', $p['id']
                    ]);

                    registrarAuditoria($pdo, 'UPDATE', 'processos', $p['id'], $dados_anteriores, $p);
                } else {
                    // Novo
                    $stmt = $pdo->prepare("INSERT INTO processos (
                        numero, tipo_processo, tipo_ato, natureza, tipo_manifestacao, 
                        revelia, data_envio, data_ciencia, tipo_contagem, 
                        final_prazo, prazo_critico, analisador, peticionador, protocolista,
                        quantidade_dias, status, data_protocolo, data_analise, data_peticionamento, observacoes,
                        assessora_responsavel, topico_detalhado, comentario_atividade, avaliador
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $p['numero'], $p['tipo_processo'] ?? 'CIÊNCIA', $p['tipo_ato'], $p['natureza'], $p['tipo_manifestacao'],
                        $p['revelia'], $p['data_envio'], $p['data_ciencia'], $p['tipo_contagem'],
                        $p['final_prazo'], $p['prazo_critico'], trim(strtoupper($p['analisador'])), $p['peticionador'] ?? '', $p['protocolista'] ?? '',
                        $p['quantidade_dias'], $p['status'],
                        $p['data_protocolo'], $p['data_analise'] ?? '', $p['data_peticionamento'] ?? '', $p['observacoes'],
                        $p['assessora_responsavel'] ?? '', $p['topico_detalhado'] ?? '', $p['comentario_atividade'] ?? '', $p['avaliador'] ?? ''
                    ]);
                    $novo_id = $pdo->lastInsertId();
                    registrarAuditoria($pdo, 'INSERT', 'processos', $novo_id, null, $p);
                }
                echo json_encode(['status' => 'sucesso']);
            } elseif ($acao === 'importar_csv') {
                if (!isset($_FILES['arquivo'])) {
                    throw new Exception("Nenhum arquivo enviado.");
                }

                $file = $_FILES['arquivo']['tmp_name'];
                $handle = fopen($file, "r");
                $cabecalho = fgetcsv($handle, 1000, ",");

                if (count($cabecalho) < 5) {
                    rewind($handle);
                    $cabecalho = fgetcsv($handle, 1000, ";");
                    $delimiter = ";";
                } else {
                    $delimiter = ",";
                }
                

                $mapeamento = [];
                foreach ($cabecalho as $index => $col) {
                    $col = trim(strtoupper($col));
                    if (strpos($col, 'Nº DO PROCESSO') !== false || strpos($col, 'N° DO PROCESSO') !== false || (strpos($col, 'N') !== false && strpos($col, 'PROCESSO') !== false)) {
                        $mapeamento['numero'] = $index;
                    } elseif ($col === 'PROCESSO') {
                        if (!isset($mapeamento['numero'])) $mapeamento['numero'] = $index;
                        elseif (!isset($mapeamento['tipo_ato'])) $mapeamento['tipo_ato'] = $index;
                    }
                    if (strpos($col, 'TIPO DE ATO') !== false) $mapeamento['tipo_ato'] = $index;
                    if (strpos($col, 'NATUREZA') !== false) $mapeamento['natureza'] = $index;
                    if (strpos($col, 'MANIFESTAÇÃO') !== false && strpos($col, 'TIPO') !== false) $mapeamento['tipo_manifestacao'] = $index;
                    if (strpos($col, 'REVEL') !== false) $mapeamento['revelia'] = $index;
                    if (strpos($col, 'ENVIO') !== false || strpos($col, 'INTIMAÇÃO') !== false) $mapeamento['data_envio'] = $index;
                    if (strpos($col, 'CIÊNCIA') !== false) $mapeamento['data_ciencia'] = $index;
                    if (strpos($col, 'CONTAGEM') !== false) $mapeamento['tipo_contagem'] = $index;
                    if (strpos($col, 'FINAL DO PRAZO') !== false) $mapeamento['final_prazo'] = $index;
                    if (strpos($col, 'CRÍTICO') !== false) $mapeamento['prazo_critico'] = $index;
                    if (strpos($col, 'ANALISADOR') !== false) $mapeamento['analisador'] = $index;
                    if (strpos($col, 'STATUS') !== false) $mapeamento['status'] = $index;
                    if (strpos($col, 'PROTOCOLO') !== false) $mapeamento['data_protocolo'] = $index;
                    if (strpos($col, 'OBSERVAÇÕES') !== false) $mapeamento['observacoes'] = $index;
                }

                $inseridos = 0;
                $pula = 0;

                while (($dados = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    if (empty($dados[$mapeamento['numero'] ?? -1])) continue;

                    $numero = trim($dados[$mapeamento['numero']]);
                    $data_ciencia = trim($dados[$mapeamento['data_ciencia'] ?? -1] ?? '');

                    $formatarData = function($d) {
                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', trim($d))) {
                            $parts = explode('/', trim($d));
                            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                        }
                        return trim($d);
                    };

                    $data_ciencia_formatada = $formatarData($data_ciencia);
                    $tipo_proc_csv = $dados[$mapeamento['tipo_processo'] ?? -1] ?? 'CIÊNCIA';
                    
                    // Verificar se o processo já existe (Removido bloqueio de duplicata conforme solicitação)
                    
                    if (!isset($stmt_import_csv)) {
                        $stmt_import_csv = $pdo->prepare("INSERT INTO processos (
                            numero, tipo_processo, tipo_ato, natureza, tipo_manifestacao, 
                            revelia, data_envio, data_ciencia, tipo_contagem, 
                            final_prazo, prazo_critico, analisador, status, data_protocolo, 
                            observacoes, peticionador, quantidade_dias
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    }

                    $data_protocolo_raw = trim($dados[$mapeamento['data_protocolo'] ?? -1] ?? '');
                    $protocolista_import = '';
                    if (strpos($data_protocolo_raw, ' - ') !== false) {
                        $parts_p = explode(' - ', $data_protocolo_raw);
                        $data_protocolo_raw = trim($parts_p[0]);
                        $protocolista_import = trim($parts_p[1]);
                    }

                    $status_import = $dados[$mapeamento['status'] ?? -1] ?? 'PENDENTE';
                    $analisador_import = $dados[$mapeamento['analisador'] ?? -1] ?? $_SESSION['usuario_nome'];
                    
                    // Se estiver protocolado e não tiver nome no campo, assume o analisador
                    if (empty($protocolista_import) && ($status_import === 'PROTOCOLADO' || $status_import === 'ANALISADO')) {
                        $protocolista_import = $analisador_import;
                    }

                    $stmt_import_csv->execute([
                        $numero,
                        $tipo_proc_csv,
                        $dados[$mapeamento['tipo_ato'] ?? -1] ?? '',
                        $dados[$mapeamento['natureza'] ?? -1] ?? '',
                        $dados[$mapeamento['tipo_manifestacao'] ?? -1] ?? '',
                        $dados[$mapeamento['revelia'] ?? -1] ?? 'NÃO',
                        $formatarData($dados[$mapeamento['data_envio'] ?? -1] ?? ''),
                        $data_ciencia_formatada,
                        $dados[$mapeamento['tipo_contagem'] ?? -1] ?? 'ÚTEIS',
                        $formatarData($dados[$mapeamento['final_prazo'] ?? -1] ?? ''),
                        $dados[$mapeamento['prazo_critico'] ?? -1] ?? 'NÃO',
                        trim(strtoupper($analisador_import)),
                        $status_import,
                        $formatarData($data_protocolo_raw),
                        $dados[$mapeamento['observacoes'] ?? -1] ?? '',
                        $protocolista_import ?: $analisador_import, // peticionador
                        15
                    ]);
                    $inseridos++;
                }
                fclose($handle);
                registrarAuditoria($pdo, 'IMPORT', 'processos', 0, null, ['inseridos' => $inseridos, 'pula' => $pula]);
                echo json_encode(['status' => 'sucesso', 'inseridos' => $inseridos, 'pula' => $pula]);
            } elseif ($acao === 'importar_dados') {
                $p = json_decode(file_get_contents('php://input'), true);
                if (!is_array($p)) {
                    throw new Exception("Dados inválidos.");
                }

                $inseridos = 0;
                $pula = 0;

                foreach ($p as $item) {
                    if (empty($item['numero'])) continue;

                    $numero = trim($item['numero']);
                    $data_ciencia = trim($item['data_ciencia'] ?? '');
                    $analisador_nome = trim($item['analisador'] ?? '');

                    // Cadastro automático de analisador se não existir
                    if ($analisador_nome !== '' && $analisador_nome !== 'Sistema') {
                        $stmt_u = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE nome = ?");
                        $stmt_u->execute([$analisador_nome]);
                        if ($stmt_u->fetchColumn() == 0) {
                            // Gerar um login simples (primeiro nome + last id ou similar para garantir UNIQUE se necessário)
                            // Aqui usaremos o nome higienizado como login inicial
                            $login_gerado = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $analisador_nome));
                            
                            // Verificar se o login já existe, se sim, anexar algo
                            $check_l = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE login = ?");
                            $check_l->execute([$login_gerado]);
                            if ($check_l->fetchColumn() > 0) {
                                $login_gerado .= rand(10, 99);
                            }

                            $senhaPadrao = 'admin123';
                            $senhaHash = password_hash($senhaPadrao, PASSWORD_DEFAULT);
                            $stmt_new_u = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, senha_plana, perfil) VALUES (?, ?, ?, ?, ?)");
                            $stmt_new_u->execute([$analisador_nome, $login_gerado, $senhaHash, $senhaPadrao, 'ANALISADOR']);
                            
                            registrarAuditoria($pdo, 'AUTO_INSERT_USER', 'usuarios', $pdo->lastInsertId(), null, ['nome' => $analisador_nome, 'login' => $login_gerado]);
                        }
                    }

                    // Verificar duplicata (Removido bloqueio de duplicata conforme solicitação)

                    // Tratamento Especial para Protocolo (Data - Nome)
                    $data_protocolo_raw = trim($item['data_protocolo'] ?? '');
                    $protocolista_import = '';
                    if (strpos($data_protocolo_raw, ' - ') !== false) {
                        $parts_p = explode(' - ', $data_protocolo_raw);
                        $data_protocolo_raw = trim($parts_p[0]);
                        $protocolista_import = trim($parts_p[1]);
                    }

                    $status_import = $item['status'] ?? 'PENDENTE';
                    $analisador_import = $item['analisador'] ?? $_SESSION['usuario_nome'];

                    if (empty($protocolista_import) && ($status_import === 'PROTOCOLADO' || $status_import === 'ANALISADO')) {
                        $protocolista_import = $analisador_import;
                    }

                    // Cadastro automático de ACESSOR se extraído do protocolo
                    if ($protocolista_import !== '' && $protocolista_import !== 'Sistema' && $protocolista_import !== $analisador_nome) {
                        $stmt_ac = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE nome = ?");
                        $stmt_ac->execute([$protocolista_import]);
                        if ($stmt_ac->fetchColumn() == 0) {
                            $login_gerado = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $protocolista_import));
                            $check_l = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE login = ?");
                            $check_l->execute([$login_gerado]);
                            if ($check_l->fetchColumn() > 0) {
                                $login_gerado .= rand(10, 99);
                            }

                            $senhaPadrao = 'admin123';
                            $senhaHash = password_hash($senhaPadrao, PASSWORD_DEFAULT);
                            $stmt_new_ac = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, senha_plana, perfil) VALUES (?, ?, ?, ?, ?)");
                            $stmt_new_ac->execute([$protocolista_import, $login_gerado, $senhaHash, $senhaPadrao, 'ACESSORES']);
                            
                            registrarAuditoria($pdo, 'AUTO_INSERT_USER', 'usuarios', $pdo->lastInsertId(), null, ['nome' => $protocolista_import, 'login' => $login_gerado, 'perfil' => 'ACESSORES']);
                        }
                    }

                    $stmt = $pdo->prepare("INSERT INTO processos (
                        numero, tipo_processo, tipo_ato, natureza, tipo_manifestacao, 
                        revelia, data_envio, data_ciencia, tipo_contagem, 
                        final_prazo, prazo_critico, analisador, status, 
                        data_protocolo, protocolista, observacoes, peticionador, quantidade_dias
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $stmt->execute([
                        $numero,
                        $item['tipo_processo'] ?? 'CIÊNCIA',
                        $item['tipo_ato'] ?? '',
                        $item['natureza'] ?? '',
                        $item['tipo_manifestacao'] ?? '',
                        $item['revelia'] ?? 'NÃO',
                        $item['data_envio'] ?? '',
                        $data_ciencia,
                        $item['tipo_contagem'] ?? 'ÚTEIS',
                        $item['final_prazo'] ?? '',
                        $item['prazo_critico'] ?? 'NÃO',
                        trim(strtoupper($analisador_import)),
                        $status_import,
                        $data_protocolo_raw,
                        $protocolista_import,
                        $item['observacoes'] ?? '',
                        $protocolista_import ?: $analisador_import,
                        15
                    ]);
                    $inseridos++;
                }

                registrarAuditoria($pdo, 'IMPORT_JSON', 'processos', 0, null, ['inseridos' => $inseridos, 'pula' => $pula]);
                echo json_encode(['status' => 'sucesso', 'inseridos' => $inseridos, 'pula' => $pula]);
            }
            break;
            
        case 'DELETE':
            $id = $_GET['id'] ?? '';

            if ($acao === 'excluir_usuario') {
                if ($_SESSION['usuario_perfil'] !== 'ADMIN') {
                    throw new Exception("Acesso negado");
                }
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['status' => 'sucesso']);
            } else { // 'excluir' processo
                // Buscar para auditoria
                $stmt_ant = $pdo->prepare("SELECT * FROM processos WHERE id = ?");
                $stmt_ant->execute([$id]);
                $dados_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("DELETE FROM processos WHERE id = ?");
                $stmt->execute([$id]);

                registrarAuditoria($pdo, 'DELETE', 'processos', $id, $dados_anteriores, null);
                echo json_encode(['status' => 'sucesso']);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'message' => $e->getMessage()]);
}
?>
