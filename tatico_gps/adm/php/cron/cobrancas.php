<?php
/**
 * Script de Automação de Cobrança - Tático GPS
 * Deve ser configurado no Cron Job da Hostinger para rodar uma vez por dia.
 */

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../whatsapp/functions.php';

// 1. Carregar Configurações de Automação
$stmtConfig = $pdo->query("SELECT * FROM configuracoes_automacao ORDER BY id DESC LIMIT 1");
$config = $stmtConfig->fetch();

if (!$config || !(int)$config['automacao_ativa']) {
    die("Automação desativada ou não configurada.\n");
}

// Forçar saída em tempo real no navegador
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    header('Cache-Control: no-cache');
}
ob_implicit_flush(true);
while (ob_get_level()) ob_end_flush();

$hoje = new DateTime();
$mesAtual = $hoje->format('m');
$anoAtual = $hoje->format('Y');

// 2. Buscar Clientes com Automação Ativa
$stmtClientes = $pdo->query("SELECT * FROM clientes WHERE mensagem_automatica = 1 AND status IN ('Ativo', 'Pendente', 'Bloqueado')");
$clientes = $stmtClientes->fetchAll();

echo "Processando " . count($clientes) . " clientes...\n";

foreach ($clientes as $cliente) {
    $diaVenc = (int)$cliente['dia_vencimento'];
    $telefone = $cliente['whatsapp_principal'] ?: $cliente['telefone'];
    
    if (empty($telefone)) continue;

    // Criar data de vencimento para este mês
    $vencimento = new DateTime($hoje->format("Y-m-$diaVenc"));
    
    // Calcular diferença em dias
    $intervalo = $hoje->diff($vencimento);
    $diasRestantes = (int)$intervalo->format('%R%a'); // Ex: +10, +5, 0, -7

    $regra = null;
    $mensagemBase = '';

    if ($diasRestantes === 10) {
        $regra = '10_dias_antes';
        $mensagemBase = $config['mensagem_10_dias'];
    } elseif ($diasRestantes === 5) {
        $regra = '5_dias_antes';
        $mensagemBase = $config['mensagem_5_dias'];
    } elseif ($diasRestantes === 0) {
        $regra = 'dia_vencimento';
        $mensagemBase = $config['mensagem_dia_vencimento'];
    } elseif ($diasRestantes === -(int)$config['bloquear_apos_dias']) {
        $regra = 'atraso_bloqueio';
        $mensagemBase = $config['mensagem_7_dias_atraso'];
        
        // Atualizar status do cliente se necessário
        atualizarStatusCliente($pdo, (int)$cliente['id'], $config['status_cliente_apos_bloqueio']);
    }

    if ($regra) {
        // Verificar se já enviamos esta regra este mês para este cliente
        if (!jaEnviado($pdo, (int)$cliente['id'], $regra, $mesAtual, $anoAtual)) {
            
            // Montar mensagem com variáveis
            $mensagemFinal = montarMensagem($mensagemBase, $cliente, $config, $vencimento->format('d/m/Y'));
            
            echo ">> Enviando regra '$regra' para {$cliente['nome']} ($telefone)... ";
            $retorno = enviarMensagemWhatsApp($telefone, $mensagemFinal);
            
            if ($retorno['ok']) {
                echo "SUCESSO! ✅\n";
            } else {
                echo "FALHOU: " . ($retorno['error'] ?? 'Erro desconhecido') . " ❌\n";
            }
            
            // Registrar log com a regra na resposta para controle
            $statusLog = $retorno['ok'] ? 'enviado' : 'falhou';
            $respostaLog = ($retorno['ok'] ? 'Sucesso' : ($retorno['error'] ?? 'Erro desconhecido')) . " | Regra: $regra";
            
            registrarLogEnvio($pdo, (int)$cliente['id'], $telefone, $mensagemFinal, $statusLog, $respostaLog);
        } else {
            echo "-- Pulo: {$cliente['nome']} já recebeu a regra '$regra' este mês.\n";
        }
    } else {
        echo "-- Nenhuma regra ativa hoje para {$cliente['nome']} (Vencimento dia {$diaVenc}).\n";
    }
}

/**
 * Verifica no log se a regra já foi disparada este mês
 */
function jaEnviado(PDO $pdo, int $clienteId, string $regra, string $mes, string $ano): bool {
    $busca = "%Regra: $regra%";
    $dataInicio = "$ano-$mes-01 00:00:00";
    
    $sql = "SELECT COUNT(*) FROM whatsapp_envios 
            WHERE cliente_id = :id 
            AND resposta_api LIKE :regra 
            AND data_envio >= :data";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $clienteId, ':regra' => $busca, ':data' => $dataInicio]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Substitui placeholders nas mensagens
 */
function montarMensagem(string $msg, array $cliente, array $config, string $dataVenc): string {
    return str_replace(
        ['@cliente', '@valor', '@vencimento', '@pix_nome', '@pix_chave', '@empresa'],
        [
            $cliente['nome'],
            number_format((float)$cliente['mensalidade'], 2, ',', '.'),
            $dataVenc,
            $config['pix_nome_recebedor'],
            $config['pix_chave'],
            $config['empresa_nome']
        ],
        $msg
    );
}

function atualizarStatusCliente(PDO $pdo, int $id, string $novoStatus): void {
    $stmt = $pdo->prepare("UPDATE clientes SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $novoStatus, ':id' => $id]);
}

echo "Finalizado.\n";
