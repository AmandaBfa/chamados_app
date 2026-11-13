<?php

namespace Tests\Unit;

use App\Services\Sla\BusinessCalendar;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BusinessCalendarTest extends TestCase
{
    #[DataProvider('windows')]
    public function test_counts_coverage_and_finds_due_instant(string $start, int $seconds, string $due, array $exceptions = []): void
    {
        $calendar = new BusinessCalendar('America/Sao_Paulo', array_fill(1, 5, [['08:00', '17:00']]), $exceptions);
        $from = CarbonImmutable::parse($start, 'America/Sao_Paulo');
        $expected = CarbonImmutable::parse($due, 'America/Sao_Paulo');
        $actual = $calendar->addSeconds($from, $seconds);

        self::assertSame(0, $actual->offset);
        self::assertTrue($actual->eq($expected));
        self::assertSame($seconds, $calendar->secondsBetween($from, $expected));
    }

    public static function windows(): array
    {
        return [
            'sexta atravessando fim de semana' => ['2026-11-13 16:00', 7200, '2026-11-16 09:00'],
            'janela seguinte' => ['2026-11-12 16:30', 3600, '2026-11-13 08:30'],
            'abertura no sábado' => ['2026-11-14 11:00', 900, '2026-11-16 08:15'],
            'feriado na segunda' => ['2026-11-13 16:00', 7200, '2026-11-17 09:00', ['2026-11-16' => []]],
            'cobertura excepcional sábado' => ['2026-11-13 17:00', 3600, '2026-11-14 11:00', ['2026-11-14' => [['10:00', '11:00']]]],
            'fim exato da janela' => ['2026-11-13 16:59:59', 1, '2026-11-13 17:00:00'],
            'início exato da janela' => ['2026-11-13 08:00:00', 1, '2026-11-13 08:00:01'],
            'almoço contínuo' => ['2026-11-13 11:30', 7200, '2026-11-13 13:30'],
        ];
    }

    public function test_rejects_overlapping_windows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BusinessCalendar('America/Sao_Paulo', [1 => [['08:00', '12:00'], ['11:00', '17:00']]]);
    }
}
