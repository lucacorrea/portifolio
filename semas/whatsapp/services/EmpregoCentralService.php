<?php
declare(strict_types=1);

require_once __DIR__ . '/../WhatsappService.php';

class EmpregoCentralService
{
    private $pdo;
    private $whatsapp;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->whatsapp = new WhatsappService();
    }

    public function ensureSchema(): void
    {
        $migration = __DIR__ . '/../database/migrations/20260622_whatsapp_emprego_central.sql';
        if (!is_file($migration)) {
            return;
        }
        $sql = file_get_contents($migration);
        if ($sql === false) {
            return;
        }
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $this->pdo->exec($statement);
            }
        }
    }

    public function csrfToken(): string
    {
        return (string)($_SESSION['semas_whatsapp_csrf'] ?? '');
    }

    public function usuarioNome(): string
    {
        foreach (['semas_whatsapp_user_nome', 'semas_whatsapp_user_email'] as $key) {
            $value = trim((string)($_SESSION[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return 'Sistema';
    }

    public function statusWhatsapp(): array
    {
        return $this->whatsapp->verificarConexao();
    }

    public function findEmploymentTypes(): array
    {
        $rows = $this->pdo->query("SELECT id, nome FROM ajudas_tipos WHERE status = 'Ativa' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allowed = [
            'emprego',
            'trabalho',
            'oportunidade de emprego',
            'encaminhamento profissional',
            'atualizacao profissional',
            'atualização profissional',
        ];
        $matches = [];
        foreach ($rows as $row) {
            $norm = self::normalizeText((string)($row['nome'] ?? ''));
            foreach ($allowed as $term) {
                $termNorm = self::normalizeText($term);
                if ($norm === $termNorm || strpos($norm, $termNorm) !== false) {
                    $matches[] = ['id' => (int)$row['id'], 'nome' => (string)$row['nome']];
                    break;
                }
            }
        }
        return [
            'ids' => array_map(static function (array $row): int { return (int)$row['id']; }, $matches),
            'tipos' => $matches,
            'ambiguo' => count($matches) > 1,
            'encontrado' => count($matches) > 0,
        ];
    }

    public static function normalizeText(string $value): string
    {
        $value = trim($value);
        $value = strtr($value, [
            'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a','á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'É'=>'e','Ê'=>'e','Ë'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'Í'=>'i','Î'=>'i','Ï'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'Ó'=>'o','Ò'=>'o','Õ'=>'o','Ô'=>'o','Ö'=>'o','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'Ç'=>'c','ç'=>'c',
        ]);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s\/-]+/', ' ', $value) ?: $value;
        return preg_replace('/\s+/', ' ', $value) ?: $value;
    }

    public function normalizarTelefone(string $telefone): string
    {
        return $this->whatsapp->normalizarTelefone($telefone);
    }

    public function filtrosBase(array $in): array
    {
        $emprego = $this->findEmploymentTypes();
        $ids = $emprego['ids'];
        if (!$ids) {
            return ['where' => ['1=0'], 'params' => [], 'emprego' => $emprego];
        }

        $beneficioIds = $this->idsFrom($in['beneficio_id'] ?? '');
        if ($beneficioIds) {
            $ids = array_values(array_intersect($ids, $beneficioIds));
            if (!$ids) {
                return ['where' => ['1=0'], 'params' => [], 'emprego' => $emprego];
            }
        }

        $where = ['sol.ajuda_tipo_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'];
        $params = $ids;

        $mes = (int)($in['mes'] ?? 0);
        $ano = (int)($in['ano'] ?? 0);
        if ($mes >= 1 && $mes <= 12 && $ano >= 2020 && $ano <= 2100) {
            $inicio = sprintf('%04d-%02d-01 00:00:00', $ano, $mes);
            $fim = date('Y-m-d H:i:s', strtotime($inicio . ' +1 month'));
            $where[] = 'sol.data_solicitacao >= ? AND sol.data_solicitacao < ?';
            $params[] = $inicio;
            $params[] = $fim;
            return $this->filtrosComplementares($in, $where, $params, $emprego);
        }

        $di = $this->normalizeDate((string)($in['di'] ?? ''));
        $df = $this->normalizeDate((string)($in['df'] ?? ''));
        if ($di !== '') {
            $where[] = 'COALESCE(sol.data_solicitacao, s.created_at) >= ?';
            $params[] = $di . ' 00:00:00';
        }
        if ($df !== '') {
            $fim = date('Y-m-d H:i:s', strtotime($df . ' +1 day'));
            $where[] = 'COALESCE(sol.data_solicitacao, s.created_at) < ?';
            $params[] = $fim;
        }

        return $this->filtrosComplementares($in, $where, $params, $emprego);
    }

    private function filtrosComplementares(array $in, array $where, array $params, array $emprego): array
    {
        $bairroIds = $this->idsFrom($in['bairro_id'] ?? '');
        if ($bairroIds) {
            $where[] = 's.bairro_id IN (' . implode(',', array_fill(0, count($bairroIds), '?')) . ')';
            $params = array_merge($params, $bairroIds);
        }

        $sexo = trim((string)($in['sexo'] ?? ''));
        if ($sexo !== '') {
            $where[] = 'LOWER(TRIM(COALESCE(s.genero, ""))) = LOWER(TRIM(?))';
            $params[] = $sexo;
        }

        $q = trim((string)($in['q'] ?? ''));
        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q) ?: '';
            $where[] = '(s.nome LIKE ? OR s.cpf LIKE ? OR s.telefone LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $digits . '%';
            $params[] = '%' . $digits . '%';
        }

        $campanhaId = (int)($in['campanha_id'] ?? 0);
        if ($campanhaId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM whatsapp_semas_destinatarios wd WHERE wd.solicitacao_id = sol.id AND wd.campanha_id = ?)';
            $params[] = $campanhaId;
        }

        return ['where' => $where, 'params' => $params, 'emprego' => $emprego];
    }

    public function listarPessoas(array $in, int $page = 1, int $perPage = 20): array
    {
        $base = $this->filtrosBase($in);
        $whereSql = implode(' AND ', $base['where']);
        $params = $base['params'];
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) FROM solicitacoes sol INNER JOIN solicitantes s ON s.id = sol.solicitante_id WHERE $whereSql";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "
            SELECT
                s.id AS solicitante_id,
                sol.id AS solicitacao_id,
                s.nome,
                s.cpf,
                s.telefone,
                COALESCE(b.nome, '-') AS bairro,
                COALESCE(sol.data_solicitacao, s.created_at) AS data_referencia,
                COALESCE(at.nome, 'Emprego') AS tipo_caso,
                s.trabalho,
                sol.resumo_caso,
                (
                    SELECT wd.status
                    FROM whatsapp_semas_destinatarios wd
                    WHERE wd.solicitacao_id = sol.id
                    ORDER BY wd.id DESC
                    LIMIT 1
                ) AS status_mensagem,
                (
                    SELECT wc.titulo
                    FROM whatsapp_semas_destinatarios wd
                    INNER JOIN whatsapp_semas_campanhas wc ON wc.id = wd.campanha_id
                    WHERE wd.solicitacao_id = sol.id
                    ORDER BY wd.id DESC
                    LIMIT 1
                ) AS campanha,
                (
                    SELECT wm.conteudo
                    FROM whatsapp_semas_mensagens wm
                    WHERE wm.solicitacao_id = sol.id AND wm.direcao = 'entrada'
                    ORDER BY COALESCE(wm.data_mensagem, wm.criado_em) DESC
                    LIMIT 1
                ) AS ultima_resposta,
                (
                    SELECT wa.categoria
                    FROM whatsapp_semas_emprego wa
                    WHERE wa.solicitacao_id = sol.id
                    ORDER BY wa.id DESC
                    LIMIT 1
                ) AS profissao_identificada,
                (
                    SELECT wa.status_revisao
                    FROM whatsapp_semas_emprego wa
                    WHERE wa.solicitacao_id = sol.id
                    ORDER BY wa.id DESC
                    LIMIT 1
                ) AS situacao_atualizacao
            FROM solicitacoes sol
            INNER JOIN solicitantes s ON s.id = sol.solicitante_id
            LEFT JOIN bairros b ON b.id = s.bairro_id
            LEFT JOIN ajudas_tipos at ON at.id = sol.ajuda_tipo_id
            WHERE $whereSql
            ORDER BY COALESCE(sol.data_solicitacao, s.created_at) DESC, sol.id DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $normalizado = $this->normalizarTelefone((string)($row['telefone'] ?? ''));
            $row['telefone_normalizado'] = $normalizado;
            $row['telefone_valido'] = $normalizado !== '';
            $row['cpf_mascarado'] = self::maskCpf((string)($row['cpf'] ?? ''));
            $row['telefone_mascarado'] = self::maskPhone((string)($row['telefone'] ?? ''));
            $row['resumo_curto'] = self::truncate((string)($row['resumo_caso'] ?? ''), 160);
            $row['ultima_resposta_curta'] = self::truncate((string)($row['ultima_resposta'] ?? ''), 80);
        }
        unset($row);

        return [
            'pessoas' => $rows,
            'paginacao' => [
                'total' => $total,
                'pagina' => $page,
                'por_pagina' => $perPage,
                'paginas' => max(1, (int)ceil($total / $perPage)),
            ],
            'emprego' => $base['emprego'],
        ];
    }

    public function indicadores(array $in): array
    {
        $all = $this->listarPessoas($in, 1, 5000);
        $pessoas = $all['pessoas'];
        $validos = 0;
        $semTelefone = 0;
        foreach ($pessoas as $pessoa) {
            if (!empty($pessoa['telefone_valido'])) {
                $validos++;
            } else {
                $semTelefone++;
            }
        }

        $stats = $this->pdo->query("
            SELECT
                SUM(status = 'na_fila') AS fila,
                SUM(status IN ('enviado','aguardando_resposta','resposta_recebida','profissao_identificada','resumo_atualizado','concluido')) AS enviados,
                SUM(status = 'falha_envio') AS falhas,
                SUM(status = 'opt_out') AS optout
            FROM whatsapp_semas_destinatarios
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $revisoes = (int)$this->pdo->query("SELECT COUNT(*) FROM whatsapp_semas_emprego WHERE status_revisao IN ('pendente','aguardando_revisao')")->fetchColumn();
        $atualizados = (int)$this->pdo->query("SELECT COUNT(*) FROM whatsapp_semas_emprego WHERE aplicado_em IS NOT NULL")->fetchColumn();
        $respondidos = (int)$this->pdo->query("SELECT COUNT(DISTINCT solicitante_id) FROM whatsapp_semas_mensagens WHERE direcao = 'entrada'")->fetchColumn();

        $profissoes = $this->pdo->query("
            SELECT COALESCE(categoria, 'Nao informado') AS nome, COUNT(*) AS total
            FROM whatsapp_semas_emprego
            WHERE categoria IS NOT NULL AND categoria <> ''
            GROUP BY categoria
            ORDER BY total DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $conversas = $this->pdo->query("
            SELECT status, COUNT(*) AS total
            FROM whatsapp_semas_destinatarios
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $periodo = $this->pdo->query("
            SELECT DATE(criado_em) AS dia,
                   SUM(direcao = 'saida') AS enviados,
                   SUM(direcao = 'entrada') AS respostas
            FROM whatsapp_semas_mensagens
            WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(criado_em)
            ORDER BY dia ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'cards' => [
                'pessoas_filtradas' => count($pessoas),
                'telefones_validos' => $validos,
                'sem_telefone_valido' => $semTelefone,
                'nao_contatadas' => count(array_filter($pessoas, static function ($p) { return empty($p['status_mensagem']); })),
                'mensagens_fila' => (int)($stats['fila'] ?? 0),
                'mensagens_enviadas' => (int)($stats['enviados'] ?? 0),
                'mensagens_entregues' => 0,
                'aguardando_resposta' => (int)$this->countDestStatus('aguardando_resposta'),
                'pessoas_responderam' => $respondidos,
                'profissoes_identificadas' => count($profissoes),
                'revisoes_pendentes' => $revisoes,
                'resumos_atualizados' => $atualizados,
                'falhas_envio' => (int)($stats['falhas'] ?? 0),
                'optout' => (int)($stats['optout'] ?? 0),
            ],
            'funil' => [
                ['etapa' => 'Selecionados', 'total' => count($pessoas)],
                ['etapa' => 'Enviados', 'total' => (int)($stats['enviados'] ?? 0)],
                ['etapa' => 'Entregues', 'total' => 0],
                ['etapa' => 'Lidos', 'total' => 0],
                ['etapa' => 'Respondidos', 'total' => $respondidos],
                ['etapa' => 'Atualizados', 'total' => $atualizados],
            ],
            'profissoes' => $profissoes,
            'periodo' => $periodo,
            'conversas' => $conversas,
            'emprego' => $all['emprego'],
        ];
    }

    public function criarCampanha(array $in): array
    {
        $message = trim((string)($in['mensagem_modelo'] ?? self::mensagemPadrao()));
        $messageNorm = self::normalizeText($message);
        if (strpos($messageNorm, 'nao representa garantia') === false) {
            throw new RuntimeException('A mensagem deve manter o aviso de que nao existe garantia de contratacao.');
        }
        if (strpos($messageNorm, 'sair') === false) {
            throw new RuntimeException('A mensagem deve informar a opcao SAIR para opt-out.');
        }

        $titulo = trim((string)($in['titulo'] ?? 'Atualizacao profissional - ' . date('d/m/Y H:i')));
        $filtros = is_array($in['filtros'] ?? null) ? $in['filtros'] : $in;
        $preview = $this->listarPessoas($filtros, 1, 5000);
        $pessoas = $preview['pessoas'];
        if (!$preview['emprego']['encontrado']) {
            throw new RuntimeException('Tipo Emprego nao encontrado em ajudas_tipos.');
        }

        $selecionarTodos = !empty($in['selecionar_todos']);
        $selecionados = is_array($in['selecionados'] ?? null) ? $in['selecionados'] : [];
        if (!$selecionarTodos && $selecionados) {
            $permitidos = [];
            foreach ($selecionados as $item) {
                $permitidos[(string)$item] = true;
            }
            $pessoas = array_values(array_filter($pessoas, static function (array $pessoa) use ($permitidos): bool {
                $key = (int)$pessoa['solicitante_id'] . ':' . (int)$pessoa['solicitacao_id'];
                return isset($permitidos[$key]);
            }));
        } elseif (!$selecionarTodos && !$selecionados) {
            $pessoas = array_slice($pessoas, 0, 20);
        }

        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_semas_campanhas (titulo, tipo, mensagem_modelo, filtros_json, status, criado_por, criado_por_id)
            VALUES (?, 'atualizacao_profissional', ?, ?, 'rascunho', ?, ?)
        ");
        $stmt->execute([
            $titulo,
            $message,
            json_encode($filtros, JSON_UNESCAPED_UNICODE),
            $this->usuarioNome(),
            (int)($_SESSION['semas_whatsapp_user_id'] ?? 0),
        ]);
        $campanhaId = (int)$this->pdo->lastInsertId();

        $seenPhones = [];
        $insert = $this->pdo->prepare("
            INSERT IGNORE INTO whatsapp_semas_destinatarios
            (campanha_id, solicitante_id, solicitacao_id, telefone_original, telefone_normalizado, status, erro_ultimo_envio)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $resumo = ['total' => count($pessoas), 'validos' => 0, 'invalidos' => 0, 'optout' => 0, 'duplicados' => 0];
        foreach ($pessoas as $pessoa) {
            $telefoneOriginal = (string)($pessoa['telefone'] ?? '');
            $telefone = $this->normalizarTelefone($telefoneOriginal);
            $status = 'na_fila';
            $erro = null;
            if ($telefone === '') {
                $status = 'telefone_invalido';
                $erro = 'Telefone ausente ou invalido.';
                $resumo['invalidos']++;
            } elseif ($this->hasOptout($telefone)) {
                $status = 'opt_out';
                $erro = 'Telefone com opt-out ativo.';
                $resumo['optout']++;
            } elseif (isset($seenPhones[$telefone])) {
                $status = 'duplicado';
                $erro = 'Telefone duplicado na campanha.';
                $resumo['duplicados']++;
            } else {
                $seenPhones[$telefone] = true;
                $resumo['validos']++;
            }
            $insert->execute([
                $campanhaId,
                (int)$pessoa['solicitante_id'],
                (int)$pessoa['solicitacao_id'],
                $telefoneOriginal,
                $telefone ?: null,
                $status,
                $erro,
            ]);
        }
        $this->audit('criar_campanha', null, null, $campanhaId, [], $resumo);
        $this->pdo->commit();

        return ['campanha_id' => $campanhaId, 'resumo' => $resumo];
    }

    public function processarFila(int $campanhaId, int $limite = 10): array
    {
        $limite = max(1, min(30, $limite));
        $lockName = 'semas_whatsapp_emprego_' . $campanhaId;
        $lock = $this->pdo->query("SELECT GET_LOCK(" . $this->pdo->quote($lockName) . ", 1)")->fetchColumn();
        if ((int)$lock !== 1) {
            throw new RuntimeException('Ja existe processamento em andamento para esta campanha.');
        }

        try {
            $this->pdo->prepare("UPDATE whatsapp_semas_campanhas SET status = 'em_envio', iniciado_em = COALESCE(iniciado_em, NOW()) WHERE id = ?")->execute([$campanhaId]);
            $stmt = $this->pdo->prepare("
                SELECT wd.*, s.nome
                FROM whatsapp_semas_destinatarios wd
                INNER JOIN solicitantes s ON s.id = wd.solicitante_id
                WHERE wd.campanha_id = ?
                  AND wd.status IN ('na_fila','falha_envio')
                  AND wd.tentativas < 3
                ORDER BY wd.id ASC
                LIMIT $limite
            ");
            $stmt->execute([$campanhaId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $campanha = $this->getCampanha($campanhaId);
            $ok = 0;
            $fail = 0;
            foreach ($items as $item) {
                $this->pdo->prepare("UPDATE whatsapp_semas_destinatarios SET status = 'enviando', tentativas = tentativas + 1 WHERE id = ?")->execute([(int)$item['id']]);
                $text = str_replace('{NOME}', (string)$item['nome'], (string)$campanha['mensagem_modelo']);
                $idempotencyKey = 'campanha-' . $campanhaId . '-destinatario-' . (int)$item['id'];
                $ret = $this->whatsapp->enviarTexto((string)$item['telefone_normalizado'], $text, $idempotencyKey);
                if ($ret['sucesso']) {
                    $ok++;
                    $this->pdo->prepare("UPDATE whatsapp_semas_destinatarios SET status = 'aguardando_resposta', enviado_em = NOW(), erro_ultimo_envio = NULL WHERE id = ?")->execute([(int)$item['id']]);
                    $this->registrarMensagemSaida($campanhaId, (int)$item['id'], (int)$item['solicitante_id'], (int)$item['solicitacao_id'], (string)$item['telefone_normalizado'], $text, 'enviada');
                } else {
                    $fail++;
                    $this->pdo->prepare("UPDATE whatsapp_semas_destinatarios SET status = 'falha_envio', erro_ultimo_envio = ? WHERE id = ?")->execute([self::truncate((string)$ret['mensagem'], 240), (int)$item['id']]);
                }
            }
            $pendentes = $this->countQueue($campanhaId);
            if ($pendentes === 0) {
                $this->pdo->prepare("UPDATE whatsapp_semas_campanhas SET status = 'processada', finalizado_em = NOW() WHERE id = ?")->execute([$campanhaId]);
            }
            return ['processados' => count($items), 'enviados' => $ok, 'falhas' => $fail, 'pendentes' => $pendentes];
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK(" . $this->pdo->quote($lockName) . ")");
        }
    }

    public function buscarConversa(int $solicitanteId, int $solicitacaoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.id AS solicitante_id, s.nome, s.cpf, s.telefone, COALESCE(b.nome, '-') AS bairro,
                   sol.id AS solicitacao_id, sol.resumo_caso, COALESCE(at.nome, 'Emprego') AS tipo_caso
            FROM solicitacoes sol
            INNER JOIN solicitantes s ON s.id = sol.solicitante_id
            LEFT JOIN bairros b ON b.id = s.bairro_id
            LEFT JOIN ajudas_tipos at ON at.id = sol.ajuda_tipo_id
            WHERE s.id = ? AND sol.id = ?
            LIMIT 1
        ");
        $stmt->execute([$solicitanteId, $solicitacaoId]);
        $pessoa = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pessoa) {
            throw new RuntimeException('Pessoa ou solicitacao nao encontrada.');
        }
        $pessoa['cpf_mascarado'] = self::maskCpf((string)$pessoa['cpf']);
        $pessoa['telefone_mascarado'] = self::maskPhone((string)$pessoa['telefone']);

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM whatsapp_semas_mensagens
            WHERE solicitante_id = ? AND solicitacao_id = ?
            ORDER BY COALESCE(data_mensagem, criado_em) ASC, id ASC
        ");
        $stmt->execute([$solicitanteId, $solicitacaoId]);
        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM whatsapp_semas_emprego
            WHERE solicitante_id = ? AND solicitacao_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$solicitanteId, $solicitacaoId]);
        $atualizacoes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ['pessoa' => $pessoa, 'mensagens' => $mensagens, 'atualizacoes' => $atualizacoes];
    }

    public function registrarEntrada(array $payload): array
    {
        $externalId = trim((string)($payload['messageId'] ?? $payload['id'] ?? $payload['mensagem_externa_id'] ?? ''));
        $telefone = $this->normalizarTelefone((string)($payload['sender'] ?? $payload['telefone'] ?? $payload['from'] ?? ''));
        $texto = trim((string)($payload['text'] ?? $payload['body'] ?? $payload['conteudo'] ?? ''));
        $tipo = trim((string)($payload['type'] ?? $payload['tipo'] ?? 'texto')) ?: 'texto';
        if ($telefone === '') {
            throw new RuntimeException('Telefone de origem invalido.');
        }
        if ($externalId !== '' && $this->messageExists($externalId)) {
            return ['duplicada' => true];
        }

        $destinatarios = $this->destinatariosPorTelefone($telefone);
        $dest = count($destinatarios) === 1 ? $destinatarios[0] : null;
        $status = count($destinatarios) > 1 ? 'telefone_compartilhado' : 'registrada';
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_semas_mensagens
            (campanha_id, destinatario_id, solicitante_id, solicitacao_id, mensagem_externa_id, direcao, tipo, conteudo, telefone, status, payload_sanitizado, data_mensagem)
            VALUES (?, ?, ?, ?, ?, 'entrada', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $dest['campanha_id'] ?? null,
            $dest['id'] ?? null,
            $dest['solicitante_id'] ?? null,
            $dest['solicitacao_id'] ?? null,
            $externalId !== '' ? $externalId : null,
            $tipo,
            $texto,
            $telefone,
            $status,
            json_encode($this->sanitizePayload($payload), JSON_UNESCAPED_UNICODE),
        ]);
        $msgId = (int)$this->pdo->lastInsertId();

        if (!$dest) {
            return ['mensagem_id' => $msgId, 'status' => $status];
        }

        $interpretacao = $this->interpretarResposta($texto, (string)$dest['status']);
        if ($interpretacao['optout']) {
            $this->registrarOptout((int)$dest['solicitante_id'], $telefone, 'Solicitado por WhatsApp');
            $this->pdo->prepare("UPDATE whatsapp_semas_destinatarios SET status = 'opt_out', respondido_em = NOW() WHERE id = ?")->execute([(int)$dest['id']]);
            $this->responderOptoutUmaVez((int)$dest['id'], $telefone);
        } elseif ($interpretacao['precisa_complemento']) {
            $this->pdo->prepare("UPDATE whatsapp_semas_destinatarios SET status = 'aguardando_profissao', respondido_em = NOW() WHERE id = ?")->execute([(int)$dest['id']]);
            $this->enviarComplementoUmaVez((int)$dest['id'], $telefone);
        } else {
            $novoStatus = $interpretacao['status_revisao'] === 'pendente' ? 'profissao_identificada' : 'aguardando_revisao';
            $this->pdo->prepare("UPDATE whatsapp_semas_destinatarios SET status = ?, respondido_em = NOW() WHERE id = ?")->execute([$novoStatus, (int)$dest['id']]);
            $this->criarAtualizacao($dest, $msgId, $interpretacao, $texto);
        }

        return ['mensagem_id' => $msgId, 'interpretacao' => $interpretacao];
    }

    public function interpretarResposta(string $texto, string $estadoAtual = ''): array
    {
        $norm = self::normalizeText($texto);
        $exact = trim($norm);
        $optoutTerms = ['sair', 'parar', 'cancelar', 'nao quero receber', 'nao mande mais mensagens'];
        foreach ($optoutTerms as $term) {
            if ($exact === $term || strpos($exact, $term) !== false) {
                return $this->interpretacao('Opt-out', 'Nao possui interesse', '', 100, 'opt_out', 'concluido', false, true);
            }
        }
        if (preg_match('/^(1|opcao 1)$/', $exact) || preg_match('/\b(servicos gerais|servico geral|auxiliar de servicos gerais|limpeza em geral)\b/', $norm)) {
            return $this->interpretacao('Servicos gerais', 'Servicos gerais', $texto, 98, 'opcao_servicos_gerais');
        }
        if (preg_match('/^(2|opcao 2)$/', $exact) || preg_match('/\b(gari|limpeza urbana|varricao|coletor|coleta de lixo)\b/', $norm)) {
            return $this->interpretacao('Limpeza urbana / gari', 'Limpeza urbana / gari', $texto, 98, 'opcao_gari');
        }
        if (preg_match('/^(3|opcao 3|outra|outra profissao)$/', $exact)) {
            return $this->interpretacao('Outra profissao', 'Nao informado', $texto, 60, 'solicitar_complemento', 'aguardando_complemento', true, false);
        }
        if (preg_match('/^(4|opcao 4)$/', $exact) || preg_match('/\b(nao tenho interesse|nao quero trabalhar|nao estou procurando emprego)\b/', $norm)) {
            return $this->interpretacao('Nao possui interesse', 'Nao possui interesse', $texto, 98, 'sem_interesse');
        }
        if (preg_match('/\b(qualquer coisa|o que tiver|tanto faz|preciso trabalhar|pode ser tudo|sim|tenho interesse|quero emprego)\b/', $norm)) {
            return $this->interpretacao('Profissao nao identificada', 'Nao informado', $texto, 40, 'resposta_ambigua', 'aguardando_revisao');
        }

        $profissoes = $this->profissoesMap();
        $matches = [];
        foreach ($profissoes as $categoria => $terms) {
            foreach ($terms as $term) {
                if (preg_match('/(^|[^a-z0-9])' . preg_quote(self::normalizeText($term), '/') . '([^a-z0-9]|$)/', $norm)) {
                    $matches[$categoria] = true;
                }
            }
        }
        $categorias = array_keys($matches);
        if (count($categorias) === 1) {
            return $this->interpretacao($categorias[0], $categorias[0], $texto, 90, 'profissao_texto_livre');
        }
        if (count($categorias) > 1) {
            return $this->interpretacao(implode(', ', $categorias), $categorias[0], $texto, 70, 'multiplas_profissoes', 'aguardando_revisao');
        }
        if ($exact !== '') {
            return $this->interpretacao('Outra profissao', 'Outra profissao', $texto, 65, 'texto_livre_desconhecido', 'aguardando_revisao');
        }
        return $this->interpretacao('Nao informado', 'Nao informado', $texto, 0, 'resposta_vazia', 'aguardando_revisao');
    }

    public function atualizarResumo(int $atualizacaoId, string $resumoAprovado): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM whatsapp_semas_emprego WHERE id = ? LIMIT 1");
        $stmt->execute([$atualizacaoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Atualizacao nao encontrada.');
        }
        $resumoAprovado = trim($resumoAprovado);
        if ($resumoAprovado === '') {
            $resumoAprovado = (string)$row['resumo_sugerido'];
        }
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare("SELECT resumo_caso FROM solicitacoes WHERE id = ? FOR UPDATE");
        $stmt->execute([(int)$row['solicitacao_id']]);
        $anterior = (string)$stmt->fetchColumn();
        $novo = trim($anterior) === '' ? $resumoAprovado : rtrim($anterior) . "\n\n" . $resumoAprovado;
        if (strlen($novo) > 65000) {
            $this->pdo->rollBack();
            throw new RuntimeException('Resumo muito longo para atualizacao segura.');
        }
        $this->pdo->prepare("UPDATE solicitacoes SET resumo_caso = ? WHERE id = ?")->execute([$novo, (int)$row['solicitacao_id']]);
        $this->pdo->prepare("
            UPDATE whatsapp_semas_emprego
            SET resumo_anterior = ?, resumo_aprovado = ?, revisado_por = ?, revisado_em = NOW(), aplicado_em = NOW(), status_revisao = 'aplicado'
            WHERE id = ?
        ")->execute([$anterior, $resumoAprovado, $this->usuarioNome(), $atualizacaoId]);
        $this->pdo->prepare("
            INSERT INTO whatsapp_semas_summary_history
            (atualizacao_id, solicitante_id, solicitacao_id, resumo_anterior, resumo_novo, usuario_id, usuario_nome)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $atualizacaoId,
            (int)$row['solicitante_id'],
            (int)$row['solicitacao_id'],
            $anterior,
            $novo,
            (int)($_SESSION['semas_whatsapp_user_id'] ?? 0),
            $this->usuarioNome(),
        ]);
        $this->audit('atualizar_resumo', (int)$row['solicitante_id'], (int)$row['solicitacao_id'], (int)($row['campanha_id'] ?? 0), ['resumo_caso' => $anterior], ['resumo_caso' => $novo]);
        $this->pdo->commit();
        return ['resumo' => $novo];
    }

    private function criarAtualizacao(array $dest, int $msgId, array $i, string $texto): void
    {
        $resumo = $this->resumoSugerido($i);
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_semas_emprego
            (solicitante_id, solicitacao_id, campanha_id, mensagem_id, resposta_original, profissao_original, profissao_normalizada, categoria, confianca, regra_interpretacao, status_revisao, resumo_sugerido)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$dest['solicitante_id'],
            (int)$dest['solicitacao_id'],
            (int)$dest['campanha_id'],
            $msgId,
            $texto,
            $i['profissao_original'],
            $i['profissao_normalizada'],
            $i['categoria'],
            $i['confianca'],
            $i['regra'],
            $i['status_revisao'],
            $resumo,
        ]);
    }

    private function resumoSugerido(array $i): string
    {
        $data = date('d/m/Y');
        if ($i['categoria'] === 'Nao possui interesse') {
            return "Atualizacao profissional via WhatsApp em {$data}:\nA pessoa informou que nao possui interesse em oportunidades de trabalho neste momento.";
        }
        if ($i['categoria'] === 'Servicos gerais') {
            return "Atualizacao profissional via WhatsApp em {$data}:\nA pessoa confirmou interesse em trabalhar na area de servicos gerais. Informacao recebida pelo telefone cadastrado durante a campanha de atualizacao profissional.";
        }
        if ($i['categoria'] === 'Limpeza urbana / gari') {
            return "Atualizacao profissional via WhatsApp em {$data}:\nA pessoa confirmou interesse em trabalhar com limpeza urbana / gari. Informacao recebida pelo telefone cadastrado durante a campanha de atualizacao profissional.";
        }
        $prof = $i['profissao_original'] ?: $i['categoria'];
        return "Atualizacao profissional via WhatsApp em {$data}:\nA pessoa informou interesse profissional na area de {$prof}. Resposta original preservada no historico da conversa.";
    }

    private function interpretacao(string $categoria, string $normalizada, string $original, float $confianca, string $regra, string $status = 'pendente', bool $complemento = false, bool $optout = false): array
    {
        return [
            'categoria' => $categoria,
            'profissao_normalizada' => $normalizada,
            'profissao_original' => $original,
            'confianca' => $confianca,
            'regra' => $regra,
            'status_revisao' => $status,
            'precisa_complemento' => $complemento,
            'optout' => $optout,
        ];
    }

    private function profissoesMap(): array
    {
        return [
            'Motorista' => ['motorista'],
            'Mototaxista' => ['mototaxista', 'moto taxista'],
            'Pedreiro' => ['pedreiro'],
            'Servente de pedreiro' => ['servente de pedreiro', 'ajudante de pedreiro'],
            'Eletricista' => ['eletricista'],
            'Encanador' => ['encanador', 'bombeiro hidraulico'],
            'Pintor' => ['pintor'],
            'Carpinteiro' => ['carpinteiro'],
            'Marceneiro' => ['marceneiro'],
            'Vigilante' => ['vigilante'],
            'Porteiro' => ['porteiro'],
            'Auxiliar administrativo' => ['auxiliar administrativo', 'administrativo'],
            'Atendente' => ['atendente'],
            'Recepcionista' => ['recepcionista'],
            'Vendedor' => ['vendedor', 'vendedora'],
            'Operador de caixa' => ['operador de caixa', 'caixa'],
            'Cozinheiro' => ['cozinheiro', 'cozinheira'],
            'Auxiliar de cozinha' => ['auxiliar de cozinha'],
            'Padeiro' => ['padeiro', 'padeira'],
            'Cuidador' => ['cuidador', 'cuidadora', 'cuidadora de idosos'],
            'Domestica' => ['domestica', 'diarista'],
            'Baba' => ['baba'],
            'Agricultor' => ['agricultor', 'agricultura'],
            'Pescador' => ['pescador'],
            'Operador de maquinas' => ['operador de maquinas', 'operador de maquina'],
            'Mecanico' => ['mecanico'],
            'Costureira' => ['costureira'],
            'Cabeleireiro' => ['cabeleireiro', 'cabeleireira'],
            'Manicure' => ['manicure'],
            'Professor' => ['professor', 'professora'],
            'Tecnico de enfermagem' => ['tecnico de enfermagem', 'tecnica de enfermagem'],
        ];
    }

    private function countDestStatus(string $status): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM whatsapp_semas_destinatarios WHERE status = ?");
        $stmt->execute([$status]);
        return (int)$stmt->fetchColumn();
    }

    private function getCampanha(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM whatsapp_semas_campanhas WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Campanha nao encontrada.');
        }
        return $row;
    }

    private function countQueue(int $campanhaId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM whatsapp_semas_destinatarios WHERE campanha_id = ? AND status IN ('na_fila','falha_envio','enviando')");
        $stmt->execute([$campanhaId]);
        return (int)$stmt->fetchColumn();
    }

    private function registrarMensagemSaida(int $campanhaId, int $destId, int $solicitanteId, int $solicitacaoId, string $telefone, string $texto, string $status): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_semas_mensagens (campanha_id, destinatario_id, solicitante_id, solicitacao_id, direcao, tipo, conteudo, telefone, status, data_mensagem)
            VALUES (?, ?, ?, ?, 'saida', 'texto', ?, ?, ?, NOW())
        ");
        $stmt->execute([$campanhaId, $destId, $solicitanteId, $solicitacaoId, $texto, $telefone, $status]);
    }

    private function destinatariosPorTelefone(string $telefone): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM whatsapp_semas_destinatarios
            WHERE telefone_normalizado = ?
              AND status NOT IN ('telefone_invalido','duplicado','opt_out')
            ORDER BY id DESC
            LIMIT 5
        ");
        $stmt->execute([$telefone]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function messageExists(string $externalId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM whatsapp_semas_mensagens WHERE mensagem_externa_id = ?");
        $stmt->execute([$externalId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function hasOptout(string $telefone): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM whatsapp_semas_optout WHERE telefone_normalizado = ? AND ativo = 1");
        $stmt->execute([$telefone]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function registrarOptout(int $solicitanteId, string $telefone, string $motivo): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_semas_optout (solicitante_id, telefone_normalizado, origem, motivo, ativo)
            VALUES (?, ?, 'whatsapp_emprego', ?, 1)
            ON DUPLICATE KEY UPDATE solicitante_id = VALUES(solicitante_id), motivo = VALUES(motivo), ativo = 1
        ");
        $stmt->execute([$solicitanteId, $telefone, $motivo]);
    }

    private function enviarComplementoUmaVez(int $destId, string $telefone): void
    {
        $stmt = $this->pdo->prepare("SELECT complemento_enviado_em FROM whatsapp_semas_destinatarios WHERE id = ?");
        $stmt->execute([$destId]);
        if ($stmt->fetchColumn()) {
            return;
        }
        $msg = "Obrigado pela resposta. Qual profissao ou area de trabalho voce tem interesse em exercer?\n\nVoce pode responder, por exemplo: motorista, pedreiro, auxiliar administrativo, cozinheiro, vendedor ou outra profissao de sua preferencia.";
        $this->whatsapp->enviarTexto($telefone, $msg);
        $this->pdo->prepare("UPDATE whatsapp_semas_destinatarios SET complemento_enviado_em = NOW() WHERE id = ?")->execute([$destId]);
    }

    private function responderOptoutUmaVez(int $destId, string $telefone): void
    {
        $msg = 'Sua solicitacao foi registrada. Voce nao recebera novas mensagens desta campanha de atualizacao cadastral.';
        $this->whatsapp->enviarTexto($telefone, $msg);
    }

    private function audit(string $acao, ?int $solicitanteId, ?int $solicitacaoId, ?int $campanhaId, array $antes, array $depois): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_semas_auditoria (usuario_id, usuario_nome, acao, solicitante_id, solicitacao_id, campanha_id, antes_json, depois_json, ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)($_SESSION['semas_whatsapp_user_id'] ?? 0),
            $this->usuarioNome(),
            $acao,
            $solicitanteId,
            $solicitacaoId,
            $campanhaId ?: null,
            json_encode($antes, JSON_UNESCAPED_UNICODE),
            json_encode($depois, JSON_UNESCAPED_UNICODE),
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }

    private function sanitizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $k = strtolower((string)$key);
            if (strpos($k, 'token') !== false || strpos($k, 'secret') !== false || strpos($k, 'senha') !== false || strpos($k, 'key') !== false) {
                $payload[$key] = '[removido]';
            }
        }
        return $payload;
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }

    private function idsFrom($value): array
    {
        $raw = is_array($value) ? $value : preg_split('/[,\s]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($raw ?: [] as $item) {
            $id = (int)$item;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    public static function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?: '';
        if (strlen($digits) !== 11) {
            return '---';
        }
        return substr($digits, 0, 3) . '.***.***-' . substr($digits, -2);
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) < 8) {
            return '---';
        }
        return substr($digits, 0, 2) . ' *****-' . substr($digits, -4);
    }

    public static function truncate(string $value, int $limit): string
    {
        $value = trim($value);
        if (strlen($value) <= $limit) {
            return $value;
        }
        return substr($value, 0, max(0, $limit - 3)) . '...';
    }

    public static function mensagemPadrao(): string
    {
        return "Ola, {NOME}. A Secretaria Municipal de Assistencia Social de Coari esta realizando uma atualizacao dos cadastros relacionados a area de emprego.\n\nAtualmente, voce possui interesse em trabalhar em alguma destas areas?\n\n1 - Servicos gerais\n2 - Limpeza urbana / gari\n3 - Outra profissao\n4 - Nao tenho interesse no momento\n\nCaso escolha a opcao 3, informe qual profissao ou area de trabalho voce procura.\n\nEsta mensagem e apenas para atualizacao cadastral e nao representa garantia de contratacao ou disponibilidade imediata de vaga.\n\nCaso nao queira receber novas mensagens de atualizacao cadastral, responda SAIR.";
    }
}
