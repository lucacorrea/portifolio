<?php
namespace App\Services;

use Exception;
use DOMDocument;

class SefazConsultaService extends BaseService {
    private $db;
    private $config;
    private $ufCodes = [
        'AC'=>'12', 'AL'=>'27', 'AM'=>'13', 'AP'=>'16', 'BA'=>'29', 'CE'=>'23', 'DF'=>'53', 'ES'=>'32', 'GO'=>'52', 
        'MA'=>'21', 'MG'=>'31', 'MS'=>'50', 'MT'=>'51', 'PA'=>'15', 'PB'=>'25', 'PE'=>'26', 'PI'=>'22', 'PR'=>'41', 
        'RJ'=>'33', 'RN'=>'24', 'RO'=>'11', 'RR'=>'14', 'RS'=>'43', 'SC'=>'42', 'SE'=>'28', 'SP'=>'35', 'TO'=>'17'
    ];

    public function __construct() {
        parent::__construct();
        $this->db = \App\Config\Database::getInstance()->getConnection();
        $this->loadConfig();
    }

    private function loadConfig() {
        // Encontra os dados fiscais via NfceService (que já possui a lógica de fallback Matriz)
        $nfceService = new \App\Services\NfceService();
        
        // Busca a configuração da Matriz (principal) para ser a base global
        $stmtMatriz = $this->db->query("SELECT id FROM filiais WHERE principal = 1 LIMIT 1");
        $matriz = $stmtMatriz->fetch();
        $matrizId = $matriz['id'] ?? 1;

        $this->config = $nfceService->getConfig($matrizId);
        
        if (!$this->config['certificado_path']) throw new Exception("Certificado A1 (Global) não configurado ou não encontrado na Matriz.");
        
        // Password handling
        $this->config['certificado_senha_raw'] = $this->config['certificado_senha'] ?? '';
    }


    /**
     * Realiza a manifestação do destinatário
     * tpEvento: 210200 (Confirmacao), 210210 (Ciencia), 210220 (Desconhecimento), 210240 (Operacao nao Realizada)
     */
    public function manifestarNota($cnpj, $chave, $tpEvento = '210210') {
        // 🔎 Buscar UF da filial para o cOrgao
        $stmt = $this->db->prepare("SELECT uf FROM filiais WHERE cnpj = ? OR cnpj LIKE ? LIMIT 1");
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
        $stmt->execute([$cnpj, "%$cnpjLimpo%"]);
        $siglaUf = $stmt->fetchColumn() ?: 'SP';
        $cUF = $this->ufCodes[strtoupper($siglaUf)] ?? '91';

        $xml = $this->gerarXmlEventoManifesto($cnpj, $chave, $tpEvento, $cUF);
        
        $signer = new \App\Services\SefazSigner();
        $pfxPath = dirname(__DIR__, 3) . "/storage/certificados/" . $this->config['certificado_path'];
        $signedXml = $signer->signXML($xml, $pfxPath, $this->config['certificado_senha_raw']);

        $soapClient = new \App\Services\SefazSoapClient();
        $responseXml = $soapClient->call('nfe_evento', $signedXml, [
            'ambiente' => $this->config['ambiente'],
            'certificado_pfx' => $this->config['certificado_path'],
            'certificado_senha' => $this->config['certificado_senha_raw']
        ]);

        return $this->processarRetornoEvento($responseXml);
    }

    private function gerarXmlEventoManifesto($cnpj, $chave, $tpEvento, $cUF = '91') {
        $descEvento = [
            '210200' => 'Confirmacao da Operacao',
            '210210' => 'Ciencia da Operacao',
            '210220' => 'Desconhecimento da Operacao',
            '210240' => 'Operacao nao Realizada'
        ][$tpEvento] ?? 'Ciencia da Operacao';

        $tpAmb = ($this->config['ambiente'] == 'producao' ? '1' : '2');
        
        // 🕒 Fuso Horário: SEFAZ Ambiente Nacional geralmente usa Brasília (-03:00)
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $dhEvento = (new \DateTime('now', $tz))->format('Y-m-d\TH:i:sP');
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
        $id = 'ID' . $tpEvento . $chave . '01';

        // 📝 Template XML compacto
        // CORREÇÕES: 
        // 1. Adicionado versao="1.00" à tag infEvento (Obrigatório em alguns servidores)
        // 2. cOrgao dinâmico baseado na UF da filial (ou 91 para AN)
        return '<?xml version="1.0" encoding="UTF-8"?><envEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00"><idLote>1</idLote><evento versao="1.00"><infEvento Id="' . $id . '" versao="1.00"><cOrgao>' . $cUF . '</cOrgao><tpAmb>' . $tpAmb . '</tpAmb><CNPJ>' . $cnpjLimpo . '</CNPJ><chNFe>' . $chave . '</chNFe><dhEvento>' . $dhEvento . '</dhEvento><tpEvento>' . $tpEvento . '</tpEvento><nSeqEvento>1</nSeqEvento><verEvento>1.00</verEvento><detEvento versao="1.00"><descEvento>' . $descEvento . '</descEvento></detEvento></infEvento></evento></envEvento>';
    }

    /**
     * Consulta as NF-e destinadas via NFeDistribuicaoDFe em loop até esgotar notas
     */
    public function consultarNotas($cnpjDestinario, $ultNSU = null) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpjDestinario);
        if (empty($cnpj) || strlen($cnpj) !== 14) {
            throw new Exception("CNPJ inválido ou não configurado (" . htmlspecialchars($cnpjDestinario) . ").");
        }

        $ambiente = $this->config['ambiente'] == 'producao' ? 1 : 2;
        $key = $ambiente == 1 ? 'nfe_last_nsu' : 'nfe_last_nsu_homologacao';

        // ✅ NSU correto se não fornecido
        if ($ultNSU === null || $ultNSU === '0') {
            $stmt = $this->db->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
            $stmt->execute([$key]);
            $ultNSU = $stmt->fetchColumn();
            if (!$ultNSU || $ultNSU === '0') {
                $ultNSU = '000000000000000';
            }
        }

        $allDocumentos = [];
        $loops = 0;
        $maxLoops = 20; // Limite de segurança para evitar timeout excessivo
        $currentNSU = $ultNSU;
        $globalMaxNSU = '000000000000000';

        $this->lastSaveError = null;

        do {
            $loops++;
            $xml_soap = $this->gerarXmlDistDfe($cnpj, $currentNSU, $ambiente);
            $responseXml = $this->comunicarSefaz($xml_soap);
            $resultado = $this->processarRetorno($responseXml);

            $documentos = $resultado['documentos'] ?? [];
            $novoUltNSU = $resultado['ultNSU'] ?? $currentNSU;
            $maxNSU     = $resultado['maxNSU'] ?? $currentNSU;
            
            if ($maxNSU > $globalMaxNSU) $globalMaxNSU = $maxNSU;

            if (!empty($documentos)) {
                $filialId = $_SESSION['filial_id'] ?? 1;
                $this->salvarNotasCache($filialId, $documentos);
                $allDocumentos = array_merge($allDocumentos, $documentos);

                // Auto-manifestação
                foreach ($documentos as $doc) {
                    if (strpos($doc['xml'], '<resNFe') !== false) {
                        try {
                            $this->manifestarNota($cnpj, $doc['chave']);
                        } catch (Exception $e) {
                            error_log("Erro ao manifestar nota " . $doc['chave'] . ": " . $e->getMessage());
                        }
                    }
                }
            }

            // 🔄 Atualizar NSU no banco imediatamente após cada lote de 50
            if ($novoUltNSU > $currentNSU) {
                $stmt = $this->db->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
                $stmt->execute([$key, $novoUltNSU, $novoUltNSU]);
                $currentNSU = $novoUltNSU;
            } else {
                // Se o NSU não avançou, paramos o loop para evitar loop infinito
                break;
            }

            // Se for cStat 137 (nenhum documento), o status vem no retorno. 
            // processarRetorno já lança exceção se for erro, mas se for 137 ele deve parar.
            // Aqui assumimos que se documentos vier vazio ou status for 137, paramos.
            if (empty($documentos) || $resultado['status'] == '137') {
                break;
            }

        } while ($currentNSU < $maxNSU && $loops < $maxLoops);

        return [
            'documentos' => $allDocumentos,
            'count' => count($allDocumentos),
            'ultNSU' => $currentNSU,
            'maxNSU' => $globalMaxNSU,
            'loops' => $loops,
            'db_error' => $this->lastSaveError
        ];
    }
    

    private function gerarXmlDistDfe($cnpj, $ultNSU, $ambiente) {
        // Encontrar UF da filial baseada no CNPJ
        $stmt = $this->db->prepare("SELECT uf FROM filiais WHERE cnpj LIKE ? OR cnpj = ? LIMIT 1");
        // Tenta com e sem formatação
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        $cnpjFmt = substr($cnpjLimpo, 0, 2) . '.' . substr($cnpjLimpo, 2, 3) . '.' . substr($cnpjLimpo, 5, 3) . '/' . substr($cnpjLimpo, 8, 4) . '-' . substr($cnpjLimpo, 12, 2);
        $stmt->execute(['%'.$cnpjLimpo.'%', $cnpjFmt]);
        $siglaUf = $stmt->fetchColumn() ?: 'SP';
        $cUF = $this->ufCodes[strtoupper($siglaUf)] ?? '35';

        $xml = new DOMDocument('1.0', 'UTF-8');
        $ns = 'http://www.portalfiscal.inf.br/nfe';
        $distDFeInt = $xml->createElementNS($ns, 'distDFeInt');
        $distDFeInt->setAttribute('versao', '1.01');
        
        $xml->appendChild($distDFeInt);
        
        // Ordem rigorosa do schema
        $distDFeInt->appendChild($xml->createElementNS($ns, 'tpAmb', $ambiente));
        $distDFeInt->appendChild($xml->createElementNS($ns, 'cUFAutor', $cUF)); 
        $distDFeInt->appendChild($xml->createElementNS($ns, 'CNPJ', $cnpjLimpo));
        
        $dist = $xml->createElementNS($ns, 'distNSU');
        $dist->appendChild($xml->createElementNS($ns, 'ultNSU', str_pad($ultNSU, 15, '0', STR_PAD_LEFT)));
        $distDFeInt->appendChild($dist);

        return $xml->saveXML($xml->documentElement);
    }

    private function comunicarSefaz($xml) {
        $soapClient = new \App\Services\SefazSoapClient();
        return $soapClient->call('nfe_distribuicao', $xml, [
            'ambiente' => $this->config['ambiente'],
            'certificado_pfx' => $this->config['certificado_path'],
            'certificado_senha' => $this->config['certificado_senha_raw']
        ]);
    }

    private function processarRetorno($xmlStr) {
        if (empty($xmlStr)) {
            throw new Exception("SEFAZ retornou uma resposta vazia.");
        }

        // Remove namespaces and soap envelope parts for easier parsing
        $xml = simplexml_load_string($xmlStr);
        if (!$xml) {
            // Fallback: try cleaning xml if it's strictly malformed
            $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlStr);
            $xml = @simplexml_load_string($cleanXml);
        }
        
        if (!$xml) {
            throw new Exception("Falha ao ler resposta da SEFAZ (XML inválido).");
        }

        // Search for retDistDFeInt regardless of namespace or depth
        $nodes = $xml->xpath('//*[local-name()="retDistDFeInt"]');
        if (empty($nodes)) {
             throw new Exception("Resposta da SEFAZ não contém o nó esperado (retDistDFeInt). Verifique os logs.");
        }
        
        $retDist = $nodes[0];
        $status = (string)$retDist->cStat;
        if ($status != '138' && $status != '137') {
            $motivo = (string)$retDist->xMotivo ?: 'Erro desconhecido';
            throw new Exception("SEFAZ retornou erro: [$status] $motivo");
        }

        $docs = [];
        if (isset($retDist->loteDistDFeInt->docZip)) {
            foreach ($retDist->loteDistDFeInt->docZip as $docZip) {
                // O conteúdo vem gzipped e codificado em base64
                $decoded = base64_decode((string)$docZip);
                $content = @gzdecode($decoded);
                
                if ($content) {
                    $docXml = simplexml_load_string($content);
                    if (!$docXml) continue;

                    // Support namespaced search for children
                    $docXml->registerXPathNamespace('f', 'http://www.portalfiscal.inf.br/nfe');
                    $root = $docXml->getName();
                    
                    if ($root == 'resNFe') {
                        $docs[] = [
                            'chave' => (string)($docXml->chNFe ?: $docXml->xpath('//f:chNFe')[0]),
                            'cnpj' => (string)($docXml->CNPJ ?: $docXml->xpath('//f:CNPJ')[0]),
                            'nome' => (string)($docXml->xNome ?: $docXml->xpath('//f:xNome')[0]),
                            'numero' => substr((string)($docXml->chNFe ?: $docXml->xpath('//f:chNFe')[0]), 25, 9),
                            'data' => (string)($docXml->dhEmi ?: $docXml->xpath('//f:dhEmi')[0]),
                            'valor' => (float)($docXml->vNF ?: $docXml->xpath('//f:vNF')[0]),
                            'xml' => $content
                        ];
                    } elseif ($root == 'nfeProc' || $root == 'NFe') {
                        $inf = $docXml->xpath('//f:infNFe')[0] ?? $docXml->infNFe;
                        if ($inf) {
                            $docs[] = [
                                'chave' => str_replace('NFe', '', (string)$inf->attributes()->Id),
                                'cnpj' => (string)($inf->emit->CNPJ ?: $inf->xpath('//f:emit/f:CNPJ')[0]),
                                'nome' => (string)($inf->emit->xNome ?: $inf->xpath('//f:emit/f:xNome')[0]),
                                'numero' => (string)($inf->ide->nNF ?: $inf->xpath('//f:ide/f:nNF')[0]),
                                'data' => (string)($inf->ide->dhEmi ?: $inf->xpath('//f:ide/f:dhEmi')[0]),
                                'valor' => (float)($inf->total->ICMSTot->vNF ?: $inf->xpath('//f:total/f:ICMSTot/f:vNF')[0]),
                                'xml' => $content
                            ];
                        }
                    }
                }
            }
        }

        return [
            'status' => $status,
            'motivo' => (string)$xml->xMotivo,
            'ultNSU' => (string)$xml->ultNSU,
            'maxNSU' => (string)$xml->maxNSU,
            'documentos' => $docs
        ];
    }

    private function processarRetornoEvento($xmlStr) {
        if (empty($xmlStr)) throw new Exception("SEFAZ retornou uma resposta vazia no evento.");

        // 🧹 Limpeza agressiva: remove namespaces e prefixos para evitar problemas de parsing
        $xmlStr = preg_replace('/xmlns="[^"]+"/', '', $xmlStr);
        $xmlStr = preg_replace('/xmlns:[^=]+="[^"]+"/', '', $xmlStr);
        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlStr);
        
        $xml = @simplexml_load_string($cleanXml);
        
        if (!$xml) throw new Exception("Falha ao ler XML de retorno da SEFAZ. RAW: " . substr(strip_tags($xmlStr), 0, 50));

        // 1. Tentar encontrar o lote (retEnvEvento)
        $lote = $xml->xpath('//retEnvEvento');
        if (!empty($lote)) {
            $root = $lote[0];
            $cStatLote = (string)$root->cStat;
            $xMotivoLote = (string)$root->xMotivo;

            // Se o lote foi rejeitado (cStat != 128)
            if ($cStatLote != '128') {
                throw new Exception("Lote rejeitado pela SEFAZ: [$cStatLote] $xMotivoLote");
            }

            // Se o lote está OK, buscar o retorno do evento dentro dele
            if (isset($root->retEvento)) {
                $cStatEv = (string)$root->retEvento->infEvento->cStat;
                $xMotivoEv = (string)$root->retEvento->infEvento->xMotivo;
                
                if ($cStatEv == '135' || $cStatEv == '136') return true;
                throw new Exception("Evento rejeitado: [$cStatEv] $xMotivoEv");
            }
        }

        // 2. Fallback: Tentar encontrar retEvento diretamente (algumas SEFAZ respondem assim)
        $eventoDirect = $xml->xpath('//retEvento');
        if (!empty($eventoDirect)) {
            $cStat = (string)$eventoDirect[0]->infEvento->cStat;
            $xMotivo = (string)$eventoDirect[0]->infEvento->xMotivo;
            if ($cStat == '135' || $cStat == '136') return true;
            throw new Exception("Falha na manifestação: [$cStat] $xMotivo");
        }

        // 3. Fallback Final: Procurar qualquer cStat e xMotivo se nada acima funcionou
        $anyStat = $xml->xpath('//cStat');
        $anyMotivo = $xml->xpath('//xMotivo');
        if (!empty($anyStat)) {
            $stat = (string)$anyStat[0];
            $motivo = !empty($anyMotivo) ? (string)$anyMotivo[0] : "Erro desconhecido";
            throw new Exception("SEFAZ retornou status inesperado: [$stat] $motivo");
        }

        $debugRaw = substr(strip_tags($xmlStr), 0, 50);
        throw new Exception("Resposta de evento da SEFAZ não contém dados de processamento. Início da resposta: " . $debugRaw);
    }

    public function salvarNotasCache($filialId, $documentos) {
        $saved = 0;
        $skipped = 0;
        
        $this->ensureTableSchema();
        
        foreach ($documentos as $doc) {
            try {
                $cnpjLimpo = preg_replace('/\D/', '', $doc['cnpj'] ?? '');
                if (empty($cnpjLimpo)) $cnpjLimpo = '00000000000000';
                
                $dataEmissao = !empty($doc['data']) ? date('Y-m-d H:i:s', strtotime($doc['data'])) : date('Y-m-d H:i:s');
                if ($dataEmissao === '1970-01-01 00:00:00') $dataEmissao = date('Y-m-d H:i:s');
                
                // Usar INSERT ... ON DUPLICATE KEY UPDATE para garantir que não estamos falhando por duplicidade de forma invisível
                // E também para atualizar o conteúdo se necessário (ex: pegar XML completo após resumo)
                $stmt = $this->db->prepare("
                    INSERT INTO nfe_importadas (filial_id, chave_acesso, fornecedor_cnpj, fornecedor_nome, numero_nota, data_emissao, valor_total, xml, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
                    ON DUPLICATE KEY UPDATE 
                        xml = IF(LENGTH(xml) < ?, ?, xml),
                        data_emissao = ?,
                        valor_total = ?
                ");
                $xmlVal = $doc['xml'] ?? '';
                $stmt->execute([
                    $filialId,
                    $doc['chave'] ?? '',
                    $cnpjLimpo,
                    $doc['nome'] ?? 'Não identificado',
                    $doc['numero'] ?? '0',
                    $dataEmissao,
                    $doc['valor'] ?? 0,
                    $xmlVal,
                    // Parâmetros do UPDATE
                    strlen($xmlVal),
                    $xmlVal,
                    $dataEmissao,
                    $doc['valor'] ?? 0
                ]);

                if ($stmt->rowCount() > 0) {
                    $saved++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                if (!$this->lastSaveError) {
                    $this->lastSaveError = "DB ERROR: " . $e->getMessage() . " / SQLSTATE: " . (is_callable([$e, 'getCode']) ? $e->getCode() : '');
                }
                error_log("SEFAZ SAVE ERROR for chave " . ($doc['chave'] ?? 'unknown') . ": " . $e->getMessage());
                $skipped++;
                continue;
            }
        }
        error_log("SEFAZ salvarNotasCache: filial=$filialId, total=" . count($documentos) . ", saved=$saved, skipped=$skipped");
        return $saved;
    }
    
    /**
     * Garante que a tabela nfe_importadas tem a estrutura atualizada:
     * - UNIQUE na chave_nfe (necessário para INSERT IGNORE funcionar)
     * - fornecedor_cnpj com tamanho adequado
     */
    private function ensureTableSchema() {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        
        try {
            // Expandir fornecedor_cnpj para VARCHAR(20) caso ainda esteja em VARCHAR(14)
            $this->db->exec("ALTER TABLE nfe_importadas MODIFY COLUMN fornecedor_cnpj VARCHAR(20) NOT NULL DEFAULT ''");
        } catch (\Exception $e) { /* já está OK */ }
        
        try {
            // Adicionar UNIQUE na chave_acesso se não existir
            $this->db->exec("ALTER TABLE nfe_importadas ADD UNIQUE INDEX uk_chave_acesso (chave_acesso)");
        } catch (\Exception $e) { /* já existe */ }
    }

}
