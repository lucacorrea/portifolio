<?php

declare(strict_types=1);

namespace App\Schedule\Service;

final class AgendaDayBoard
{
    private const GROUPS = [
        'reminder_active' => ['Compromissos pendentes', 'bi-alarm'],
        'reminder_completed' => ['Compromissos feitos', 'bi-check-circle'],
        'other' => ['Outros', 'bi-three-dots'],
    ];

    /** @param array<int,array<string,mixed>> $events @return array<int,array{key:string,label:string,icon:string,events:array}> */
    public static function group(array $events): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => [$label, $icon]) {
            $groups[$key] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'events' => []];
        }

        foreach ($events as $event) {
            $type = (string) ($event['type'] ?? '');
            if ($type !== 'reminder') {
                continue;
            }
            $status = (string) ($event['status'] ?? '');
            $key = match (true) {
                $status === 'ativo' => 'reminder_active',
                $status === 'concluido' => 'reminder_completed',
                default => 'other',
            };
            $groups[$key]['events'][] = $event;
        }

        return array_values(array_filter($groups, static fn(array $group): bool => $group['events'] !== []));
    }
}
