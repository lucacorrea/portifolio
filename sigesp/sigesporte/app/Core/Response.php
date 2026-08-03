<?php
declare(strict_types=1);

namespace Sigesp\Core;

final class Response
{
    public function __construct(private readonly string $content = '', private readonly int $status = 200, private readonly array $headers = []) {}
    public static function redirect(string $url): self
    {
        if (preg_match('/[\\r\\n]/', $url) === 1) {
            throw new \InvalidArgumentException('URL de redirecionamento inválida.');
        }
        if (preg_match('#^https?://#i', $url) !== 1) {
            $url = Url::to($url);
        }
        return new self('', 302, ['Location' => $url]);
    }
    public static function json(array $data, int $status = 200): self { return new self((string) json_encode($data, JSON_THROW_ON_ERROR), $status, ['Content-Type' => 'application/json']); }
    public function send(): never { http_response_code($this->status); foreach ($this->headers as $name => $value) header("$name: $value"); echo $this->content; exit; }
}
