<?php
namespace App\Services;

class AssetService extends BaseService {
    
    private $assets = [
        'bootstrap.min.css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
        'bootstrap.bundle.min.js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
        'all.min.css' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
        'chart.js' => 'https://cdn.jsdelivr.net/npm/chart.js'
    ];

    public function downloadRequiredAssets() {
        $basePath = dirname(__DIR__, 3) . '/public';
        $results = [];

        foreach ($this->assets as $name => $url) {
            $folder = (strpos($name, '.css') !== false) ? 'css' : 'js';
            $filePath = "{$basePath}/{$folder}/{$name}";
            
            if (!file_exists($filePath)) {
                try {
                    $content = @file_get_contents($url);
                    if ($content === false) {
                        throw new \Exception("Erro ao baixar {$url}");
                    }
                    if (!is_dir(dirname($filePath))) {
                        mkdir(dirname($filePath), 0755, true);
                    }
                    file_put_contents($filePath, $content);
                    $results[$name] = 'Baixado com sucesso';
                } catch (\Exception $e) {
                    $results[$name] = 'Erro: ' . $e->getMessage();
                }
            } else {
                $results[$name] = 'Já existe localmente';
            }
        }
        return $results;
    }

    /**
     * Retorna o caminho do asset (local se existir, CDN se não)
     */
    public static function getAssetPath($name, $cdnUrl) {
        $folder = (strpos($name, '.css') !== false) ? 'css' : 'js';
        $localPath = "public/{$folder}/{$name}";
        
        if (file_exists(dirname(__DIR__, 3) . '/' . $localPath)) {
            return $localPath;
        }
        return $cdnUrl;
    }
}
