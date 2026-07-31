<?php
declare(strict_types=1);
namespace App\Integration\SO;
use PDO;
final class BudgetSoIntegrationRepository {
    public function __construct(private readonly PDO $connection) {}
    public function reserve(int $budgetId): array {
        $this->connection->beginTransaction();
        try {
            $lock = $this->connection->prepare('SELECT id, status, so_aquisicao_id FROM orcamentos WHERE id = :id AND excluido_em IS NULL FOR UPDATE'); $lock->execute(['id' => $budgetId]); $budget = $lock->fetch();
            if ($budget === false) throw new \InvalidArgumentException('Orçamento não encontrado.');
            if ($budget['status'] === 'recusado') throw new \InvalidArgumentException('Orçamento recusado não pode ser aprovado.');
            if ($budget['so_aquisicao_id'] !== null) { $this->connection->commit(); return ['already_synced' => true]; }
            $row = $this->connection->prepare('SELECT evento_uuid, status FROM orcamento_integracoes_so WHERE orcamento_id = :id FOR UPDATE'); $row->execute(['id' => $budgetId]); $integration = $row->fetch();
            if ($integration !== false && $integration['status'] === 'processando') { $this->connection->commit(); return ['processing' => true]; }
            $uuid = $integration === false ? $this->uuid() : (string) $integration['evento_uuid'];
            $this->connection->prepare("INSERT INTO orcamento_integracoes_so (orcamento_id, evento_uuid, tipo_evento, status, tentativas, ultima_tentativa_em) VALUES (:id,:uuid,'budget.approved','processando',1,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE status='processando', tentativas=tentativas+1, ultima_tentativa_em=CURRENT_TIMESTAMP, ultimo_erro=NULL")->execute(['id' => $budgetId, 'uuid' => $uuid]);
            $this->connection->prepare('UPDATE orcamentos SET so_evento_uuid = :uuid WHERE id = :id')->execute(['id' => $budgetId, 'uuid' => $uuid]); $this->connection->commit(); return ['event_uuid' => $uuid];
        } catch (\Throwable $e) { if ($this->connection->inTransaction()) $this->connection->rollBack(); throw $e; }
    }
    public function synchronized(int $budgetId, SoAcquisitionResponse $response): void { $this->connection->prepare("UPDATE orcamento_integracoes_so SET status='sincronizado', so_aquisicao_id=:aid, so_aquisicao_numero=:number, so_codigo_entrega=:delivery, so_status=:status, sincronizado_em=CURRENT_TIMESTAMP, proxima_tentativa_em=NULL WHERE orcamento_id=:id")->execute(['id'=>$budgetId,'aid'=>$response->id(),'number'=>$response->number(),'delivery'=>$response->deliveryCode(),'status'=>$response->status()]); $this->connection->prepare('UPDATE orcamentos SET so_aquisicao_id=:aid, so_aquisicao_numero=:number, so_codigo_entrega=:delivery, so_aquisicao_status=:status, so_sincronizado_em=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>$budgetId,'aid'=>$response->id(),'number'=>$response->number(),'delivery'=>$response->deliveryCode(),'status'=>$response->status()]); }
    public function failed(int $budgetId, string $message): void { $this->connection->prepare("UPDATE orcamento_integracoes_so SET status='falhou', ultimo_erro=:error, proxima_tentativa_em=DATE_ADD(CURRENT_TIMESTAMP, INTERVAL CASE WHEN tentativas < 2 THEN 1 WHEN tentativas = 2 THEN 5 WHEN tentativas = 3 THEN 15 WHEN tentativas = 4 THEN 60 ELSE 360 MINUTE END) WHERE orcamento_id=:id")->execute(['id'=>$budgetId,'error'=>mb_substr($message,0,1000),'id'=>$budgetId]); }
    private function uuid(): string { $b=random_bytes(16); $b[6]=chr((ord($b[6])&15)|64); $b[8]=chr((ord($b[8])&63)|128); return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4)); }
}
