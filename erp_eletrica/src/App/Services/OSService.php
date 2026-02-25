<?php
namespace App\Services;

use App\Models\OS;
use Exception;

class OSService extends BaseService {
    public function __construct() {
        parent::__construct(new OS());
    }

    public function createWithSla(array $data) {
        $slaHours = $data['sla_prazo_horas'] ?? 48;
        $data['data_vencimento_sla'] = date('Y-m-d H:i:s', strtotime("+{$slaHours} hours"));
        
        $id = $this->repository->create($data);
        $this->logAction('os_created', 'os', $id, null, $data);
        return $id;
    }

    public function uploadPhotos(int $osId, array $files) {
        $uploaded = [];
        $targetDir = dirname(__DIR__, 3) . '/public/uploads/os/';
        
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $newName = "os_{$osId}_" . uniqid() . ".{$ext}";
                $targetFile = $targetDir . $newName;
                
                if (move_uploaded_file($files['tmp_name'][$key], $targetFile)) {
                    $uploaded[] = $newName;
                }
            }
        }
        
        if (!empty($uploaded)) {
            $os = $this->repository->find($osId);
            $currentFotos = json_decode($os['fotos'] ?? '[]', true);
            $newFotos = array_merge($currentFotos, $uploaded);
            $this->repository->update($osId, ['fotos' => json_encode($newFotos)]);
            $this->logAction('os_photos_uploaded', 'os', $osId, $currentFotos, $newFotos);
        }
        
        return $uploaded;
    }

    public function saveSignature(int $osId, string $signatureData) {
        $this->repository->update($osId, ['assinatura_digital' => $signatureData]);
        $this->logAction('os_signature_saved', 'os', $osId);
    }

    public function updateStatus(int $osId, string $newStatus, string $observation = '') {
        $os = $this->repository->find($osId);
        $oldStatus = $os['status'];
        
        $this->repository->update($osId, ['status' => $newStatus]);
        
        // Record in history table too if it exists (database_update.sql has os_historico)
        $this->db->prepare("INSERT INTO os_historico (os_id, status_anterior, status_novo, observacao, usuario_id) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$osId, $oldStatus, $newStatus, $observation, $_SESSION['usuario_id']]);
        
        $this->logAction('os_status_changed', 'os', $osId, ['status' => $oldStatus], ['status' => $newStatus]);
    }
}
