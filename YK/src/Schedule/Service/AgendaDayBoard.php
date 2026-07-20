<?php

declare(strict_types=1);

namespace App\Schedule\Service;

final class AgendaDayBoard
{
    private const GROUPS = [
        'reminder_active' => ['Lembretes pendentes', 'bi-alarm'],
        'agendada' => ['Agendadas', 'bi-calendar2-check'],
        'em_deslocamento' => ['Em deslocamento', 'bi-truck'],
        'em_execucao' => ['Em execução', 'bi-play-circle'],
        'aguardando_peca' => ['Aguardando peça', 'bi-box-seam'],
        'finalizada' => ['OS finalizadas', 'bi-check2-circle'],
        'reminder_completed' => ['Lembretes feitos', 'bi-check-circle'],
        'cancelada' => ['Canceladas', 'bi-x-circle'],
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
            $status = (string) ($event['status'] ?? '');
            $key = match (true) {
                $type === 'reminder' && $status === 'ativo' => 'reminder_active',
                $type === 'reminder' && $status === 'concluido' => 'reminder_completed',
                $type === 'service_order' && isset($groups[$status]) => $status,
                default => 'other',
            };
            $groups[$key]['events'][] = $event;
        }

        return array_values(array_filter($groups, static fn(array $group): bool => $group['events'] !== []));
    }
}
