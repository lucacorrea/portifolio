<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

class WhatsappService
{
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: semas_whatsapp_config();
    }

    public function verificarConexao(): array
    {
        $retorno = $this->executarRequisicao('/status', [], 'GET');
        if (!$retorno['sucesso']) {
            return $retorno;
        }

        $dados = $this->dadosBridge($retorno);
        $status = (string)($dados['state'] ?? $dados['status'] ?? 'indisponivel');
        $connected = !empty($dados['connected']) || $status === 'connected';

        return [
            'sucesso' => true,
            'mensagem' => $connected ? 'WhatsApp conectado.' : $this->mensagemStatus($status),
            'http_code' => (int)$retorno['http_code'],
            'dados' => [
                'conectado' => $connected,
                'status' => $status,
                'instance_id' => $dados['instanceId'] ?? $this->config['instance_id'] ?? 'semas_whatsapp',
                'numero' => $dados['phoneMasked'] ?? null,
                'phoneMasked' => $dados['phoneMasked'] ?? null,
                'accountName' => $dados['accountName'] ?? null,
                'lastChangeAt' => $dados['lastChangeAt'] ?? null,
                'qrExpiresAt' => $dados['qrExpiresAt'] ?? null,
            ],
        ];
    }

    public function iniciarQrCode(): array
    {
        $retorno = $this->executarRequisicao('/connect/qrcode', [], 'POST');
        if (!$retorno['sucesso']) {
            return $retorno;
        }

        return $this->obterQrCode();
    }

    public function obterQrCode(): array
    {
        $retorno = $this->executarRequisicao('/qrcode', [], 'GET');
        if (!$retorno['sucesso']) {
            return $retorno;
        }

        return $this->resposta(true, 'QR Code consultado.', (int)$retorno['http_code'], $this->dadosBridge($retorno));
    }

    public function solicitarPareamento(string $telefone): array
    {
        $telefone = $this->normalizarTelefone($telefone);
        if ($telefone === '') {
            return $this->resposta(false, 'Telefone inválido para pareamento.', 422);
        }

        $retorno = $this->executarRequisicao('/pairing-code', ['phone' => $telefone], 'POST');
        if (!$retorno['sucesso']) {
            return $retorno;
        }

        return $this->resposta(true, 'Código de pareamento gerado.', (int)$retorno['http_code'], $this->dadosBridge($retorno));
    }

    public function reiniciarCliente(): array
    {
        return $this->executarRequisicao('/restart', [], 'POST');
    }

    public function desconectarConta(): array
    {
        return $this->executarRequisicao('/disconnect', [], 'POST');
    }

    public function apagarSessao(): array
    {
        return $this->executarRequisicao('/session/reset', [], 'POST');
    }

    public function enviarTexto(string $telefone, string $mensagem, ?string $idempotencyKey = null): array
    {
        $telefone = $this->normalizarTelefone($telefone);
        if ($telefone === '') {
            return $this->resposta(false, 'Telefone inválido.', 422);
        }

        $mensagem = trim($mensagem);
        if ($mensagem === '') {
            return $this->resposta(false, 'Mensagem obrigatória.', 422);
        }

        $tamanhoMensagem = function_exists('mb_strlen') ? mb_strlen($mensagem, 'UTF-8') : strlen($mensagem);
        if ($tamanhoMensagem > (int)$this->config['message_limit']) {
            return $this->resposta(false, 'Mensagem excede o limite permitido.', 422);
        }

        return $this->executarRequisicao('/send-message', [
            'to' => $telefone,
            'type' => 'text',
            'text' => $mensagem,
            'idempotencyKey' => $idempotencyKey ?: hash('sha256', $telefone . '|' . sha1($mensagem)),
        ], 'POST');
    }

    public function enviarImagem(string $telefone, string $imagem, string $legenda = ''): array
    {
        return $this->resposta(false, 'Envio de imagem ainda não está habilitado no bridge SEMAS.', 501);
    }

    public function enviarDocumento(string $telefone, string $arquivo, string $nomeArquivo = ''): array
    {
        return $this->resposta(false, 'Envio de documento ainda não está habilitado no bridge SEMAS.', 501);
    }

    public function normalizarTelefone(string $telefone): string
    {
        $numero = preg_replace('/\D+/', '', $telefone);
        $numero = is_string($numero) ? $numero : '';

        if ($numero === '') {
            return '';
        }

        if (strlen($numero) === 10 || strlen($numero) === 11) {
            $numero = '55' . $numero;
        }

        if (substr($numero, 0, 2) !== '55') {
            return '';
        }

        $semPais = substr($numero, 2);
        if (strlen($semPais) !== 10 && strlen($semPais) !== 11) {
            return '';
        }

        if (preg_match('/^(\d)\1+$/', $semPais)) {
            return '';
        }

        return $numero;
    }

    public function executarRequisicao(string $endpoint, array $dados = [], string $metodo = 'POST'): array
    {
        $baseUrl = rtrim((string)($this->config['bridge_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $this->registrarLog('config', 'URL do bridge WhatsApp não localizada.', []);
            return $this->resposta(false, 'Configuração do WhatsApp indisponível.', 500);
        }

        $endpoint = '/' . ltrim($endpoint, '/');
        $url = $baseUrl . $endpoint;
        $metodo = strtoupper($metodo);
        $internalKey = (string)($this->config['internal_key'] ?? '');
        if ($endpoint !== '/health' && strlen($internalKey) < 32) {
            return $this->resposta(false, 'Chave interna do bridge SEMAS não configurada.', 500);
        }

        if (!function_exists('curl_init')) {
            return $this->executarRequisicaoStream($url, $endpoint, $dados, $metodo, $internalKey);
        }

        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($endpoint !== '/health') {
            $headers[] = 'X-Internal-Key: ' . $internalKey;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->config['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($metodo !== 'GET') {
            $payload = json_encode($dados, JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                return $this->resposta(false, 'Falha ao preparar payload JSON.', 500);
            }

            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            $mensagem = $curlErrno === 28 ? 'Timeout ao comunicar com o bridge WhatsApp.' : 'Falha de conexão com o bridge WhatsApp.';
            $this->registrarLog('erro_requisicao', $mensagem, [
                'endpoint' => $endpoint,
                'curl_errno' => $curlErrno,
                'curl_error' => $curlError,
            ]);
            return $this->resposta(false, $mensagem, $httpCode > 0 ? $httpCode : 0);
        }

        if (trim((string)$body) === '') {
            $this->registrarLog('erro_resposta', 'Resposta vazia do bridge WhatsApp.', ['endpoint' => $endpoint, 'http_code' => $httpCode]);
            return $this->resposta(false, 'Resposta vazia do bridge WhatsApp.', $httpCode);
        }

        $json = json_decode((string)$body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->registrarLog('erro_resposta', 'Resposta inválida do bridge WhatsApp.', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'json_error' => json_last_error_msg(),
            ]);
            return $this->resposta(false, 'Resposta inválida do bridge WhatsApp.', $httpCode);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($json) ? (string)($json['error'] ?? $json['message'] ?? 'Falha no bridge WhatsApp.') : 'Falha no bridge WhatsApp.';
            if ($httpCode === 401 || $httpCode === 403) {
                $message = 'Autenticação recusada pelo bridge WhatsApp.';
            }
            $this->registrarLog('erro_http', $message, ['endpoint' => $endpoint, 'http_code' => $httpCode]);
            return $this->resposta(false, $message, $httpCode, is_array($json) ? $json : []);
        }

        return $this->resposta(true, 'Operação realizada com sucesso.', $httpCode, is_array($json) ? $json : []);
    }

    private function executarRequisicaoStream(string $url, string $endpoint, array $dados, string $metodo, string $internalKey): array
    {
        if (!ini_get('allow_url_fopen')) {
            return $this->resposta(false, 'Extensão cURL indisponível e allow_url_fopen desativado no PHP.', 500);
        }

        if (parse_url($url, PHP_URL_SCHEME) === 'https' && !in_array('https', stream_get_wrappers(), true)) {
            return $this->resposta(false, 'PHP sem cURL/OpenSSL para consultar o bridge HTTPS do WhatsApp.', 500);
        }

        $headers = ['Accept: application/json'];
        if ($endpoint !== '/health') {
            $headers[] = 'X-Internal-Key: ' . $internalKey;
        }
        $options = [
            'http' => [
                'method' => $metodo,
                'timeout' => (int)$this->config['timeout'],
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        if ($metodo !== 'GET') {
            $payload = json_encode($dados, JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                return $this->resposta(false, 'Falha ao preparar payload JSON.', 500);
            }
            $headers[] = 'Content-Type: application/json';
            $options['http']['content'] = $payload;
        }

        $options['http']['header'] = implode("\r\n", $headers) . "\r\n";
        $context = stream_context_create($options);
        $body = @file_get_contents($url, false, $context);
        $httpCode = $this->httpCodeFromHeaders($http_response_header ?? []);

        if ($body === false) {
            $this->registrarLog('erro_requisicao', 'Falha de conexão com o bridge WhatsApp.', [
                'endpoint' => $endpoint,
                'transport' => 'stream',
                'http_code' => $httpCode,
            ]);
            return $this->resposta(false, 'Falha de conexão com o bridge WhatsApp.', $httpCode);
        }

        return $this->respostaBridge((string)$body, $httpCode, $endpoint);
    }

    private function respostaBridge(string $body, int $httpCode, string $endpoint): array
    {
        if (trim($body) === '') {
            $this->registrarLog('erro_resposta', 'Resposta vazia do bridge WhatsApp.', ['endpoint' => $endpoint, 'http_code' => $httpCode]);
            return $this->resposta(false, 'Resposta vazia do bridge WhatsApp.', $httpCode);
        }

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->registrarLog('erro_resposta', 'Resposta inválida do bridge WhatsApp.', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'json_error' => json_last_error_msg(),
            ]);
            return $this->resposta(false, 'Resposta inválida do bridge WhatsApp.', $httpCode);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($json) ? (string)($json['error'] ?? $json['message'] ?? 'Falha no bridge WhatsApp.') : 'Falha no bridge WhatsApp.';
            if ($httpCode === 401 || $httpCode === 403) {
                $message = 'Autenticação recusada pelo bridge WhatsApp.';
            }
            $this->registrarLog('erro_http', $message, ['endpoint' => $endpoint, 'http_code' => $httpCode]);
            return $this->resposta(false, $message, $httpCode, is_array($json) ? $json : []);
        }

        return $this->resposta(true, 'Operação realizada com sucesso.', $httpCode, is_array($json) ? $json : []);
    }

    private function httpCodeFromHeaders(array $headers): int
    {
        $httpCode = 0;
        foreach ($headers as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string)$header, $matches)) {
                $httpCode = (int)$matches[1];
            }
        }

        return $httpCode;
    }

    private function dadosBridge(array $retorno): array
    {
        $payload = is_array($retorno['dados'] ?? null) ? $retorno['dados'] : [];
        if (isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return $payload;
    }

    public function registrarLog(string $tipo, string $mensagem, array $contexto = []): void
    {
        $dir = (string)$this->config['log_dir'];
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = [
            'data' => date('c'),
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'usuario_id' => $_SESSION['semas_whatsapp_user_id'] ?? null,
            'contexto' => semas_whatsapp_safe_context($contexto),
        ];

        @file_put_contents(
            $dir . '/whatsapp-' . date('Y-m-d') . '.log',
            json_encode($line, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function resposta(bool $sucesso, string $mensagem, int $httpCode = 200, array $dados = []): array
    {
        return [
            'sucesso' => $sucesso,
            'mensagem' => $mensagem,
            'http_code' => $httpCode,
            'dados' => $dados,
        ];
    }

    private function mensagemStatus(string $status): string
    {
        $map = [
            'connected' => 'WhatsApp conectado.',
            'disconnected' => 'WhatsApp desconectado.',
            'waiting_qr' => 'WhatsApp aguardando leitura do QR Code.',
            'qr_available' => 'QR Code disponível para leitura.',
            'waiting_pairing_code' => 'Aguardando confirmação do código de pareamento.',
            'connecting' => 'WhatsApp autenticando ou conectando.',
            'authenticating' => 'WhatsApp autenticando.',
            'restoring_session' => 'Restaurando sessão SEMAS.',
            'starting' => 'Iniciando cliente WhatsApp SEMAS.',
            'reconnecting' => 'Reconectando WhatsApp SEMAS.',
            'auth_failed' => 'Sessão inválida ou desconectada no celular.',
            'not_initialized' => 'Cliente WhatsApp SEMAS ainda não iniciado.',
            'error' => 'Bridge WhatsApp SEMAS com erro.',
            'offline' => 'Bridge WhatsApp SEMAS indisponível.',
        ];

        return $map[$status] ?? 'Status do WhatsApp indisponível.';
    }
}
