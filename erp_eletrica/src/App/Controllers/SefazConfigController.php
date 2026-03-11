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
            
            $data = [
                'ambiente' => $ambiente,
                'certificado_senha' => base64_encode($senha) // Mantendo padrão Base64 para global
            ];

            // Handle File Upload
            if (isset($_FILES['certificado_pfx']) && $_FILES['certificado_pfx']['error'] == 0) {
                $pfxContent = file_get_contents($_FILES['certificado_pfx']['tmp_name']);
                $certs = [];
                
                // Validação Real: Tenta ler o certificado com a senha fornecida
                if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
                    $this->redirect('importar_automatico.php?action=config&msg=Erro: Senha incorreta ou arquivo de certificado inválido');
                    return;
                }

                $dir = dirname(__DIR__, 3) . "/storage/certificados/";
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $filename = "global_sefaz_" . time() . ".pfx";
                if (move_uploaded_file($_FILES['certificado_pfx']['tmp_name'], $dir . $filename)) {
                    $data['certificado_path'] = $filename;
                }
            } else {
                // Se não enviou novo arquivo, mas mudou a senha, precisamos validar a senha com o arquivo existente
                $stmt = $db->query("SELECT certificado_path FROM sefaz_config LIMIT 1");
                $existing = $stmt->fetch();
                if ($existing && !empty($existing['certificado_path'])) {
                    $path = dirname(__DIR__, 3) . "/storage/certificados/" . $existing['certificado_path'];
                    if (file_exists($path)) {
                        $pfxContent = file_get_contents($path);
                        $certs = [];
                        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
                            $this->redirect('importar_automatico.php?action=config&msg=Erro: A senha informada não confere com o certificado atual');
                            return;
                        }
                    }
                }
            }

            // Check if exists
            $stmt = $db->query("SELECT id FROM sefaz_config LIMIT 1");
            $existingConfig = $stmt->fetch();

            if ($existingConfig) {
                $sql = "UPDATE sefaz_config SET ambiente = ?, certificado_senha = ?";
                $params = [$data['ambiente'], $data['certificado_senha']];
                if (isset($data['certificado_path'])) {
                    $sql .= ", certificado_path = ?";
                    $params[] = $data['certificado_path'];
                }
                $sql .= " WHERE id = ?";
                $params[] = $existingConfig['id'];
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                if (!isset($data['certificado_path'])) {
                    $this->redirect('importar_automatico.php?action=config&msg=Erro: Certificado obrigatório no primeiro cadastro');
                    return;
                }
                $stmt = $db->prepare("INSERT INTO sefaz_config (certificado_path, certificado_senha, ambiente) VALUES (?, ?, ?)");
                $stmt->execute([$data['certificado_path'], $data['certificado_senha'], $data['ambiente']]);
            }

            $audit->record('Configuração SEFAZ Global Atualizada', 'sefaz_config');
            $this->redirect('importar_automatico.php?action=config&msg=Configuração salva com sucesso');
        }
    }
}
