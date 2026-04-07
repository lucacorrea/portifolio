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
        $stmt = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
        $this->config = $stmt->fetch();
        if (!$this->config) throw new Exception("Configuração SEFAZ Global não encontrada.");
        if (empty($this->config['certificado_path'])) throw new Exception("Certificado A1 não configurado.");
        
        // Ensure password is raw
        if (!empty($this->config['certificado_senha'])) {
             $this->config['certificado_senha_raw'] = $this->config['certificado_senha'];
        }
    }

    /**
     * Realiza a manifestação do destinatário (Ciência da Operação)
     */
    public function manifestarNota($cnpj, $chave) {
        $xml = $this->gerarXmlEventoManifesto($cnpj, $chave);
        
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

    private function gerarXmlEventoManifesto($cnpj, $chave) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $envEvento = $dom->createElementNS('http://www.portalfiscal.inf.br/nfe', 'envEvento');
        $envEvento->setAttribute('versao', '1.00');
        $dom->appendChild($envEvento);
        
        $envEvento->appendChild($dom->createElement('idLote', '1'));
        
        $evento = $dom->createElement('evento');
        $evento->setAttribute('versao', '1.00');
        $envEvento->appendChild($evento);
        
        $infEvento = $dom->createElement('infEvento');
        $id = 'ID210210' . $chave . '01';
        $infEvento->setAttribute('Id', $id);
        $evento->appendChild($infEvento);
        
        $infEvento->appendChild($dom->createElement('cOrgao', '91')); // Ambiente Nacional
        $infEvento->appendChild($dom->createElement('tpAmb', ($this->config['ambiente'] == 'producao' ? '1' : '2')));
        $infEvento->appendChild($dom->createElement('CNPJ', preg_replace('/[^0-9]/', '', $cnpj)));
        $infEvento->appendChild($dom->createElement('chNFe', $chave));
        $infEvento->appendChild($dom->createElement('dhEvento', date('Y-m-d\TH:i:sP')));
        $infEvento->appendChild($dom->createElement('tpEvento', '210210')); // Ciência da Operação
        $infEvento->appendChild($dom->createElement('nSeqEvento', '1'));
        $infEvento->appendChild($dom->createElement('verEvento', '1.00'));
        
        $detEvento = $dom->createElement('detEvento');
        $detEvento->setAttribute('versao', '1.00');
        $infEvento->appendChild($detEvento);
        $detEvento->appendChild($dom->createElement('descEvento', 'Ciencia da Operacao'));
        
        return $dom->saveXML();
    }

    /**
     * Consulta as NF-e destinadas via NFeDistribuicaoDFe
     */
    public function consultarNotas($cnpjDestinario, $ultNSU = null) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpjDestinario);
        if (empty($cnpj) || strlen($cnpj) !== 14) {
            throw new Exception("CNPJ inválido ou não configurado (" . htmlspecialchars($cnpjDestinario) . "). Verifique os dados da sua filial.");
        }
        
        $ambiente = $this->config['ambiente'] == 'producao' ? 1 : 2; 
        
        // Se ultNSU não for fornecido, buscar das configurações
        if ($ultNSU === null) {
            $key = $ambiente == 1 ? 'nfe_last_nsu' : 'nfe_last_nsu_homologacao';
            $stmt = $this->db->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
            $stmt->execute([$key]);
            $ultNSU = $stmt->fetchColumn() ?: '0';
        }

        $allDocs = [];
        $currentNSU = $ultNSU;
        $maxIterations = 10; // Evitar loop infinito se houver erro
        
        do {
            $xml_soap = $this->gerarXmlDistDfe($cnpj, $currentNSU, $ambiente);
            $responseXml = $this->comunicarSefaz($xml_soap);
            $resultado = $this->processarRetorno($responseXml);
            
            if (!empty($resultado['documentos'])) {
                $allDocs = array_merge($allDocs, $resultado['documentos']);
                
                // Salvar imediatamente no banco para não perder dados se a próxima iteração falhar
                $this->salvarNotasCache($_SESSION['filial_id'] ?? 1, $resultado['documentos']);
                
                // Auto-manifestar notas que vieram apenas como resumo para permitir download futuro do XML completo
                foreach ($resultado['documentos'] as $doc) {
                    if (strpos($doc['xml'], '<resNFe') !== false) {
                        try {
                            $this->manifestarNota($cnpj, $doc['chave']);
                        } catch (Exception $me) {
                            error_log("Erro ao auto-manifestar nota " . $doc['chave'] . ": " . $me->getMessage());
                        }
                    }
                }
            }
            
            $currentNSU = $resultado['ultNSU'];
            $maxNSU = $resultado['maxNSU'];
            
            // Atualizar o último NSU nas configurações
            $key = $ambiente == 1 ? 'nfe_last_nsu' : 'nfe_last_nsu_homologacao';
            $stmt = $this->db->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$key, $currentNSU, $currentNSU]);
            
            $maxIterations--;
        } while ($currentNSU < $maxNSU && $maxIterations > 0);

        return ['documentos' => $allDocs, 'ultNSU' => $currentNSU, 'maxNSU' => $maxNSU];
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
        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlStr);
        $xml = simplexml_load_string($cleanXml);
        
        $ret = $xml->xpath('//retEnvEvento');
        if (empty($ret)) throw new Exception("Resposta de evento inválida da SEFAZ.");
        
        $cStat = (string)$ret[0]->retEvento->infEvento->cStat;
        $xMotivo = (string)$ret[0]->retEvento->infEvento->xMotivo;
        
        if ($cStat != '135' && $cStat != '136') {
            throw new Exception("Falha na manifestação: [$cStat] $xMotivo");
        }
        
        return true;
    }

    public function salvarNotasCache($filialId, $documentos) {
        foreach ($documentos as $doc) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO nfe_importadas (filial_id, chave_nfe, fornecedor_cnpj, fornecedor_nome, numero_nota, data_emissao, valor_total, xml_conteudo, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $filialId,
                    $doc['chave'],
                    $doc['cnpj'],
                    $doc['nome'],
                    $doc['numero'],
                    date('Y-m-d H:i:s', strtotime($doc['data'])),
                    $doc['valor'],
                    $doc['xml']
                ]);
                $lastId = $this->db->lastInsertId();
                if ($lastId) {
                    $this->logAction('Nota SEFAZ Listada', 'nfe_importadas', $lastId, null, $doc['chave']);
                }
            } catch (\Exception $e) {
                // Se o erro não for de duplicidade (SQLSTATE 23000), logamos para debug
                if ($e->getCode() != '23000') {
                    error_log("Erro ao salvar nota cache SEFAZ: " . $e->getMessage());
                }
                continue;
            }
        }
    }

}
