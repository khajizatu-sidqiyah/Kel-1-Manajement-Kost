<?php

namespace Tests\Unit;

use App\Services\TenggatSewaService;
use Tests\TestCase;

class TenggatSewaServiceTest extends TestCase
{
    public function test_tanggal_akhir_bulan_tidak_overflow(): void
    {
        $service = new TenggatSewaService();

        $this->assertSame(
            '2026-02-28',
            $service->hitungBerikutnya('2026-01-29')
        );

        $this->assertSame(
            '2026-02-28',
            $service->hitungBerikutnya('2026-01-30')
        );

        $this->assertSame(
            '2026-02-28',
            $service->hitungBerikutnya('2026-01-31')
        );

        $this->assertSame(
            '2026-04-30',
            $service->hitungBerikutnya('2026-03-31')
        );
    }
}
