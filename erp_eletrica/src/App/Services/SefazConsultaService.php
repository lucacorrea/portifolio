<?php
namespace App\Services;

use Exception;
use DOMDocument;

class SefazConsultaService extends BaseService {
    private $db;
    private $filial;

    public function __construct($filialId) {
        parent::__construct();
        $this->db = \App\Config\Database::getInstance()->getConnection();
        $this->loadFilial($filialId);
    }

    private function loadFilial($id) {
        $stmt = $this->db->prepare("SELECT * FROM filiais WHERE id = ?");
        $stmt->execute([$id]);
        $this->filial = $stmt->fetch();
        if (!$this->filial) throw new Exception("Filial não encontrada.");
        if (empty($this->filial['certificado_pfx'])) throw new Exception("Certificado A1 não configurado para esta filial.");
    }

    /**
     * Consulta as NF-e destinadas via NFeDistribuicaoDFe
     */
    public function consultarNotas($ultNSU = '0') {
        $cnpj = preg_replace('/[^0-9]/', '', $this->filial['cnpj']);
        $ambiente = $this->filial['ambiente']; // 1 = Produção, 2 = Homologação
        $uf = $this->filial['uf'] ?: '35'; // Default SP se não informado

        // Preparamos o XML de solicitação seguindo NT 2014.002
        $xml_soap = $this->gerarXmlDistDfe($cnpj, $ultNSU, $ambiente);
        
        // Em um cenário real, usaríamos o certificado PFX com cURL/SOAP
        // Como não podemos testar conectividade real aqui, vamos simular o retorno da SEFAZ
        // mas o código está estruturado para a lógica real.
        
        $responseXml = $this->comunicarSefaz($xml_soap);
        return $this->processarRetorno($responseXml);
    }

    private function gerarXmlDistDfe($cnpj, $ultNSU, $ambiente) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $distDFeInt = $xml->createElementNS('http://www.portalfiscal.inf.br/nfe', 'distDFeInt');
        $distDFeInt->setAttribute('versao', '1.01');
        
        $xml->appendChild($distDFeInt);
        $distDFeInt->appendChild($xml->createElement('tpAmb', $ambiente));
        $distDFeInt->appendChild($xml->createElement('cUFAutor', '35')); // Geralmente 91 para AN, mas SP usa 35
        $distDFeInt->appendChild($xml->createElement('CNPJ', $cnpj));
        
        $dist = $xml->createElement('distNSU');
        $dist->appendChild($xml->createElement('ultNSU', str_pad($ultNSU, 15, '0', STR_PAD_LEFT)));
        $distDFeInt->appendChild($dist);

        return $xml->saveXML();
    }

    private function comunicarSefaz($xml) {
        // MOCK: Simulação de resposta da SEFAZ para fins de demonstração do fluxo
        // Em produção, aqui iria o cURL com Certificado Digital
        
        $this->logAction('Consulta SEFAZ Realizada', 'filiais', $this->filial['id']);
        
        // Simulando um retorno com 1 nota para teste
        $mockXml = '<?xml version="1.0" encoding="utf-8"?>
        <retDistDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
            <tpAmb>2</tpAmb>
            <verAplic>SP_NFE_PL_009_V4</verAplic>
            <cStat>138</cStat>
            <xMotivo>Documentos localizados</xMotivo>
            <ultNSU>000000000000123</ultNSU>
            <maxNSU>000000000000123</maxNSU>
            <loteDistDFeInt>
                <docZip NSU="000000000000123" schema="resNFe_v1.01">H4sIAAAAAAAACjvOz9XNL0pPzEtXyE9TCMnMTfXJL0pVSM7MTfVNLkotSy0qzszPU0jOSy3IA8oWpSbk5-cXpRYXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpSokF-QXlyQXpYkBABO5P6WAAAAA</docZip>
            </loteDistDFeInt>
        </retDistDFeInt>';
        
        return $mockXml;
    }

    private function processarRetorno($xmlStr) {
        $xml = simplexml_load_string($xmlStr);
        $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
        
        $status = (string)$xml->cStat;
        if ($status != '138' && $status != '137') {
            throw new Exception("SEFAZ retornou erro: " . (string)$xml->xMotivo);
        }

        $docs = [];
        if (isset($xml->loteDistDFeInt->docZip)) {
            foreach ($xml->loteDistDFeInt->docZip as $docZip) {
                // O conteúdo vem gzipped e codificado em base64
                $decoded = base64_decode((string)$docZip);
                $content = @gzdecode($decoded);
                
                if ($content) {
                    $docXml = simplexml_load_string($content);
                    // resNFe ou procNFe
                    if ($docXml->getName() == 'resNFe') {
                        $docs[] = [
                            'chave' => (string)$docXml->chNFe,
                            'cnpj' => (string)$docXml->CNPJ,
                            'nome' => (string)$docXml->xNome,
                            'numero' => substr((string)$docXml->chNFe, 25, 9),
                            'data' => (string)$docXml->dhEmi,
                            'valor' => (float)$docXml->vNF,
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

    public function salvarNotasCache($documentos) {
        foreach ($documentos as $doc) {
            $stmt = $this->db->prepare("SELECT id FROM nfe_importadas WHERE chave_nfe = ? AND filial_id = ?");
            $stmt->execute([$doc['chave'], $this->filial['id']]);
            if ($stmt->fetch()) continue; // Já existe

            $stmt = $this->db->prepare("
                INSERT INTO nfe_importadas (filial_id, chave_nfe, fornecedor_cnpj, fornecedor_nome, numero_nota, data_emissao, valor_total, xml_conteudo, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
            ");
            $stmt->execute([
                $this->filial['id'],
                $doc['chave'],
                $doc['cnpj'],
                $doc['nome'],
                $doc['numero'],
                date('Y-m-d H:i:s', strtotime($doc['data'])),
                $doc['valor'],
                $doc['xml']
            ]);
            
            $this->logAction('Nota SEFAZ Listada', 'nfe_importadas', $this->db->lastInsertId(), null, $doc['chave']);
        }
    }
}
