/**
 * SCP - Sistema de Controle de Processos (PGM Coari/AM)
 * Módulo de Integração PROJUDI TJAM (MNI 2.2.2) - Frontend JS
 * 
 * Este arquivo fornece exemplos práticos e prontos de funções em JavaScript Puro (Vanilla JS)
 * para realizar chamadas assíncronas utilizando a API 'fetch()' para comunicar com as novas rotas do api.php.
 */

const PROJUDI = {
    /**
     * Sincroniza os prazos (citações e intimações) destinadas a Coari junto ao PROJUDI
     * Consome a rota: GET api.php?acao=sincronizar_prazos
     */
    async sincronizarPrazos() {
        console.log("[PROJUDI] Iniciando sincronização de prazos...");
        
        try {
            // Chamada assíncrona ao api.php da pasta PROSCP
            const resposta = await fetch("api.php?acao=sincronizar_prazos");
            
            if (!resposta.ok) {
                const erroData = await resposta.json();
                throw new Error(erroData.message || `Erro HTTP: ${resposta.status}`);
            }

            const resultado = await resposta.json();
            
            if (resultado.status === 'sucesso') {
                console.log("[PROJUDI] Sincronização bem sucedida:", resultado);
                
                // Exemplo de retorno:
                // {
                //    "status": "sucesso",
                //    "mensagem": "Sincronização concluída com sucesso. 2 novas intimações importadas.",
                //    "dados": { "importados": 2, "mensagens": [...] }
                // }
                
                // Dispara alertas amigáveis para o usuário na interface do SCP
                alert(resultado.mensagem);
                
                // Recarrega a tabela de processos no Dashboard caso a função esteja globalmente disponível
                if (typeof initDashboard === 'function') {
                    initDashboard();
                } else {
                    location.reload(); // Fallback seguro
                }
                
                return resultado.dados;
            } else {
                throw new Error(resultado.message || "Erro desconhecido retornado pelo backend.");
            }

        } catch (erro) {
            console.error("[PROJUDI] Falha na sincronização:", erro);
            alert(`⚠️ Falha ao Sincronizar com PROJUDI: ${erro.message}`);
            throw erro;
        }
    },

    /**
     * Envia o PDF da petição de manifestação/contestação e protocola junto ao TJAM
     * Consome a rota: POST api.php?acao=protocolar_projudi
     * 
     * @param {number|string} processoId - ID do prazo na tabela 'processos'
     * @param {File} arquivoPdf - O objeto de arquivo PDF (retornado de um input file, e.g., input.files[0])
     */
    async protocolarPeticao(processoId, arquivoPdf) {
        console.log(`[PROJUDI] Iniciando protocolo de petição para o Processo ID: ${processoId}...`);

        if (!processoId) {
            alert("⚠️ Seleção do prazo/processo é obrigatória.");
            return;
        }

        if (!arquivoPdf || arquivoPdf.type !== "application/pdf") {
            alert("⚠️ Por favor, envie um documento em formato PDF válido.");
            return;
        }

        // Como estamos enviando um arquivo binário, utilizamos FormData para codificação multipart/form-data
        const formData = new FormData();
        formData.append("processo_id", processoId);
        formData.append("arquivo", arquivoPdf);

        try {
            // Chamada assíncrona POST
            const resposta = await fetch("api.php?acao=protocolar_projudi", {
                method: "POST",
                body: formData // O fetch define automaticamente os cabeçalhos corretos para FormData
            });

            if (!resposta.ok) {
                const erroData = await resposta.json();
                throw new Error(erroData.message || `Erro HTTP: ${resposta.status}`);
            }

            const resultado = await resposta.json();

            if (resultado.status === 'sucesso') {
                console.log("[PROJUDI] Protocolo realizado com sucesso:", resultado);

                // Exemplo de retorno:
                // {
                //    "status": "sucesso",
                //    "id_protocolo": "PROT-TJAM-2026-12345678",
                //    "mensagem": "Manifestação processual recebida e protocolada com sucesso no TJAM.",
                //    "hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
                // }

                alert(`✅ Petição protocolada com sucesso!\nRecibo: ${resultado.id_protocolo}\nHash SHA-256 do Documento: ${resultado.hash}`);
                
                // Recarrega o painel de prazos
                if (typeof listarProcessos === 'function') {
                    listarProcessos();
                } else {
                    location.reload(); // Fallback seguro
                }

                return resultado;
            } else {
                throw new Error(resultado.message || "Falha ao protocolar.");
            }

        } catch (erro) {
            console.error("[PROJUDI] Falha no peticionamento:", erro);
            alert(`❌ Falha ao Protocolar no PROJUDI: ${erro.message}`);
            throw erro;
        }
    }
};
