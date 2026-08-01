<?php

declare(strict_types=1);

return [
    'competencias' => [
        ['competencia' => 'Julho/2026', 'periodo' => '01/07 a 31/07', 'polos' => '8', 'familias' => '5.000', 'entregas' => '4.736', 'situacao' => 'Em andamento'],
        ['competencia' => 'Junho/2026', 'periodo' => '01/06 a 30/06', 'polos' => '8', 'familias' => '4.920', 'entregas' => '4.881', 'situacao' => 'Encerrada'],
        ['competencia' => 'Agosto/2026', 'periodo' => '01/08 a 31/08', 'polos' => '9', 'familias' => '5.100', 'entregas' => '0', 'situacao' => 'Planejada'],
    ],
    'polos' => [
        ['polo' => 'Polo Centro', 'zona' => 'Urbana', 'responsavel' => 'Equipe 01', 'familias' => '842', 'capacidade' => '900', 'situacao' => 'Ativo'],
        ['polo' => 'Polo Itamarati', 'zona' => 'Urbana', 'responsavel' => 'Equipe 02', 'familias' => '716', 'capacidade' => '760', 'situacao' => 'Ativo'],
        ['polo' => 'Polo Rural Norte', 'zona' => 'Rural', 'responsavel' => 'Equipe Fluvial', 'familias' => '384', 'capacidade' => '420', 'situacao' => 'Programado'],
    ],
    'documentos' => [
        ['documento' => 'Comprovante de residência', 'categoria' => 'Inscrição', 'familias' => '4.812', 'pendencias' => '188', 'validade' => 'Conforme cadastro', 'situacao' => 'Monitorado'],
        ['documento' => 'Declaração familiar', 'categoria' => 'Elegibilidade', 'familias' => '4.905', 'pendencias' => '95', 'validade' => '12 meses', 'situacao' => 'Monitorado'],
        ['documento' => 'Termo de recebimento', 'categoria' => 'Entrega', 'familias' => '4.736', 'pendencias' => '264', 'validade' => 'Competência atual', 'situacao' => 'Em coleta'],
    ],
    'historico' => [
        ['data' => '29/07/2026 15:40', 'evento' => 'Entrega registrada', 'referencia' => 'Família FAM-04821', 'operador' => 'Equipe Polo Centro', 'resultado' => 'Concluído'],
        ['data' => '29/07/2026 14:15', 'evento' => 'Cadastro revisado', 'referencia' => 'Família FAM-03106', 'operador' => 'Equipe de análise', 'resultado' => 'Aprovado'],
        ['data' => '29/07/2026 11:20', 'evento' => 'Documento sinalizado', 'referencia' => 'Família FAM-02744', 'operador' => 'Equipe documental', 'resultado' => 'Pendência'],
    ],
];
