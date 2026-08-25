<?php

declare(strict_types=1);

namespace App\ServiceOrder\DTO;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class ServiceOrderScheduleData
{
    private const MIN_DURATION_MINUTES = 5;
    private const MAX_DURATION_MINUTES = 1440;

    public function __construct(
        private readonly DateTimeImmutable $start,
        private readonly DateTimeImmutable $end
    ) {
        if ($this->end <= $this->start) {
            throw new InvalidArgumentException(
                'O fim do agendamento deve ser posterior ao início.'
            );
        }
    }

    public static function fromArray(array $data): ?self
    {
        $startValue = trim((string) (
            $data['agendado_inicio']
            ?? $data['scheduled_start']
            ?? ''
        ));

        $endValue = trim((string) (
            $data['agendado_fim']
            ?? $data['scheduled_end']
            ?? ''
        ));

        $durationValue = trim((string) (
            $data['agendamento_duracao_minutos']
            ?? $data['schedule_duration_minutes']
            ?? $data['duration_minutes']
            ?? ''
        ));

        if (
            $startValue === ''
            && $endValue === ''
            && $durationValue === ''
        ) {
            return null;
        }

        if ($startValue === '') {
            throw new InvalidArgumentException(
                'Informe a data e a hora do serviço.'
            );
        }

        $start = self::dateTime(
            $startValue,
            'data e hora do serviço'
        );

        if ($durationValue === '' && $endValue === '') {
            $durationValue = '60';
        }

        if ($durationValue !== '') {
            $durationMinutes = filter_var(
                $durationValue,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => self::MIN_DURATION_MINUTES,
                        'max_range' => self::MAX_DURATION_MINUTES,
                    ],
                ]
            );

            if ($durationMinutes === false) {
                throw new InvalidArgumentException(
                    'Informe uma duração prevista entre 5 minutos e 24 horas.'
                );
            }

            $end = $start->add(
                new DateInterval(
                    'PT' . (int) $durationMinutes . 'M'
                )
            );

            return new self($start, $end);
        }

        if ($endValue === '') {
            throw new InvalidArgumentException(
                'Informe a duração prevista do serviço.'
            );
        }

        return new self(
            $start,
            self::dateTime(
                $endValue,
                'término previsto do serviço'
            )
        );
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    public function durationMinutes(): int
    {
        return (int) round(
            ($this->end->getTimestamp() - $this->start->getTimestamp()) / 60
        );
    }

    private static function dateTime(
        string $value,
        string $field
    ): DateTimeImmutable {
        $normalized = str_replace('T', ' ', $value);
        $expected = strlen($normalized) === 16
            ? $normalized . ':00'
            : $normalized;

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $expected
        );

        if (
            !$date
            || $date->format('Y-m-d H:i:s') !== $expected
        ) {
            throw new InvalidArgumentException(
                'Informe uma data válida para ' . $field . '.'
            );
        }

        return $date;
    }
}
