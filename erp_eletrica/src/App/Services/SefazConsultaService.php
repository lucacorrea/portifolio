<?php
namespace App\Services;

use Exception;
use DOMDocument;

class SefazConsultaService extends BaseService {
    private $db;
    private $config;

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
    public function consultarNotas($cnpjDestinario, $ultNSU = '0') {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpjDestinario);
        if (empty($cnpj) || strlen($cnpj) !== 14) {
            throw new Exception("CNPJ inválido ou não configurado (" . htmlspecialchars($cnpjDestinario) . "). Verifique os dados da sua filial utilizando o botão 'Diagnóstico Completo' na aba Fiscal > Configurações.");
        }
        
        $ambiente = $this->config['ambiente'] == 'producao' ? 1 : 2; 

        // Preparamos o XML de solicitação seguindo NT 2014.002
        $xml_soap = $this->gerarXmlDistDfe($cnpj, $ultNSU, $ambiente);
        
        // Em um cenário real, usaríamos o certificado desacoplado da sefaz_config
        $responseXml = $this->comunicarSefaz($xml_soap);
        return $this->processarRetorno($responseXml);
    }

    private function gerarXmlDistDfe($cnpj, $ultNSU, $ambiente) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $distDFeInt = $xml->createElementNS('http://www.portalfiscal.inf.br/nfe', 'distDFeInt');
        $distDFeInt->setAttribute('versao', '1.01');
        
        $xml->appendChild($distDFeInt);
        $distDFeInt->appendChild($xml->createElement('tpAmb', $ambiente));
        $distDFeInt->appendChild($xml->createElement('cUFAutor', '91')); // Ambiente Nacional é obrigatório para DistDFe
        $distDFeInt->appendChild($xml->createElement('CNPJ', $cnpj));
        
        $dist = $xml->createElement('distNSU');
        $dist->appendChild($xml->createElement('ultNSU', str_pad($ultNSU, 15, '0', STR_PAD_LEFT)));
        $distDFeInt->appendChild($dist);

        return $xml->saveXML();
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

        // Remove namespaces for easier parsing (strips soap:, nfe:, etc)
        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlStr);
        $xml = simplexml_load_string($cleanXml);
        
        if (!$xml) {
            throw new Exception("Falha ao ler resposta da SEFAZ (XML inválido).");
        }

        // O nó de retorno retDistDFeInt pode estar dentro do envelope SOAP
        $nodes = $xml->xpath('//retDistDFeInt');
        if (empty($nodes)) {
             // Caso não esteja em SOAP (raro), tentamos o root
             $retDist = $xml;
        } else {
             $retDist = $nodes[0];
        }
        
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
                    // resNFe (resumo) ou nfeProc (completo)
                    $root = $docXml->getName();
                    if ($root == 'resNFe') {
                        $docs[] = [
                            'chave' => (string)$docXml->chNFe,
                            'cnpj' => (string)$docXml->CNPJ,
                            'nome' => (string)$docXml->xNome,
                            'numero' => substr((string)$docXml->chNFe, 25, 9),
                            'data' => (string)$docXml->dhEmi,
                            'valor' => (float)$docXml->vNF,
                            'xml' => $content
                        ];
                    } elseif ($root == 'nfeProc') {
                        $infNFe = $docXml->NFe->infNFe;
                        $docs[] = [
                            'chave' => str_replace('NFe', '', (string)$infNFe->attributes()->Id),
                            'cnpj' => (string)$infNFe->emit->CNPJ,
                            'nome' => (string)$infNFe->emit->xNome,
                            'numero' => (string)$infNFe->ide->nNF,
                            'data' => (string)$infNFe->ide->dhEmi,
                            'valor' => (float)$infNFe->total->ICMSTot->vNF,
                            'xml' => $content
                        ];
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
                    INSERT INTO nfe_importadas (filial_id, chave_acesso, fornecedor_cnpj, fornecedor_nome, numero_nota, data_emissao, valor_total, xml, status)
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
                $this->logAction('Nota SEFAZ Listada', 'nfe_importadas', $this->db->lastInsertId(), null, $doc['chave']);
            } catch (Exception $e) {
                // Provavelmente duplicidade (chave_acesso UNIQUE), ignoramos silenciosamente
                continue;
            }
        }
    }
}
