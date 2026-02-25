<?php
namespace App\Services;

use App\Repositories\AuditLogRepository;

class AuditLogService extends BaseService {
    public function __construct() {
        parent::__construct(new AuditLogRepository());
    }

    public function record($action, $table = null, $id = null, $oldData = null, $newData = null) {
        return $this->repository->log([
            'acao' => $action,
            'tabela' => $table,
            'registro_id' => $id,
            'dados_anteriores' => $oldData,
            'dados_novos' => $newData
        ]);
    }
}
