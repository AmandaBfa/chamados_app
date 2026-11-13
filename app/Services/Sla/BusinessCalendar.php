<?php

namespace App\Services\Sla;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class BusinessCalendar
{
    /**
     * @param  array<int, array<array{string, string}>>  $week  ISO weekdays (1–7)
     * @param  array<string, array<array{string, string}>>  $exceptions  Local dates replacing the normal windows
     */
    public function __construct(
        private readonly string $timezone,
        private readonly array $week,
        private readonly array $exceptions = [],
    ) {
        if (! in_array($timezone, timezone_identifiers_list(), true)
            || array_diff(array_keys($week), range(1, 7)) !== []
            || array_filter($week) === []) {
            throw new InvalidArgumentException('Informe um fuso válido e pelo menos uma janela semanal.');
        }

        foreach ($exceptions as $date => $windows) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
                || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                throw new InvalidArgumentException('Informe as datas de exceção no formato AAAA-MM-DD.');
            }
            $this->validateWindows($windows);
        }
        foreach ($week as $windows) {
            $this->validateWindows($windows);
        }
    }

    private function validateWindows(mixed $windows): void
    {
        if (! is_array($windows) || ! array_is_list($windows)) {
            throw new InvalidArgumentException('Informe uma lista de janelas de atendimento.');
        }
        $previousEnd = '00:00';
        foreach ($windows as $window) {
            if (! is_array($window) || array_keys($window) !== [0, 1]
                || ! is_string($window[0]) || ! is_string($window[1])
                || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $window[0])
                || ! preg_match('/^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/D', $window[1])
                || $window[0] < $previousEnd || $window[1] <= $window[0]) {
                throw new InvalidArgumentException('As janelas devem estar ordenadas, sem sobreposição e com fim após o início.');
            }
            $previousEnd = $window[1];
        }
    }

    private function windows(CarbonImmutable $day): iterable
    {
        foreach ($this->exceptions[$day->toDateString()] ?? $this->week[$day->dayOfWeekIso] ?? [] as [$from, $to]) {
            $start = $day->setTime((int) substr($from, 0, 2), (int) substr($from, 3, 2));
            $end = $to === '24:00' ? $day->addDay()->startOfDay()
                : $day->setTime((int) substr($to, 0, 2), (int) substr($to, 3, 2));
            if ($end->gt($start)) {
                yield [$start->timestamp, $end->timestamp];
            }
        }
    }

    public function secondsBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $seconds = 0;
        for ($day = $from->setTimezone($this->timezone)->startOfDay(); $day->lt($to); $day = $day->addDay()->startOfDay()) {
            foreach ($this->windows($day) as [$start, $end]) {
                $seconds += max(0, min($end, $to->timestamp) - max($start, $from->timestamp));
            }
        }

        return $seconds;
    }

    public function addSeconds(CarbonImmutable $from, int $seconds): CarbonImmutable
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('O tempo de cobertura a adicionar deve ser positivo ou zero.');
        }
        if ($seconds === 0) {
            return $from->utc();
        }

        for ($day = $from->setTimezone($this->timezone)->startOfDay(); ; $day = $day->addDay()->startOfDay()) {
            foreach ($this->windows($day) as [$start, $end]) {
                $start = max($start, $from->timestamp);
                if ($start >= $end) {
                    continue;
                }
                if ($seconds <= $end - $start) {
                    return CarbonImmutable::createFromTimestampUTC($start + $seconds);
                }
                $seconds -= $end - $start;
            }
        }
    }
}
