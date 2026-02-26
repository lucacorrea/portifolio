<?php
namespace App\Services;

class CacheService {
    private static $cacheDir = __DIR__ . '/../../../cache/';

    public static function get($key) {
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data['expires'] > time()) {
                return $data['content'];
            }
            unlink($file);
        }
        return null;
    }

    public static function set($key, $content, $duration = 1800) { // 30 min default
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }
        $file = self::$cacheDir . md5($key) . '.cache';
        $data = [
            'expires' => time() + $duration,
            'content' => $content
        ];
        file_put_contents($file, json_encode($data));
    }

    public static function clear() {
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
