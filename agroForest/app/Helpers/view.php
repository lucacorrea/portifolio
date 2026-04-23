<?php
function render_view(string $path, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/Views/' . $path . '.php';
}
