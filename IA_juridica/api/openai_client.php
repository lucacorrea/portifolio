<?php
/**
 * OpenAI API Client for IA Jurídica
 */

class OpenAIClient {
    private $apiKey;
    private $apiUrl;

    public function __construct() {
        $settings = require __DIR__ . '/../config/settings.php';
        $this->apiKey = $settings['openai_api_key'];
        $this->apiUrl = $settings['api_url'];
    }

    public function generateDocument($type, $template, $data) {
        if ($this->apiKey === 'SUA_CHAVE_OPENAI_AQUI' || empty($this->apiKey)) {
            // Return a mock response if API key is not set for testing
            return $this->getMockResponse($type, $data);
        }

        $prompt = $this->buildPrompt($type, $template, $data);

        $payload = [
            'model' => 'gpt-4-turbo-preview',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Você é um assistente jurídico especializado em redação de documentos formais utilizados em órgãos públicos brasileiros e escritórios de advocacia. Utilize linguagem jurídica formal, estrutura institucional e clareza administrativa."
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("OpenAI API Error: " . $response);
            throw new Exception("Erro ao gerar documento via IA. Verifique as configurações da API.");
        }

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'];
    }

    private function buildPrompt($type, $template, $data) {
        $prompt = "Crie um documento jurídico formal do tipo '$type' preenchendo o seguinte modelo:\n\n";
        $prompt .= $template . "\n\n";
        $prompt .= "Informações para preenchimento:\n";
        foreach ($data as $key => $value) {
            $prompt .= "- $key: $value\n";
        }
        $prompt .= "\nO documento deve conter:\n- Cabeçalho institucional\n- Identificação do documento\n- Assunto\n- Texto formal\n- Encerramento\n- Assinatura do responsável.\n";
        $prompt .= "Retorne apenas o texto final formatado do documento.";

        return $prompt;
    }

    private function getMockResponse($type, $data) {
        return "--- MODO DE TESTE (SEM API KEY) ---\n\nDOCUMENTO: $type\nDATA: " . date('d/m/Y') . "\nASSUNTO: " . ($data['assunto'] ?? 'N/A') . "\n\nExcelentíssimo(a) Senhor(a),\n\nEste é um texto gerado em modo de simulação porque a chave da API da OpenAI não foi configurada no arquivo config/settings.php.\n\nAtenciosamente,\n\n" . ($data['responsavel'] ?? 'Responsável') . "\n" . ($data['cargo'] ?? 'Cargo');
    }
}
