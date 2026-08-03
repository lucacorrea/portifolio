<?php
declare(strict_types=1);

$normalizeBasePath = static function (string $value): string {
    $value = trim($value);
    if ($value === '' || $value === '/') {
        return '';
    }

    if (preg_match('/[\\x00-\\x20\\x7f\\\\?#]/', $value) === 1 || str_contains($value, '://')) {
        return '';
    }

    $value = '/' . trim((string) preg_replace('#/+#', '/', $value), '/');
    foreach (explode('/', trim($value, '/')) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/^[A-Za-z0-9._~-]+$/D', $segment) !== 1) {
            return '';
        }
    }

    return $value;
};

$configuredBasePath = (string) (getenv('APP_BASE_PATH') ?: '');
if (trim($configuredBasePath) === '') {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDirectory = str_replace('\\', '/', dirname($scriptName));
    $configuredBasePath = in_array($scriptDirectory, ['', '.', '/'], true)
        ? ''
        : $scriptDirectory;
}

$demoModeValue = getenv('DEMO_MODE');
$demoMode = $demoModeValue === false
    ? true
    : filter_var($demoModeValue, FILTER_VALIDATE_BOOL);

return [
    'name' => getenv('APP_NAME') ?: 'SIGESP',
    'environment' => getenv('APP_ENV') ?: ($demoMode ? 'demo' : 'production'),
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) (getenv('APP_URL') ?: ''), '/'),
    'base_path' => $normalizeBasePath($configuredBasePath),
    'demo_mode' => $demoMode,
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Manaus',
    'session_lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 120),
    'upload_max_size' => (int) (getenv('UPLOAD_MAX_SIZE') ?: 10485760),
];
