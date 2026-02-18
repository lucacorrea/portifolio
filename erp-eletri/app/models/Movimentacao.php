<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Movimentacao extends Model
{
    protected $table = 'movimentacoes_estoque';

    public function registrar($produtoId, $filialId, $usuarioId, $tipo, $quantidade, $motivo)
    {
        try {
            $this->db->beginTransaction();

            // 1. Registrar Movimentação
            $data = [
                'produto_id' => $produtoId,
                'filial_id' => $filialId,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'motivo' => $motivo
            ];
            $this->create($data);

            // 2. Atualizar Estoque
            $this->atualizarEstoque($produtoId, $filialId, $tipo, $quantidade);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    private function atualizarEstoque($produtoId, $filialId, $tipo, $quantidade)
    {
        // Verifica se registro existe na tabela estoque
        $stmt = $this->db->prepare("SELECT id, quantidade FROM estoque WHERE produto_id = :p AND filial_id = :f");
        $stmt->execute(['p' => $produtoId, 'f' => $filialId]);
        $estoque = $stmt->fetch();

        if (!$estoque) {
            // Cria se não existir
            $stmtInsert = $this->db->prepare("INSERT INTO estoque (produto_id, filial_id, quantidade) VALUES (:p, :f, 0)");
            $stmtInsert->execute(['p' => $produtoId, 'f' => $filialId]);
            $estoqueAtual = 0;
        } else {
            $estoqueAtual = $estoque['quantidade'];
        }

        // Calcula novo saldo
        if (in_array($tipo, ['entrada', 'devolucao', 'ajuste_entrada'])) {
            $novoEstoque = $estoqueAtual + $quantidade;
        } elseif (in_array($tipo, ['saida', 'venda', 'transferencia', 'ajuste_saida'])) {
             $novoEstoque = $estoqueAtual - $quantidade;
        } else {
             $novoEstoque = $estoqueAtual; // Tipo desconhecido
        }

        // Atualiza
        $stmtUpdate = $this->db->prepare("UPDATE estoque SET quantidade = :q WHERE produto_id = :p AND filial_id = :f");
        $stmtUpdate->execute(['q' => $novoEstoque, 'p' => $produtoId, 'f' => $filialId]);
    }
}
