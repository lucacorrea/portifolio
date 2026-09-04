<?php

declare(strict_types=1);

class SigasEnvironment
{
    private $values = array();

    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function locate()
    {
        $paths = array();

        if (
            isset($_SERVER['SIGAS_ENV_PATH'])
            && is_string($_SERVER['SIGAS_ENV_PATH'])
            && trim($_SERVER['SIGAS_ENV_PATH']) !== ''
        ) {
            $paths[] = trim($_SERVER['SIGAS_ENV_PATH']);
        }

        $envPath = getenv('SIGAS_ENV_PATH');
        if (is_string($envPath) && trim($envPath) !== '') {
            $paths[] = trim($envPath);
        }

        $homePath = getenv('HOME');
        if (is_string($homePath) && trim($homePath) !== '') {
            $paths[] =
                rtrim($homePath, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'configuracao'
                . DIRECTORY_SEPARATOR . 'sigas'
                . DIRECTORY_SEPARATOR . 'conect'
                . DIRECTORY_SEPARATOR . '.env';
        }

        /*
         * Este é o mesmo padrão usado pelo Comida na Mesa para localizar
         * configuracao/anexo/conect/.env.
         *
         * Em semth.com.br resolve para:
         * /home/u784961086/domains/semth.com.br/configuracao/sigas/conect/.env
         */
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $paths[] =
                dirname((string)$_SERVER['DOCUMENT_ROOT'])
                . DIRECTORY_SEPARATOR . 'configuracao'
                . DIRECTORY_SEPARATOR . 'sigas'
                . DIRECTORY_SEPARATOR . 'conect'
                . DIRECTORY_SEPARATOR . '.env';
        }

        foreach (array_unique($paths) as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function load($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                'Arquivo de ambiente da integração SIGAS não está acessível.'
            );
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(
                'Arquivo de ambiente da integração SIGAS não pôde ser lido.'
            );
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $values = array();

        $lines = preg_split('/\R/', (string)$content);

        if (!is_array($lines)) {
            $lines = array();
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '' || substr($line, 0, 1) === '#') {
                continue;
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));

            /*
             * Igual à integração ANEXO: somente variáveis exclusivas
             * da integração são aceitas.
             */
            if (preg_match('/^SIGAS_[A-Z0-9_]+$/', $key) !== 1) {
                continue;
            }

            $value = trim(substr($line, $separator + 1));

            if (strlen($value) >= 2) {
                $first = substr($value, 0, 1);
                $last = substr($value, -1);

                if (
                    ($first === '"' && $last === '"')
                    || ($first === "'" && $last === "'")
                ) {
                    $value = substr($value, 1, -1);
                }
            }

            $lower = strtolower($value);

            if ($lower === 'true') {
                $value = true;
            } elseif ($lower === 'false') {
                $value = false;
            } elseif ($lower === 'null') {
                $value = null;
            }

            $values[$key] = $value;
        }

        return new self($values);
    }

    public function get($key, $default = null)
    {
        $this->assertKey($key);

        return array_key_exists($key, $this->values)
            ? $this->values[$key]
            : $default;
    }

    public function required($key)
    {
        $value = $this->get($key);

        if ($value === null || trim((string)$value) === '') {
            throw new RuntimeException(
                'Variável obrigatória da integração SIGAS não encontrada: ' . $key
            );
        }

        return (string)$value;
    }

    public function bool($key, $default = false)
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        return $parsed === null ? (bool)$default : $parsed;
    }

    public function int($key, $default = 0)
    {
        $value = $this->get($key, $default);

        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        return $parsed === false ? (int)$default : (int)$parsed;
    }

    private function assertKey($key)
    {
        if (preg_match('/^SIGAS_[A-Z0-9_]+$/', (string)$key) !== 1) {
            throw new RuntimeException(
                'Nome de variável inválido para a integração SIGAS.'
            );
        }
    }
}
