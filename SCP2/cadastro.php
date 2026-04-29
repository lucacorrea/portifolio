<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCP 2.0 - Cadastro de Processo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="v2/assets/css/style.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        input, select, textarea {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.8rem;
            color: white;
            outline: none;
            transition: border-color 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 10px var(--primary-glow);
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
            color: var(--primary);
        }
    </style>
</head>
<body>

    <nav class="premium-nav">
        <div class="logo-group" style="cursor:pointer" onclick="location.href='v2/index.html'">
            <i class="fas fa-microchip"></i>
            <span>SCP 2.0</span>
        </div>
        <div style="display: flex; gap: 2rem; align-items: center;">
            <button class="btn-premium" style="background: var(--border);" onclick="location.href='v2/index.html'">
                <i class="fas fa-arrow-left"></i> Voltar ao Painel
            </button>
        </div>
    </nav>

    <main style="padding: 2rem; max-width: 1000px; margin: 0 auto;">
        
        <header style="margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; font-weight: 800;">Novo Registro</h1>
            <p style="color: var(--text-muted);">Preencha os dados do processo para iniciar o monitoramento.</p>
        </header>

        <div class="glass-card">
            <form id="form-cadastro-v2">
                
                <div class="section-title"><i class="fas fa-info-circle"></i> Informações Básicas</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Número do Processo (CNJ)</label>
                        <input type="text" id="numero" name="numero" placeholder="0000000-00.0000.8.04.0000" required>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Processo</label>
                        <select id="tipo_processo" name="tipo_processo">
                            <option value="CIÊNCIA">CIÊNCIA</option>
                            <option value="CUMPRIMENTO">CUMPRIMENTO</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Natureza</label>
                        <input type="text" id="natureza" name="natureza" placeholder="Ex: Execução de Título">
                    </div>
                </div>

                <div class="section-title"><i class="fas fa-calendar-alt"></i> Prazos e Datas</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Data de Ciência</label>
                        <input type="date" id="data_ciencia" name="data_ciencia">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Contagem</label>
                        <select id="tipo_contagem" name="tipo_contagem">
                            <option value="DIAS ÚTEIS">DIAS ÚTEIS</option>
                            <option value="DIAS CORRIDOS">DIAS CORRIDOS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantidade de Dias</label>
                        <input type="number" id="quantidade_dias" name="quantidade_dias" value="15">
                    </div>
                </div>

                <div class="section-title"><i class="fas fa-user-tie"></i> Atribuição</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Analisador Responsável</label>
                        <input type="text" id="analisador" name="analisador" value="<?php echo $_SESSION['usuario_nome']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Status Inicial</label>
                        <select id="status" name="status">
                            <option value="PENDENTE">PENDENTE</option>
                            <option value="URGENTE">URGENTE</option>
                            <option value="ANALISADO">ANALISADO</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label>Observações Adicionais</label>
                    <textarea id="observacoes" name="observacoes" rows="4" placeholder="Algum detalhe importante?"></textarea>
                </div>

                <div style="margin-top: 2.5rem; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="reset" class="btn-premium" style="background: rgba(248,113,113,0.1); color: #f87171;">
                        Limpar Campos
                    </button>
                    <button type="submit" class="btn-premium">
                        <i class="fas fa-save"></i> Gravar Processo
                    </button>
                </div>

            </form>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('form-cadastro-v2').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            data.acao = 'salvar';

            try {
                const response = await fetch('api.php?acao=salvar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if(result.status === 'sucesso') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Processo Gravado!',
                        text: 'O registro foi salvo com sucesso no SCP 2.0.',
                        background: '#1e293b',
                        color: '#fff',
                        confirmButtonColor: '#38bdf8'
                    }).then(() => {
                        location.href = 'v2/index.html';
                    });
                } else {
                    throw new Exception(result.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro no Cadastro',
                    text: error.message || 'Não foi possível salvar o processo.',
                    background: '#1e293b',
                    color: '#fff'
                });
            }
        });
    </script>
</body>
</html>
