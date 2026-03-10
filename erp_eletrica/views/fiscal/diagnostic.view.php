<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-stethoscope text-primary me-2"></i> Diagnóstico Profundo SEFAZ
                </h4>
                <p class="text-muted mb-0">Varredura completa de conectividade, configuração do ambiente e banco de dados.</p>
            </div>
            <div>
                <a href="fiscal.php?action=settings" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Voltar para Configurações
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Seleção de Filial -->
    <?php if ($_SESSION['is_matriz'] && count($branches) > 1): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 d-flex align-items-center">
                <i class="fas fa-building text-primary fs-4 me-3"></i>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1">Unidade Analisada</h6>
                    <select class="form-select form-select-sm w-auto fw-bold" onchange="window.location.href='?action=diagnostic&id='+this.value">
                        <?php foreach($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $b['id'] == $selectedBranchId ? 'selected' : '' ?>><?= $b['nome'] ?> (<?= current(explode(' ', $b['cnpj'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button class="btn btn-primary btn-sm fw-bold" onclick="runLiveTest(<?= $selectedBranchId ?>)">
                        <i class="fas fa-play-circle me-1"></i> EXECUTAR TESTE DE COMUNICAÇÃO SEFAZ
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-12 text-end">
        <button class="btn btn-primary fw-bold" onclick="runLiveTest(<?= $selectedBranchId ?>)">
            <i class="fas fa-play-circle me-2"></i> EXECUTAR TESTE DE COMUNICAÇÃO SEFAZ
        </button>
    </div>
    <?php endif; ?>

    <!-- 1. Database Validation -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 <?= array_product($dbStatus) ? 'border-top border-success border-4' : 'border-top border-danger border-4' ?>">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-database text-secondary me-2"></i> 1. Dados da Filial Cadastrados</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>CNPJ Informado</span>
                        <?= $dbStatus['has_cnpj'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> SIM</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> FALTANDO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Formato do CNPJ Valido (14 dig)</span>
                        <?= $dbStatus['valid_cnpj'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> SIM</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INVÁLIDO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Inscrição Estadual (IE)</span>
                        <?= $dbStatus['has_ie'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> SIM</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> FALTANDO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>CEP Valido (8 dig)</span>
                        <?= $dbStatus['has_cep'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> SIM</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INVÁLIDO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>UF (2 letras)</span>
                        <?= $dbStatus['has_uf'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> SIM</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INVÁLIDO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Código MUNICIPIO IBGE (7 dig)</span>
                        <?= $dbStatus['has_ibge'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> SIM</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INVÁLIDO</span>' ?>
                    </li>
                </ul>
                <?php if(!array_product($dbStatus)): ?>
                <div class="alert alert-danger mt-3 mb-0 extra-small">
                    <strong>Atenção:</strong> Falta de dados cruciais resultará em rejeição imediata da SEFAZ ou erro XML. <a href="filiais.php">Corrija no cadastro da filial</a>.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. Environment & Extensions -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 <?= array_product(array_slice($env, 1)) ? 'border-top border-success border-4' : 'border-top border-danger border-4' ?>">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-server text-secondary me-2"></i> 2. Ambiente do Servidor (PHP)</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Versão PHP</span>
                        <span class="fw-bold text-muted"><?= $env['php_version'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Extensão cURL (Rede via HTTPS)</span>
                        <?= $env['curl_loaded'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> ATIVO</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INATIVO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Extensão OpenSSL (TLS 1.2/Cripto)</span>
                        <?= $env['openssl_loaded'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> ATIVO</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INATIVO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Extensão SOAP (WSDL SEFAZ)</span>
                        <?= $env['soap_loaded'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> ATIVO</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INATIVO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Extensão DOM (Geração XML)</span>
                        <?= $env['dom_loaded'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> ATIVO</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INATIVO</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Extensão SimpleXML (Leitura Retorno)</span>
                        <?= $env['simplexml_loaded'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> ATIVO</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> INATIVO</span>' ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 3. Storage & Certificate Verification -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 <?= array_product($storage) ? 'border-top border-success border-4' : 'border-top border-warning border-4' ?>">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-folder-open text-secondary me-2"></i> 3. Permissões e Certificado</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Diretório Storage/ (Gravação XMLs)</span>
                        <?= $storage['storage_writable'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> OK</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> PERM. NEGADA</span>' ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Diretório Certificados/</span>
                        <?= $storage['cert_dir_writable'] ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check"></i> OK</span>' : '<span class="badge bg-danger rounded-pill"><i class="fas fa-times"></i> PERM. NEGADA</span>' ?>
                    </li>
                </ul>

                <h6 class="fw-bold extra-small text-uppercase text-muted mb-2">Vínculo de Certificado</h6>
                <?php if ($globalConfig && $globalConfig['certificado_path']): ?>
                    <div class="alert alert-info py-2 px-3 small border-0 mb-0">
                        <i class="fas fa-link me-1"></i> Certificado GLOBAL ativo.
                        <div class="mt-1 extra-small user-select-all text-break"><code><?= $globalConfig['certificado_path'] ?></code></div>
                    </div>
                <?php elseif($branch['certificado_pfx']): ?>
                    <div class="alert alert-success py-2 px-3 small border-0 mb-0">
                        <i class="fas fa-file-signature me-1"></i> Certificado INDIVIDUAL ativo.
                        <div class="mt-1 extra-small user-select-all text-break"><code><?= $branch['certificado_pfx'] ?></code></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger py-2 px-3 small border-0 mb-0 fw-bold">
                        <i class="fas fa-exclamation-circle me-1"></i> Nenhum certificado! Emissão impossível.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Live Test Result Output -->
<div class="row g-4" id="liveTestContainer" style="display: none;">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-dark text-light">
            <div class="card-header border-secondary border-opacity-25 pt-4 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-white"><i class="fas fa-terminal me-2"></i> Log de Comunicação em Tempo Real SEFAZ</h6>
                <button class="btn btn-sm btn-outline-light" onclick="this.closest('#liveTestContainer').style.display='none'"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body font-monospace small" id="liveTestOutput" style="height: 300px; overflow-y: auto; background-color: #1e1e1e;">
                <!-- Logs serão injetados aqui -->
            </div>
        </div>
    </div>
</div>

<script>
async function runLiveTest(branchId) {
    const container = document.getElementById('liveTestContainer');
    const output = document.getElementById('liveTestOutput');
    
    container.style.display = 'block';
    output.innerHTML = `<div class="text-primary mb-2">> Iniciando diagnóstico completo de comunicação para filial #${branchId}...</div>`;
    
    const ts = new Date().toISOString();
    output.innerHTML += `<div class="text-muted mb-1">[${ts}] Preparando requisição 'nfeStatusServico4'...</div>`;
    output.innerHTML += `<div class="text-muted mb-1">[${ts}] Estabelecendo túnel mTLS (TLS 1.2) com a WSDL da SEFAZ...</div>`;
    
    // Auto-scroll to bottom Function
    const scrollToBottom = () => { output.scrollTop = output.scrollHeight; };
    scrollToBottom();

    try {
        const res = await fetch(`fiscal.php?action=test_connection&id=${branchId}`);
        const data = await res.json();
        
        const endTs = new Date().toISOString();
        if (data.success) {
            output.innerHTML += `<div class="text-success fw-bold mb-1 mt-3">[${endTs}] ✔ CONEXÃO BEM SUCEDIDA (HTTP 200 OK)</div>`;
            output.innerHTML += `<div class="text-success mb-1">> cStat: ${data.status}</div>`;
            output.innerHTML += `<div class="text-success mb-1">> xMotivo: ${data.motivo}</div>`;
            output.innerHTML += `<div class="text-white opacity-75 mb-1">> Ambiente: ${data.ambiente} | Versão: ${data.verAplic}</div>`;
            output.innerHTML += `<div class="text-info mt-2">> Diagnóstico: O certificado está válido, a senha está correta e a SEFAZ aceitou a assinatura e os cabeçalhos SOAP Action corretamente. Tudo pronto para faturamento!</div>`;
        } else {
            output.innerHTML += `<div class="text-danger fw-bold mb-1 mt-3">[${endTs}] ✖ FALHA NA CONEXÃO OU REJEIÇÃO DA SEFAZ</div>`;
            output.innerHTML += `<div class="text-danger mb-1">> Erro Retornado: ${data.error}</div>`;
            
            // Sugestões automáticas baseadas no erro
            output.innerHTML += `<div class="text-warning mt-3 mb-1">=== SUGESTÕES DE SOLUÇÃO (IA DIAGNÓSTICA) ===</div>`;
            if (data.error.includes("500") || data.error.includes("Action")) {
                output.innerHTML += `<div class="text-warning mb-1">> Possível Causa: Protocolo SOAP Action incorreto para a SEFAZ do seu Estado ou erro interno da SEFAZ no processamento do XML.</div>`;
            } else if (data.error.includes("token") || data.error.includes("senha") || data.error.includes("certPKCS12")) {
                output.innerHTML += `<div class="text-warning mb-1">> Possível Causa: A senha do certificado informada está incorreta ou o arquivo .pfx está corrompido. Tente enviar o arquivo novamente.</div>`;
            } else if (data.error.includes("Forbidden") || data.error.includes("403")) {
                output.innerHTML += `<div class="text-warning mb-1">> Possível Causa: O certificado enviado não possui autorização (CNPJ divergente) ou expirou.</div>`;
            } else {
                output.innerHTML += `<div class="text-warning mb-1">> Sugerimos olhar os arquivos temporais (storage/last_connection_test_error.txt) para stack trace avançado.</div>`;
            }
        }
    } catch (e) {
        output.innerHTML += `<div class="text-danger fw-bold mb-1 mt-3">[SERVER ERRO FATAL] CRASH LOCAL</div>`;
        output.innerHTML += `<div class="text-danger mb-1">> ${e.message}</div>`;
        output.innerHTML += `<div class="text-warning mb-1">> Verifique a aba Network (Rede) do navegador para ver o retorno bruto em HTML que quebrou o JSON.</div>`;
    }
    
    output.innerHTML += `<div class="text-white mt-3 border-top border-secondary pt-2">--- Fim da Análise ---</div>`;
    scrollToBottom();
}
</script>
