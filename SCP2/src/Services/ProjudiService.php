<?php
/**
 * ProjudiService.php - Módulo de Integração MNI 2.2.2 (TJAM)
 * Responsável pela comunicação SOAP e tratamento de dados do Projudi.
 */

class ProjudiService {
    private $wsdl;
    private $idConsultante;
    private $codigoSecreto;
    private $client;

    public function __construct($wsdl, $idConsultante, $codigoSecreto) {
        $this->wsdl = $wsdl;
        $this->idConsultante = $idConsultante;
        $this->codigoSecreto = $codigoSecreto;
        
        try {
            $this->client = new SoapClient($this->wsdl, [
                'trace' => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 15
            ]);
        } catch (Exception $e) {
            throw new Exception("Erro ao conectar ao Web Service: " . $e->getMessage());
        }
    }

    /**
     * Gera a senha dinâmica baseada no MD5(código + AAAAMMDD)
     */
    private function gerarSenhaDinamica() {
        $dataAtual = date('Ymd');
        return md5($this->codigoSecreto . $dataAtual);
    }

    /**
     * Consulta os dados de um processo específico
     */
    public function consultarProcesso($numeroProcesso) {
        $params = [
            'idConsultante' => $this->idConsultante,
            'senhaConsultante' => $this->gerarSenhaDinamica(),
            'numeroProcesso' => $numeroProcesso,
            'movimentos' => true,
            'incluirCabecalho' => true,
            'incluirDocumentos' => false // Por padrão não baixa PDFs para ser mais rápido
        ];

        try {
            // A operação no MNI 2.2.2 costuma ser 'consultarProcesso'
            $response = $this->client->consultarProcesso($params);
            return $this->tratarResposta($response);
        } catch (SoapFault $f) {
            throw new Exception("Erro na consulta Projudi: " . $f->getMessage());
        }
    }

    /**
     * Busca avisos/intimações pendentes
     */
    public function consultarAvisosPendentes() {
        $params = [
            'idConsultante' => $this->idConsultante,
            'senhaConsultante' => $this->gerarSenhaDinamica()
        ];

        try {
            $response = $this->client->consultarAvisosPendentes($params);
            return $response;
        } catch (SoapFault $f) {
            throw new Exception("Erro ao buscar avisos: " . $f->getMessage());
        }
    }

    /**
     * Converte o objeto de resposta em um array amigável para o SCP
     */
    private function tratarResposta($response) {
        // Lógica de parser baseada no XSD recebido
        // Aqui vamos extrair partes, assuntos, magistrado e movimentações
        return $response; 
    }
}
