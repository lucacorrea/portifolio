<?php
require_once dirname(__DIR__) . '/Helpers/view.php';

abstract class Controller
{
    protected function view(string $path, array $data = []): void
    {
        render_view($path, $data);
    }
}
