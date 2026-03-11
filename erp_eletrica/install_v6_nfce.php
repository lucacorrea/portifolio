<?php
/**
 * PATCH V6 - NFC-e Integration & UI Standardization
 * ERP Elétrica - Cloud Deployment Tool
 * 
 * Instructions:
 * 1. Upload this file to your Hostinger server (root of erp_eletrica).
 * 2. Access it via: http://lucacorrea.pro/erp_eletrica/install_v6_nfce.php
 * 3. The system will automatically update files and database.
 */

require_once 'config.php';

echo "<body style='font-family: sans-serif; background: #f4f7f6; padding: 40px;'>";
echo "<div style='max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 12px; shadow: 0 4px 6px rgba(0,0,0,0.1); border: 1px solid #e1e4e8;'>";
echo "<h1 style='color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>🚀 Patch de Atualização V6</h1>";

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    // --- 1. Database Update ---
    echo "<h3>1. Verificando Banco de Dados...</h3><ul>";
    $queries = [
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS razao_social VARCHAR(150)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS inscricao_estadual VARCHAR(30)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS crt TINYINT(1) DEFAULT 1",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS tipo_emissao VARCHAR(50) DEFAULT '1'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS finalidade VARCHAR(50) DEFAULT '1'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS ind_pres VARCHAR(50) DEFAULT '1'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS tipo_impressao VARCHAR(50) DEFAULT '4'",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS serie_nfce INT DEFAULT 1",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS ultimo_numero_nfce INT DEFAULT 0",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS logradouro VARCHAR(150)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS numero_endereco VARCHAR(20)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS complemento VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS bairro VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cep VARCHAR(20)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS cidade VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS codigo_municipio VARCHAR(10)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS uf VARCHAR(2)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS codigo_uf VARCHAR(2)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS telefone VARCHAR(20)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS email VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS csc VARCHAR(100)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS csc_id VARCHAR(50)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS ambiente TINYINT(1) DEFAULT 2",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS certificado_pfx VARCHAR(255)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS certificado_senha VARCHAR(255)",
        "ALTER TABLE filiais ADD COLUMN IF NOT EXISTS nome VARCHAR(255)"
    ];

    foreach($queries as $q) {
        $db->exec($q);
        echo "<li style='color: #27ae60; font-size: 0.9rem;'>OK: " . htmlspecialchars(substr($q, 0, 50)) . "...</li>";
    }
    echo "</ul>";

    // --- 2. File Updates ---
    echo "<h3>2. Atualizando Arquivos do Sistema...</h3><ul>";

    $files = [
        'src/App/Models/Filial.php' => <<<'EOT'
<?php
namespace App\Models;

class Filial extends BaseModel {
    protected $table = 'filiais';

    public function getAllBranches($id = null) {
        if ($id) {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetchAll();
        }
        return $this->all();
    }

    public function save($data) {
        if (isset($data['id']) && !empty($data['id'])) {
            $sql = "UPDATE filiais SET 
                razao_social = ?, 
                inscricao_estadual = ?, 
                crt = ?, 
                tipo_emissao = ?, 
                finalidade = ?, 
                ind_pres = ?, 
                tipo_impressao = ?, 
                serie_nfce = ?, 
                ultimo_numero_nfce = ?, 
                logradouro = ?, 
                numero_endereco = ?, 
                complemento = ?, 
                bairro = ?, 
                cep = ?, 
                cidade = ?, 
                codigo_municipio = ?, 
                uf = ?, 
                codigo_uf = ?,
                telefone = ?, 
                email = ?, 
                csc = ?,
                csc_id = ?, 
                ambiente = ?, 
                certificado_pfx = ?, 
                certificado_senha = ?,
                nome = ?
                WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['razao_social'], $data['inscricao_estadual'], $data['regime_tributario'],
                $data['tipo_emissao'], $data['finalidade'], $data['ind_pres'],
                $data['tipo_impressao'], $data['serie_nfce'], $data['ultimo_numero_nfce'],
                $data['logradouro'], $data['numero_endereco'], $data['complemento'],
                $data['bairro'], $data['cep'], $data['cidade'], $data['codigo_municipio'],
                $data['uf'], $data['codigo_uf'], $data['telefone'], $data['email'],
                $data['csc'], $data['csc_id'], $data['ambiente'],
                $data['certificado_pfx'] ?? null, $data['certificado_senha'] ?? null,
                $data['nome'], $data['id']
            ]);
        } else {
            $sql = "INSERT INTO filiais (
                razao_social, inscricao_estadual, crt, tipo_emissao, finalidade, 
                ind_pres, tipo_impressao, serie_nfce, ultimo_numero_nfce, logradouro, 
                numero_endereco, complemento, bairro, cep, cidade, codigo_municipio, 
                uf, codigo_uf, telefone, email, csc, csc_id, ambiente, 
                certificado_pfx, certificado_senha, nome
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['razao_social'], $data['inscricao_estadual'], $data['regime_tributario'],
                $data['tipo_emissao'], $data['finalidade'], $data['ind_pres'],
                $data['tipo_impressao'], $data['serie_nfce'], $data['ultimo_numero_nfce'],
                $data['logradouro'], $data['numero_endereco'], $data['complemento'],
                $data['bairro'], $data['cep'], $data['cidade'], $data['codigo_municipio'],
                $data['uf'], $data['codigo_uf'], $data['telefone'], $data['email'],
                $data['csc'], $data['csc_id'], $data['ambiente'],
                $data['certificado_pfx'] ?? null, $data['certificado_senha'] ?? null,
                $data['nome']
            ]);
        }
    }
}
EOT
,
        'views/branches.view.php' => <<<'EOT'
<!-- Page Header / Actions -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-network-wired me-2"></i>Unidades Operacionais</h6>
        <button class="btn btn-primary fw-bold" onclick="newBranch()">
            <i class="fas fa-plus-circle me-2"></i>Expandir Operação
        </button>
    </div>
</div>

<!-- Branches Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" width="100">Cód.</th>
                        <th>Nome da Unidade</th>
                        <th>CNPJ / Identificação</th>
                        <th>Tipo / Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr>
                        <td class="ps-4 small fw-bold text-muted">#<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px;">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="fw-bold text-dark"><?= $b['nome'] ?></div>
                            </div>
                        </td>
                        <td class="small fw-bold text-muted"><?= $b['cnpj'] ?? '---' ?></td>
                        <td>
                            <?php if ($b['principal']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 border border-success border-opacity-25">SEDE MATRIZ</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 border border-secondary border-opacity-25">FILIAL ATIVA</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-light border" title="Configurar Unidade" onclick="editBranch(<?= htmlspecialchars(json_encode($b)) ?>)">
                                    <i class="fas fa-cog text-primary"></i>
                                </button>
                                <button class="btn btn-light border text-danger" title="Suspender Atividades">
                                    <i class="fas fa-pause-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Fiscal Configuration Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" action="filiais.php?action=save" method="POST" enctype="multipart/form-data">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Configuração Fiscal da Unidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="branch_id">
                <input type="hidden" name="nome" id="branch_nome_hidden">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Razão Social (Contrato Social)</label>
                        <input type="text" name="razao_social" id="branch_razao_social" class="form-control shadow-sm text-uppercase" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nome Fantasia (Apelido)</label>
                        <input type="text" name="nome_fantasia" id="branch_nome_fantasia" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">CNPJ</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="cnpj" id="branch_cnpj" class="form-control shadow-sm" placeholder="00.000.000/0000-00" onkeyup="maskCnpj(this)">
                            <button type="button" class="btn btn-outline-primary fw-bold" onclick="consultarCNPJ()">
                                <i class="fas fa-search me-1"></i> Buscar
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Inscrição Estadual</label>
                        <input type="text" name="inscricao_estadual" id="branch_ie" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Inscrição Municipal</label>
                        <input type="text" name="inscricao_municipal" id="branch_im" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Ambiente SEFAZ</label>
                        <select name="ambiente" id="branch_ambiente" class="form-select shadow-sm">
                            <option value="2">Homologação</option>
                            <option value="1">Produção</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Regime Tributário</label>
                        <select name="regime_tributario" id="branch_regime_tributario" class="form-select shadow-sm">
                            <option value="1">1 - Simples Nacional</option>
                            <option value="2">2 - Simples Excesso</option>
                            <option value="3">3 - Regime Normal</option>
                        </select>
                    </div>
                    <div class="col-md-3 mt-2">
                        <label class="form-label small fw-bold">Telefone</label>
                        <input type="text" name="telefone" id="branch_telefone" class="form-control shadow-sm" onkeyup="maskTel(this)">
                    </div>
                    <div class="col-md-3 mt-2">
                        <label class="form-label small fw-bold">E-mail (NFE)</label>
                        <input type="email" name="email" id="branch_email" class="form-control shadow-sm">
                    </div>
                    
                    <div class="col-12 mt-4 mb-2"><h6 class="fw-bold text-primary small border-bottom pb-2">Endereço Fiscal (Identico a SEFAZ)</h6></div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">CEP</label>
                        <input type="text" name="cep" id="branch_cep" class="form-control shadow-sm" onkeyup="maskCep(this)">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-bold">Logradouro</label>
                        <input type="text" name="logradouro" id="branch_logradouro" class="form-control shadow-sm" placeholder="Av / Rua / Travessa">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Nº</label>
                        <input type="text" name="numero_endereco" id="branch_numero_endereco" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Complemento</label>
                        <input type="text" name="complemento" id="branch_complemento" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Bairro</label>
                        <input type="text" name="bairro" id="branch_bairro" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Cidade</label>
                        <input type="text" name="cidade" id="branch_cidade" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">UF</label>
                        <select name="uf" id="branch_uf" class="form-select shadow-sm" onchange="updateBranchIbge()">
                            <option value="">--</option>
                            <?php 
                            $ufs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach($ufs as $uf): ?>
                            <option value="<?= $uf ?>"><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Código UF (IBGE)</label>
                        <input type="text" name="codigo_uf" id="branch_codigo_uf" class="form-control shadow-sm bg-light" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Código do Município (IBGE)</label>
                        <input type="text" name="codigo_municipio" id="branch_codigo_municipio" class="form-control shadow-sm" placeholder="ex: 3550308" maxlength="7" pattern="[0-9]{7}">
                    </div>

                    <div class="col-12 mt-4 mb-2"><h6 class="fw-bold text-primary small border-bottom pb-2">Configurações Avançadas (NF-e / NFC-e)</h6></div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tipo de Emissão</label>
                        <select name="tipo_emissao" id="branch_tipo_emissao" class="form-select shadow-sm">
                            <option value="1">1 - Normal</option>
                            <option value="9">9 - Contingência Offline</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Finalidade</label>
                        <select name="finalidade" id="branch_finalidade" class="form-select shadow-sm">
                            <option value="1">1 - Normal</option>
                            <option value="2">2 - Complementar</option>
                            <option value="3">3 - Ajuste</option>
                            <option value="4">4 - Devolução</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Ind. Presença</label>
                        <select name="ind_pres" id="branch_ind_pres" class="form-select shadow-sm">
                            <option value="1">1 - Operação Presencial</option>
                            <option value="4">4 - Entrega a domicílio</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tipo Impressão</label>
                        <select name="tipo_impressao" id="branch_tipo_impressao" class="form-select shadow-sm">
                            <option value="4">4 - DANFE NFC-e</option>
                            <option value="5">5 - DANFE em Mensagem</option>
                        </select>
                    </div>
                    <div class="col-md-2 mt-3">
                        <label class="form-label small fw-bold">Série NFC-e</label>
                        <input type="number" name="serie_nfce" id="branch_serie_nfce" class="form-control shadow-sm" value="1">
                    </div>
                    <div class="col-md-3 mt-3">
                        <label class="form-label small fw-bold">Último número</label>
                        <input type="number" name="ultimo_numero_nfce" id="branch_ultimo_numero_nfce" class="form-control shadow-sm" value="0">
                    </div>
                    <div class="col-md-3 mt-3">
                        <label class="form-label small fw-bold">CSC (Token)</label>
                        <input type="text" name="csc" id="branch_csc" class="form-control shadow-sm" placeholder="AABBCC...">
                    </div>
                    <div class="col-md-4 mt-3">
                        <label class="form-label small fw-bold">CSC ID</label>
                        <input type="text" name="csc_id" id="branch_csc_id" class="form-control shadow-sm" placeholder="000001">
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label small fw-bold">Certificado Digital (A1 .pfx)</label>
                        <input type="file" name="certificado" class="form-control shadow-sm">
                        <div id="cert_info" class="extra-small text-success mt-1 fw-bold"></div>
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label small fw-bold">Senha do Certificado</label>
                        <input type="password" name="certificado_senha" id="branch_cert_senha" class="form-control shadow-sm" placeholder="Deixe em branco para manter">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Salvar Configuração</button>
            </div>
        </form>
    </div>
</div>

<script>
function newBranch() {
    const modal = new bootstrap.Modal(document.getElementById('branchModal'));
    document.getElementById('branch_id').value = '';
    document.getElementById('branch_nome_hidden').value = '';
    document.getElementById('branch_razao_social').value = '';
    document.getElementById('branch_nome_fantasia').value = '';
    document.getElementById('branch_ambiente').value = 2;
    document.getElementById('branch_regime_tributario').value = 1;
    document.getElementById('branch_cnpj').value = '';
    document.getElementById('branch_ie').value = '';
    document.getElementById('branch_im').value = '';
    
    document.getElementById('branch_tipo_emissao').value = 1;
    document.getElementById('branch_finalidade').value = 1;
    document.getElementById('branch_ind_pres').value = 1;
    document.getElementById('branch_tipo_impressao').value = 4;
    document.getElementById('branch_serie_nfce').value = 1;
    document.getElementById('branch_ultimo_numero_nfce').value = 0;

    document.getElementById('branch_telefone').value = '';
    document.getElementById('branch_email').value = '';
    document.getElementById('branch_logradouro').value = '';
    document.getElementById('branch_numero_endereco').value = '';
    document.getElementById('branch_complemento').value = '';
    document.getElementById('branch_bairro').value = '';
    document.getElementById('branch_cidade').value = '';
    document.getElementById('branch_codigo_municipio').value = '';
    document.getElementById('branch_uf').value = '';
    document.getElementById('branch_codigo_uf').value = '';
    document.getElementById('branch_cep').value = '';
    document.getElementById('branch_csc').value = '';
    document.getElementById('branch_csc_id').value = '';
    document.getElementById('branch_cert_senha').value = '';
    document.getElementById('cert_info').innerText = 'Novo certificado (.pfx) será carregado';
    modal.show();
}

function editBranch(branch) {
    const modal = new bootstrap.Modal(document.getElementById('branchModal'));
    document.getElementById('branch_id').value = branch.id;
    document.getElementById('branch_nome_hidden').value = branch.nome || '';
    document.getElementById('branch_razao_social').value = branch.razao_social || '';
    document.getElementById('branch_nome_fantasia').value = branch.nome_fantasia || '';
    document.getElementById('branch_ambiente').value = branch.ambiente || 2;
    document.getElementById('branch_regime_tributario').value = branch.regime_tributario || 1;
    document.getElementById('branch_cnpj').value = branch.cnpj || '';
    document.getElementById('branch_ie').value = branch.inscricao_estadual || '';
    document.getElementById('branch_im').value = branch.inscricao_municipal || '';

    document.getElementById('branch_tipo_emissao').value = branch.tipo_emissao || 1;
    document.getElementById('branch_finalidade').value = branch.finalidade || 1;
    document.getElementById('branch_ind_pres').value = branch.ind_pres || 1;
    document.getElementById('branch_tipo_impressao').value = branch.tipo_impressao || 4;
    document.getElementById('branch_serie_nfce').value = branch.serie_nfce || 1;
    document.getElementById('branch_ultimo_numero_nfce').value = branch.ultimo_numero_nfce || 0;

    document.getElementById('branch_telefone').value = branch.telefone || '';
    document.getElementById('branch_email').value = branch.email || '';
    document.getElementById('branch_logradouro').value = branch.logradouro || '';
    document.getElementById('branch_numero_endereco').value = branch.numero_endereco || branch.numero || '';
    document.getElementById('branch_complemento').value = branch.complemento || '';
    document.getElementById('branch_bairro').value = branch.bairro || '';
    document.getElementById('branch_cidade').value = branch.cidade || branch.municipio || '';
    document.getElementById('branch_codigo_municipio').value = branch.codigo_municipio || '';
    document.getElementById('branch_uf').value = branch.uf || '';
    document.getElementById('branch_codigo_uf').value = branch.codigo_uf || '';
    document.getElementById('branch_cep').value = branch.cep || '';
    document.getElementById('branch_csc').value = branch.csc || branch.csc_token || '';
    document.getElementById('branch_csc_id').value = branch.csc_id || '';
    document.getElementById('branch_cert_senha').value = ''; // Always empty for security
    
    document.getElementById('cert_info').innerText = branch.certificado_pfx ? 'Certificado atual: ' + branch.certificado_pfx : 'Nenhum certificado carregado';
    
    modal.show();
}

// Ensure the system name 'nome' stays in sync with 'nome_fantasia'
document.getElementById('branch_nome_fantasia').addEventListener('input', function() {
    document.getElementById('branch_nome_hidden').value = this.value;
});

const MAP_UF_IBGE = {
    "RO": 11, "AC": 12, "AM": 13, "RR": 14, "PA": 15, "AP": 16, "TO": 17, "MA": 21, "PI": 22, "CE": 23, "RN": 24, "PB": 25, "PE": 26, "AL": 27, "SE": 28, "BA": 29, "MG": 31, "ES": 32, "RJ": 33, "SP": 35, "PR": 41, "SC": 42, "RS": 43, "MS": 50, "MT": 51, "GO": 52, "DF": 53
};

function updateBranchIbge() {
    const uf = document.getElementById('branch_uf').value;
    const cod = MAP_UF_IBGE[uf] || '';
    document.getElementById('branch_codigo_uf').value = cod;
}

function maskCnpj(i) {
    let v = i.value.replace(/\D/g, '').slice(0, 14);
    if (v.length > 2) v = v.slice(0, 2) + '.' + v.slice(2);
    if (v.length > 6) v = v.slice(0, 6) + '.' + v.slice(6);
    if (v.length > 10) v = v.slice(0, 10) + '/' + v.slice(10);
    if (v.length > 15) v = v.slice(0, 15) + '-' + v.slice(15);
    i.value = v;
}

function maskCep(i) {
    let v = i.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
    i.value = v;
}

function maskTel(i) {
    let v = i.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 2) v = '(' + v.slice(0, 2) + ') ' + v.slice(2);
    if (v.length > 9) v = v.slice(0, 9) + '-' + v.slice(9);
    i.value = v;
}

async function consultarCNPJ() {
    const cnpjInput = document.getElementById('branch_cnpj');
    const cnpj = cnpjInput.value.replace(/\D/g, '');
    const btn = cnpjInput.nextElementSibling;
    const originalText = btn.innerHTML;
    
    if (cnpj.length !== 14) {
        alert('Por favor, informe um CNPJ válido com 14 dígitos.');
        return;
    }

    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';
    btn.disabled = true;

    try {
        const response = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`);
        if (!response.ok) {
            throw new Error('Falha na consulta.');
        }
        
        const data = await response.json();
        
        // Auto-Fill Fields
        document.getElementById('branch_razao_social').value = data.razao_social || '';
        document.getElementById('branch_nome_fantasia').value = data.nome_fantasia || data.razao_social || '';
        document.getElementById('branch_nome_hidden').value = data.nome_fantasia || data.razao_social || '';
        document.getElementById('branch_telefone').value = data.ddd_telefone_1 || '';
        document.getElementById('branch_email').value = data.email || '';
        
        document.getElementById('branch_cep').value = data.cep || '';
        document.getElementById('branch_logradouro').value = data.logradouro || '';
        document.getElementById('branch_numero_endereco').value = data.numero || '';
        document.getElementById('branch_complemento').value = data.complemento || '';
        document.getElementById('branch_bairro').value = data.bairro || '';
        document.getElementById('branch_cidade').value = data.municipio || '';
        document.getElementById('branch_uf').value = data.uf || '';
        document.getElementById('branch_codigo_municipio').value = data.codigo_municipio || '';
        
        updateBranchIbge();

        btn.innerHTML = '<i class="fas fa-check text-success"></i> OK';
        setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 2000);
        
    } catch (error) {
        alert('Erro: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>
EOT
,
        'src/App/Services/SefazXmlService.php' => <<<'EOT'
<?php
namespace App\Services;

use DOMDocument;
use Exception;

class SefazXmlService extends BaseService {
    
    public function generateNFCe(array $sale, array $fiscal) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $nfe = $dom->createElementNS('http://www.portalfiscal.inf.br/nfe', 'NFe');
        $dom->appendChild($nfe);

        $infNFe = $dom->createElement('infNFe');
        
        // ID: NFe + cUF(2) + AAMM(4) + CNPJ(14) + mod(2) + serie(3) + nNF(9) + tpEmis(1) + cNF(8) + cDV(1)
        $cUF = $fiscal['codigo_uf'] ?? "35"; 
        $tpAmb = ($fiscal['ambiente'] == 1) ? '1' : '2';
        $mod = "65"; // NFC-e
        $serie = "001";
        $nNF = str_pad($sale['id'], 9, '0', STR_PAD_LEFT);
        $tpEmis = "1"; // Normal
        $cNF = str_pad(rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        $dhEmi = date('Y-m-d\TH:i:sP');
        
        $cnpj = preg_replace('/[^0-9]/', '', $fiscal['cnpj']);
        $aamm = date('ym');
        
        $chaveSemDV = $cUF . $aamm . $cnpj . $mod . $serie . $nNF . $tpEmis . $cNF;
        $cDV = $this->calculateDV($chaveSemDV);
        $chave = $chaveSemDV . $cDV;
        
        $infNFe->setAttribute('Id', 'NFe' . $chave);
        $infNFe->setAttribute('versao', '4.00');
        $nfe->appendChild($infNFe);

        // 1. ide
        $ide = $dom->createElement('ide');
        $ide->appendChild($dom->createElement('cUF', $cUF));
        $ide->appendChild($dom->createElement('cNF', $cNF));
        $ide->appendChild($dom->createElement('natOp', 'VENDA'));
        $ide->appendChild($dom->createElement('mod', $mod));
        $ide->appendChild($dom->createElement('serie', (int)$serie));
        $ide->appendChild($dom->createElement('nNF', (int)$nNF));
        $ide->appendChild($dom->createElement('dhEmi', $dhEmi));
        $ide->appendChild($dom->createElement('tpNF', '1')); // Saída
        $ide->appendChild($dom->createElement('idDest', '1')); // Interna
        $ide->appendChild($dom->createElement('cMunFG', $fiscal['codigo_municipio'] ?? '3550308')); 
        $ide->appendChild($dom->createElement('tpImp', '4')); // DANFE NFC-e
        $ide->appendChild($dom->createElement('tpEmis', $tpEmis));
        $ide->appendChild($dom->createElement('cDV', $cDV));
        $ide->appendChild($dom->createElement('tpAmb', $tpAmb));
        $ide->appendChild($dom->createElement('finNFe', '1')); // Normal
        $ide->appendChild($dom->createElement('indFinal', '1')); // Consumidor Final
        $ide->appendChild($dom->createElement('indPres', '1')); // Presencial
        $ide->appendChild($dom->createElement('procEmi', '0')); // Aplicativo do Contribuinte
        $ide->appendChild($dom->createElement('verProc', 'ERP_ELET_V1'));
        $infNFe->appendChild($ide);

        // 2. emit
        $emit = $dom->createElement('emit');
        $emit->appendChild($dom->createElement('CNPJ', $cnpj));
        $emit->appendChild($dom->createElement('xNome', $this->clearText($fiscal['nome'])));
        $enderEmit = $dom->createElement('enderEmit');
        $enderEmit->appendChild($dom->createElement('xLgr', $this->clearText($fiscal['logradouro'] ?? 'Logradouro')));
        $enderEmit->appendChild($dom->createElement('nro', $fiscal['numero_endereco'] ?? 'S/N'));
        $enderEmit->appendChild($dom->createElement('xBairro', $this->clearText($fiscal['bairro'] ?? 'Bairro')));
        $enderEmit->appendChild($dom->createElement('cMun', $fiscal['codigo_municipio'] ?? '3550308'));
        $enderEmit->appendChild($dom->createElement('xMun', $this->clearText($fiscal['cidade'] ?? 'SAO PAULO')));
        $enderEmit->appendChild($dom->createElement('UF', $fiscal['uf'] ?? 'SP'));
        $enderEmit->appendChild($dom->createElement('CEP', preg_replace('/[^0-9]/', '', $fiscal['cep'] ?? '01001000')));
        $enderEmit->appendChild($dom->createElement('cPais', '1058'));
        $enderEmit->appendChild($dom->createElement('xPais', 'BRASIL'));
        $emit->appendChild($enderEmit);
        $emit->appendChild($dom->createElement('IE', preg_replace('/[^0-9]/', '', $fiscal['inscricao_estadual'] ?? '')));
        $emit->appendChild($dom->createElement('CRT', $fiscal['crt'] ?? '1'));
        $infNFe->appendChild($emit);

        // 3. det (Items)
        $nItem = 1;
        foreach ($sale['items'] as $item) {
            $det = $dom->createElement('det');
            $det->setAttribute('nItem', $nItem++);
            
            $prod = $dom->createElement('prod');
            $prod->appendChild($dom->createElement('cProd', $item['produto_id']));
            $prod->appendChild($dom->createElement('cEAN', 'SEM GTIN'));
            $prod->appendChild($dom->createElement('xProd', $this->clearText($item['nome'])));
            $prod->appendChild($dom->createElement('NCM', $item['ncm'] ?: '00000000'));
            $prod->appendChild($dom->createElement('CFOP', $item['cfop_interno'] ?: '5102'));
            $prod->appendChild($dom->createElement('uCom', $item['unidade'] ?: 'UN'));
            $prod->appendChild($dom->createElement('qCom', number_format($item['quantidade'], 4, '.', '')));
            $prod->appendChild($dom->createElement('vUnCom', number_format($item['preco_unitario'], 10, '.', '')));
            $prod->appendChild($dom->createElement('vProd', number_format($item['quantidade'] * $item['preco_unitario'], 2, '.', '')));
            $prod->appendChild($dom->createElement('cEANTrib', 'SEM GTIN'));
            $prod->appendChild($dom->createElement('uTrib', $item['unidade'] ?: 'UN'));
            $prod->appendChild($dom->createElement('qTrib', number_format($item['quantidade'], 4, '.', '')));
            $prod->appendChild($dom->createElement('vUnTrib', number_format($item['preco_unitario'], 10, '.', '')));
            $prod->appendChild($dom->createElement('indTot', '1'));
            $det->appendChild($prod);
            
            $imposto = $dom->createElement('imposto');
            $icms = $dom->createElement('ICMS');
            $icmsSn = $dom->createElement('ICMSSN102'); 
            $icmsSn->appendChild($dom->createElement('orig', $item['origem'] ?? '0'));
            $icmsSn->appendChild($dom->createElement('CSOSN', '102'));
            $icms->appendChild($icmsSn);
            $imposto->appendChild($icms);
            
            $pis = $dom->createElement('PIS');
            $pisOutr = $dom->createElement('PISAliq');
            $pisOutr->appendChild($dom->createElement('CST', '01'));
            $pisOutr->appendChild($dom->createElement('vBC', '0.00'));
            $pisOutr->appendChild($dom->createElement('pPIS', '0.00'));
            $pisOutr->appendChild($dom->createElement('vPIS', '0.00'));
            $pis->appendChild($pisOutr);
            $imposto->appendChild($pis);

            $cofins = $dom->createElement('COFINS');
            $cofinsAliq = $dom->createElement('COFINSAliq');
            $cofinsAliq->appendChild($dom->createElement('CST', '01'));
            $cofinsAliq->appendChild($dom->createElement('vBC', '0.00'));
            $cofinsAliq->appendChild($dom->createElement('pCOFINS', '0.00'));
            $cofinsAliq->appendChild($dom->createElement('vCOFINS', '0.00'));
            $cofins->appendChild($cofinsAliq);
            $imposto->appendChild($cofins);

            $det->appendChild($imposto);
            $infNFe->appendChild($det);
        }

        // 4. total
        $total = $dom->createElement('total');
        $icmsTot = $dom->createElement('ICMSTot');
        $icmsTot->appendChild($dom->createElement('vBC', '0.00'));
        $icmsTot->appendChild($dom->createElement('vICMS', '0.00'));
        $icmsTot->appendChild($dom->createElement('vICMSDeson', '0.00'));
        $icmsTot->appendChild($dom->createElement('vFCP', '0.00'));
        $icmsTot->appendChild($dom->createElement('vBCST', '0.00'));
        $icmsTot->appendChild($dom->createElement('vST', '0.00'));
        $icmsTot->appendChild($dom->createElement('vFCPST', '0.00'));
        $icmsTot->appendChild($dom->createElement('vFCPSTRet', '0.00'));
        $icmsTot->appendChild($dom->createElement('vProd', number_format($sale['valor_total'] + ($sale['desconto_total'] ?? 0), 2, '.', '')));
        $icmsTot->appendChild($dom->createElement('vFrete', '0.00'));
        $icmsTot->appendChild($dom->createElement('vSeg', '0.00'));
        $icmsTot->appendChild($dom->createElement('vDesc', number_format($sale['desconto_total'] ?? 0, 2, '.', '')));
        $icmsTot->appendChild($dom->createElement('vII', '0.00'));
        $icmsTot->appendChild($dom->createElement('vIPI', '0.00'));
        $icmsTot->appendChild($dom->createElement('vIPIDevol', '0.00'));
        $icmsTot->appendChild($dom->createElement('vPIS', '0.00'));
        $icmsTot->appendChild($dom->createElement('vCOFINS', '0.00'));
        $icmsTot->appendChild($dom->createElement('vOutro', '0.00'));
        $icmsTot->appendChild($dom->createElement('vNF', number_format($sale['valor_total'], 2, '.', '')));
        $total->appendChild($icmsTot);
        $infNFe->appendChild($total);

        // 5. transp
        $transp = $dom->createElement('transp');
        $transp->appendChild($dom->createElement('modFrete', '9')); 
        $infNFe->appendChild($transp);

        // 6. pag
        $pag = $dom->createElement('pag');
        $detPag = $dom->createElement('detPag');
        $detPag->appendChild($dom->createElement('tPag', $this->mapPaymentMethod($sale['forma_pagamento'])));
        $detPag->appendChild($dom->createElement('vPag', number_format($sale['valor_total'], 2, '.', '')));
        $pag->appendChild($detPag);
        $infNFe->appendChild($pag);

        return [
            'xml' => $dom->saveXML(),
            'chave' => $chave
        ];
    }

    private function calculateDV($key) {
        $factors = [2, 3, 4, 5, 6, 7, 8, 9];
        $sum = 0; $i = strlen($key) - 1; $f = 0;
        while ($i >= 0) {
            $sum += (int)$key[$i] * $factors[$f];
            $i--; $f++;
            if ($f > 7) $f = 0;
        }
        $remainder = $sum % 11;
        if ($remainder == 0 || $remainder == 1) return 0;
        return 11 - $remainder;
    }

    private function clearText($text) {
        return preg_replace('/[^a-zA-Z0-9 ]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', (string)$text));
    }

    private function mapPaymentMethod($method) {
        $map = ['dinheiro' => '01', 'credito' => '03', 'debito' => '04', 'pix' => '17', 'fiado' => '99'];
        return $map[strtolower($method)] ?? '99';
    }
}
EOT
,
        'src/App/Services/FiscalService.php' => <<<'EOT'
<?php
namespace App\Services;

use App\Config\Database;
use Exception;

class FiscalService extends BaseService {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance()->getConnection();
    }

    private function getFiscalConfig($branchId) {
        $branch = $this->getBranchData($branchId);
        if (!$branch) throw new Exception("Filial ID $branchId não encontrada.");

        $stmt = $this->db->query("SELECT * FROM sefaz_config LIMIT 1");
        $global = $stmt->fetch();

        if ($global && !empty($global['certificado_path'])) {
            return [
                'cnpj' => $branch['cnpj'],
                'certificado_pfx' => $global['certificado_path'],
                'certificado_senha' => base64_decode($global['certificado_senha']),
                'ambiente' => $global['ambiente'] == 'producao' ? 1 : 2,
                'nome' => $branch['nome'],
                'codigo_uf' => $branch['codigo_uf'] ?? $global['codigo_uf'] ?? '35',
                'codigo_municipio' => $branch['codigo_municipio'] ?? $global['codigo_municipio'] ?? '3550308',
                'uf' => $branch['uf'] ?? $global['uf'] ?? 'SP',
                'cidade' => $branch['cidade'] ?? $global['cidade'] ?? 'SAO PAULO',
                'bairro' => $branch['bairro'] ?? $global['bairro'] ?? '',
                'cep' => $branch['cep'] ?? $global['cep'] ?? '',
                'logradouro' => $branch['logradouro'] ?? $global['logradouro'] ?? '',
                'numero_endereco' => $branch['numero_endereco'] ?? $global['numero_endereco'] ?? 'S/N',
                'inscricao_estadual' => $branch['inscricao_estadual'] ?? $global['inscricao_estadual'] ?? '',
                'crt' => $branch['crt'] ?? $global['crt'] ?? '1'
            ];
        }

        return [
            'cnpj' => $branch['cnpj'],
            'certificado_pfx' => $branch['certificado_pfx'] ?? null,
            'certificado_senha' => !empty($branch['certificado_senha']) ? base64_decode($branch['certificado_senha']) : '',
            'ambiente' => $branch['ambiente'] ?? 2,
            'nome' => $branch['nome'],
            'codigo_uf' => $branch['codigo_uf'] ?? '35',
            'codigo_municipio' => $branch['codigo_municipio'] ?? '3550308',
            'uf' => $branch['uf'] ?? 'SP',
            'cidade' => $branch['cidade'] ?? 'SAO PAULO',
            'bairro' => $branch['bairro'] ?? '',
            'cep' => $branch['cep'] ?? '',
            'logradouro' => $branch['logradouro'] ?? '',
            'numero_endereco' => $branch['numero_endereco'] ?? 'S/N',
            'inscricao_estadual' => $branch['inscricao_estadual'] ?? '',
            'crt' => $branch['crt'] ?? '1'
        ];
    }

    public function issueNFCe($vendaId) {
        try {
            $sale = $this->getSaleData($vendaId);
            $fiscal = $this->getFiscalConfig($sale['filial_id']);

            if (empty($fiscal['cnpj']) || empty($fiscal['certificado_pfx'])) {
                throw new Exception("Configuração fiscal incompleta.");
            }

            $xml = $this->generateXML($sale, $fiscal, 'nfce');
            $signedXml = $this->signXML($xml, $fiscal);
            $response = $this->transmitToSEFAZ($signedXml, $fiscal, 'nfce');
            $this->saveFiscalRecord($vendaId, 'nfce', $response);

            return [
                'success' => true,
                'status' => $response['status'],
                'protocolo' => $response['protocolo'],
                'chave' => $response['chave']
            ];
        } catch (Exception $e) {
            $this->logAction('fiscal_error', 'vendas', $vendaId, null, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function getSaleData($vendaId) {
        $sale = $this->db->prepare("SELECT * FROM vendas WHERE id = ?");
        $sale->execute([$vendaId]);
        $data = $sale->fetch();

        $items = $this->db->prepare("
            SELECT vi.*, p.nome, p.ncm, p.cest, p.unidade, p.origem, p.csosn, p.cfop_interno, p.aliquota_icms
            FROM venda_itens vi
            JOIN produtos p ON vi.produto_id = p.id
            WHERE vi.venda_id = ?
        ");
        $items->execute([$vendaId]);
        $data['items'] = $items->fetchAll();
        return $data;
    }

    private function getBranchData($branchId) {
        $stmt = $this->db->prepare("SELECT * FROM filiais WHERE id = ?");
        $stmt->execute([$branchId]);
        return $stmt->fetch();
    }

    private function generateXML($sale, $fiscal, $type = 'nfce') {
        $xmlService = new SefazXmlService();
        $result = $xmlService->generateNFCe($sale, $fiscal);
        return $result['xml'];
    }

    private function signXML($xml, $fiscal) {
        $signer = new SefazSigner();
        $pfxPath = dirname(__DIR__, 3) . "/storage/certificados/" . $fiscal['certificado_pfx'];
        $password = $fiscal['certificado_senha'];
        return $signer->signXML($xml, $pfxPath, $password);
    }

    private function transmitToSEFAZ($signedXml, $fiscal, $type = 'nfce') {
        $soapClient = new SefazSoapClient();
        try {
            $responseXml = $soapClient->call('nfce_autorizacao', $signedXml, $fiscal);
            return $this->parseSefazResponse($responseXml);
        } catch (Exception $e) {
            $this->logAction('sefaz_comm_error', 'vendas', null, null, ['error' => $e->getMessage()]);
            throw new Exception("Falha SEFAZ: " . $e->getMessage());
        }
    }

    private function parseSefazResponse($xmlStr) {
        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlStr);
        $xml = simplexml_load_string($cleanXml);
        $ret = $xml->xpath('//retEnviNFe');
        if (empty($ret)) throw new Exception("Resposta desconhecida.");
        
        $data = $ret[0];
        $cStat = (string)$data->cStat;
        if ($cStat != '103' && $cStat != '104') throw new Exception("Rejeitada: [$cStat] " . $data->xMotivo);

        return [
            'status' => 'autorizada',
            'protocolo' => (string)$data->infRec->nRec ?? 'N/A',
            'chave' => (string)$data->protNFe->infProt->chNFe ?? 'N/A'
        ];
    }

    public function testConnection($branchId) {
        $branch = $this->getBranchData($branchId);
        $fiscal = $this->getFiscalConfig($branchId);
        $soapClient = new SefazSoapClient();
        
        $uf = "35";
        if (isset($branch['uf'])) {
            $estados = ['RO'=>'11','AC'=>'12','AM'=>'13','RR'=>'14','PA'=>'15','AP'=>'16','TO'=>'17','MA'=>'21','PI'=>'22','CE'=>'23','RN'=>'24','PB'=>'25','PE'=>'26','AL'=>'27','SE'=>'28','BA'=>'29','MG'=>'31','ES'=>'32','RJ'=>'33','SP'=>'35','PR'=>'41','SC'=>'42','RS'=>'43','MS'=>'50','MT'=>'51','GO'=>'52','DF'=>'53'];
            $uf = $estados[$branch['uf']] ?? '35';
        }

        $xml = '<consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>' . $fiscal['ambiente'] . '</tpAmb><cUF>' . $uf . '</cUF><xServ>STATUS</xServ></consStatServ>';
        $responseXml = $soapClient->call('sefaz_status', $xml, $fiscal);
        
        $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseXml);
        $res = @simplexml_load_string($cleanXml);
        $nodes = $res->xpath('//retConsStatServ');
        if (empty($nodes)) $nodes = $res->xpath('//*[local-name()="retConsStatServ"]');
        
        $retStatus = !empty($nodes) ? $nodes[0] : null;
        if (!$retStatus) throw new Exception("Falha no status.");

        return [
            'success' => true,
            'status' => (string)$retStatus->cStat,
            'motivo' => (string)$retStatus->xMotivo,
            'ambiente' => ($fiscal['ambiente'] == 1) ? 'Produção' : 'Homologação'
        ];
    }

    private function saveFiscalRecord($vendaId, $type, $response) {
        $stmt = $this->db->prepare("INSERT INTO notas_fiscais (venda_id, tipo, chave_acesso, status, protocolo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$vendaId, $type, $response['chave'], $response['status'], $response['protocolo']]);
    }
}
EOT
    ];

    foreach($files as $path => $content) {
        $fullPath = __DIR__ . '/' . $path;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        
        if (file_put_contents($fullPath, $content)) {
            echo "<li style='color: #2980b9;'>Arquivo Atualizado: <strong>" . htmlspecialchars($path) . "</strong></li>";
        } else {
            echo "<li style='color: #e74c3c;'>Erro ao gravar: " . htmlspecialchars($path) . "</li>";
        }
    }

    echo "</ul>";
    echo "<h2 style='color: #27ae60;'>✔️ Tudo pronto! O sistema foi atualizado na nuvem com sucesso.</h2>";
    echo "<p>Agora você pode limpar o cache (Ctrl+F5) e testar o cadastro de filiais.</p>";
    echo "<hr><p style='font-size: 0.8rem; color: #7f8c8d;'>Para sua segurança, exclua este arquivo (<code>install_v6_nfce.php</code>) após a conclusão.</p>";
    echo "<a href='filiais.php' style='display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;'>Ir para Filiais</a>";

} catch(Exception $e) {
    echo "<h2 style='color: #e74c3c;'>❌ Erro Fatal: " . $e->getMessage() . "</h2>";
}

echo "</div></body>";
