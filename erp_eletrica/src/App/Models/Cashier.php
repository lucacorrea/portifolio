<?php
namespace App\Models;

class Cashier extends BaseModel {
    protected $table = 'caixas';

    public function getOpenForFilial($filialId) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE filial_id = ? AND status = 'aberto' 
            LIMIT 1
        ");
        $stmt->execute([$filialId]);
        return $stmt->fetch();
    }

    public function getSummary($caixaId) {
        $stmtOp = $this->db->prepare("SELECT operador_id, data_abertura, data_fechamento, filial_id FROM caixas WHERE id = ?");
        $stmtOp->execute([$caixaId]);
        $caixa = $stmtOp->fetch();

        $dataAbertura = $caixa['data_abertura'] ?? date('Y-m-d H:i:s');
        $dataFechamento = $caixa['data_fechamento']; // Será NULL se aberto
        $filialId = $caixa['filial_id'] ?? 0;

        $whereTime = "AND data_venda >= ?";
        $paramsTime = [$filialId, $dataAbertura];
        if ($dataFechamento) {
            $whereTime .= " AND data_venda <= ?";
            $paramsTime[] = $dataFechamento;
        }

        // 1. Vendas diretas (incluindo Fiado valor bruto)
        $sqlVendas = "
            SELECT LOWER(forma_pagamento), COALESCE(SUM(valor_total), 0) as total
            FROM vendas 
            WHERE filial_id = ? $whereTime AND status = 'concluido'
            GROUP BY forma_pagamento
        ";
        $stmtVendas = $this->db->prepare($sqlVendas);
        $stmtVendas->execute($paramsTime);
        $vendasPorForma = $stmtVendas->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 2. Pagamentos de Fiados (Recebimentos de hoje, de qualquer venda)
        $whereTimePagos = "AND fp.created_at >= ?";
        $paramsTimePagos = [$filialId, $dataAbertura];
        if ($dataFechamento) {
            $whereTimePagos .= " AND fp.created_at <= ?";
            $paramsTimePagos[] = $dataFechamento;
        }

        $sqlPagos = "
            SELECT LOWER(fp.metodo), COALESCE(SUM(fp.valor), 0) as total
            FROM fiados_pagamentos fp
            JOIN contas_receber cr ON fp.fiado_id = cr.id
            WHERE cr.filial_id = ? $whereTimePagos
            GROUP BY fp.metodo
        ";
        $stmtPagos = $this->db->prepare($sqlPagos);
        $stmtPagos->execute($paramsTimePagos);
        $pagosPorForma = $stmtPagos->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 3. Movimentações (sangria/suprimento/entradas manuais)
        $vTrasFiado = "
            SELECT COALESCE(SUM(valor), 0) 
            FROM caixa_movimentacoes 
            WHERE caixa_id = ? AND tipo = 'entrada' AND (LOWER(motivo) LIKE '%fiado%' OR LOWER(motivo) LIKE '%recebimento%')
        ";
        $stmtEntradaFiado = $this->db->prepare($vTrasFiado);
        $stmtEntradaFiado->execute([$caixaId]);
        $entradasExtrasDinheiro = (float)$stmtEntradaFiado->fetchColumn();

        $sqlMov = "
            SELECT 
                COALESCE(SUM(CASE WHEN tipo = 'suprimento' THEN valor ELSE 0 END), 0) as suprimentos,
                COALESCE(SUM(CASE WHEN tipo = 'sangria' THEN valor ELSE 0 END), 0) as sangrias
            FROM caixa_movimentacoes 
            WHERE caixa_id = ?
        ";
        $stmtMov = $this->db->prepare($sqlMov);
        $stmtMov->execute([$caixaId]);
        $movs = $stmtMov->fetch();
        
        // --- CÁLCULOS FINAIS ---
        $vendasDinheiroDireto = (float)($vendasPorForma['dinheiro'] ?? 0);
        $vendasPixDireto = (float)($vendasPorForma['pix'] ?? 0);
        $vendasCartaoDireto = (float)($vendasPorForma['cartao_credito'] ?? 0) + (float)($vendasPorForma['cartao_debito'] ?? 0) + (float)($vendasPorForma['cartao'] ?? 0);
        $vendasBoletoDireto = (float)($vendasPorForma['boleto'] ?? 0);
        $vendasFiadoBruto = (float)($vendasPorForma['fiado'] ?? 0);
        
        // Pagamentos Recebidos hoje (AVS)
        $pagosDinheiro = (float)($pagosPorForma['dinheiro'] ?? 0);
        $pagosPix = (float)($pagosPorForma['pix'] ?? 0);
        $pagosCartao = (float)($pagosPorForma['cartao'] ?? 0);
        $pagosBoleto = (float)($pagosPorForma['boleto'] ?? 0);
        
        // Físico = Vendas Diretas em Dinheiro + Pagamentos em Dinheiro (ou via movimentos 'fiado')
        // Usamos o maior valor entre pagosDinheiro e entradasExtrasDinheiro para evitar duplicidade 
        // mas garantir que se um falhar o outro pegue (já que ambos registram a mesma entrada física).
        $totalDinheiroEntrou = $vendasDinheiroDireto + max($pagosDinheiro, $entradasExtrasDinheiro);
        
        $totalPix = $vendasPixDireto + $pagosPix;
        $totalCartao = $vendasCartaoDireto + $pagosCartao;
        $totalBoleto = $vendasBoletoDireto + $pagosBoleto;
        
        // Saldo Gaveta
        $dinheiroEmGaveta = $totalDinheiroEntrou + $movs['suprimentos'] - $movs['sangrias'];

        // Total Bruto (Soma de tudo que entrou fisicamente ou digitalmente na sessão)
        $totalEntradasSessao = $totalDinheiroEntrou + $totalPix + $totalCartao + $totalBoleto;

        return [
            'vendas_dinheiro' => $totalDinheiroEntrou,
            'vendas_pix' => $totalPix,
            'vendas_cartao' => $totalCartao,
            'vendas_boleto' => $totalBoleto,
            'vendas_fiado' => 0, // Card removido
            'entradas_fiado_dinheiro' => 0, // Já incluído em vendas_dinheiro
            'suprimentos' => $movs['suprimentos'],
            'sangrias' => $movs['sangrias'],
            'dinheiro_em_gaveta' => $dinheiroEmGaveta,
            'total_bruto' => $totalEntradasSessao
        ];
    }

    public function getSessionDetails($caixaId) {
        // 1. Dados do caixa + operador
        $stmt = $this->db->prepare("
            SELECT c.*, u.nome as operador_nome 
            FROM caixas c 
            LEFT JOIN usuarios u ON c.operador_id = u.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$caixaId]);
        $caixa = $stmt->fetch();

        if (!$caixa) return null;

        $dataAbertura = $caixa['data_abertura'];
        $dataFechamento = $caixa['data_fechamento'];
        $filialId = $caixa['filial_id'];

        // 2. Summary (reutiliza método existente)
        $summary = $this->getSummary($caixaId);

        // 3. Vendas do período
        $whereTime = "AND v.data_venda >= ?";
        $paramsVendas = [$filialId, $dataAbertura];
        if ($dataFechamento) {
            $whereTime .= " AND v.data_venda <= ?";
            $paramsVendas[] = $dataFechamento;
        }

        $stmtVendas = $this->db->prepare("
            SELECT v.*, 
                   IFNULL(cl.nome, v.nome_cliente_avulso) as cliente_nome,
                   u.nome as vendedor_nome
            FROM vendas v 
            LEFT JOIN clientes cl ON v.cliente_id = cl.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.filial_id = ? $whereTime AND v.status = 'concluido'
            ORDER BY v.data_venda DESC
        ");
        $stmtVendas->execute($paramsVendas);
        $vendas = $stmtVendas->fetchAll();

        // 4. Recebimentos de Fiados no período
        $whereTimeFiado = "AND fp.created_at >= ?";
        $paramsFiado = [$filialId, $dataAbertura];
        if ($dataFechamento) {
            $whereTimeFiado .= " AND fp.created_at <= ?";
            $paramsFiado[] = $dataFechamento;
        }

        $stmtFiados = $this->db->prepare("
            SELECT fp.*, cr.cliente_nome, cr.valor_total as valor_fiado_total
            FROM fiados_pagamentos fp
            JOIN contas_receber cr ON fp.fiado_id = cr.id
            WHERE cr.filial_id = ? $whereTimeFiado
            ORDER BY fp.created_at DESC
        ");
        $stmtFiados->execute($paramsFiado);
        $fiadosPagamentos = $stmtFiados->fetchAll();

        // 5. Movimentações
        $stmtMov = $this->db->prepare("
            SELECT cm.*, u.nome as operador_nome
            FROM caixa_movimentacoes cm
            LEFT JOIN usuarios u ON cm.operador_id = u.id
            WHERE cm.caixa_id = ?
            ORDER BY cm.created_at ASC
        ");
        $stmtMov->execute([$caixaId]);
        $movimentacoes = $stmtMov->fetchAll();

        return [
            'caixa' => $caixa,
            'summary' => $summary,
            'vendas' => $vendas,
            'fiados_pagamentos' => $fiadosPagamentos,
            'movimentacoes' => $movimentacoes
        ];
    }
}
