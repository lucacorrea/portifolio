<?php
namespace App\Repositories;

class AuditLogRepository extends BaseRepository {
    protected $table = 'audit_logs';

    public function log(array $data) {
        return $this->create([
            'usuario_id' => $data['usuario_id'] ?? ($_SESSION['usuario_id'] ?? null),
            'acao' => $data['acao'],
            'tabela' => $data['tabela'] ?? null,
            'registro_id' => $data['registro_id'] ?? null,
            'dados_anteriores' => isset($data['dados_anteriores']) ? json_encode($data['dados_anteriores']) : null,
            'dados_novos' => isset($data['dados_novos']) ? json_encode($data['dados_novos']) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }
}
