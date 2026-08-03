<?php
declare(strict_types=1);

namespace Sigesp\Shared\Presentation;

/** @deprecated Use Sigesp\Demo\DemoData directly. Kept for compatible presentation views. */
final class DemoData
{
    public static function module(string $module, string $screen = 'index'): array
    {
        return array_merge(\Sigesp\Demo\DemoData::page($module), ['screen' => $screen]);
    }
}
