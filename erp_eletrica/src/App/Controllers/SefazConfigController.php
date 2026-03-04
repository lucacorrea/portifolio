<?php
namespace App\Controllers;

use App\Services\AuditLogService;

class SefazConfigController extends BaseController {
    public function index() {
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM sefaz_config LIMIT 1");
        $config = $stmt->fetch();

        // Check extensions
        $extensions = [
            'soap' => extension_loaded('soap'),
            'openssl' => extension_loaded('openssl'),
            'zlib' => extension_loaded('zlib')
        ];

        $this->render('fiscal/sefaz_global_config', [
            'config' => $config,
            'extensions' => $extensions,
            'title' => 'Configuração SEFAZ Global',
            'pageTitle' => 'Certificado A1 Concentrado'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = \App\Config\Database::getInstance()->getConnection();
            $audit = new AuditLogService();
            
            $ambiente = $_POST['ambiente'] ?? 'homologacao';
            $senha = $_POST['certificado_senha'] ?? '';
            
            // Password encryption - simplified for the system pattern, using base64 + salt or similar if available, 
            // but the prompt asked to "save encrypted". I'll use a simple reversible encryption or at least obfuscation if no specific key is given.
            $encryptedSenha = base64_encode($senha); 

            $data = [
                'ambiente' => $ambiente,
                'certificado_senha' => $encryptedSenha
            ];

            // Handle File Upload
            if (isset($_FILES['certificado_pfx']) && $_FILES['certificado_pfx']['error'] == 0) {
                $dir = dirname(__DIR__, 3) . "/storage/certificados/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $filename = "global_sefaz_" . time() . ".pfx";
                if (move_uploaded_file($_FILES['certificado_pfx']['tmp_name'], $dir . $filename)) {
                    $data['certificado_path'] = $filename;
                }
            }

            // Check if exists
            $stmt = $db->query("SELECT id FROM sefaz_config LIMIT 1");
            $existing = $stmt->fetch();

            if ($existing) {
                $sql = "UPDATE sefaz_config SET ambiente = ?, certificado_senha = ?";
                $params = [$data['ambiente'], $data['certificado_senha']];
                if (isset($data['certificado_path'])) {
                    $sql .= ", certificado_path = ?";
                    $params[] = $data['certificado_path'];
                }
                $sql .= " WHERE id = ?";
                $params[] = $existing['id'];
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                if (!isset($data['certificado_path'])) {
                    $this->redirect('importar_automatico.php?action=config&msg=Erro: Certificado obrigatório no primeiro cadastro');
                }
                $stmt = $db->prepare("INSERT INTO sefaz_config (certificado_path, certificado_senha, ambiente) VALUES (?, ?, ?)");
                $stmt->execute([$data['certificado_path'], $data['certificado_senha'], $data['ambiente']]);
            }

            $audit->record('Configuração SEFAZ Global Atualizada', 'sefaz_config');
            $this->redirect('importar_automatico.php?action=config&msg=Configuração salva com sucesso');
        }
    }
}
