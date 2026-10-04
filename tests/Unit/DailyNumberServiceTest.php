<?php

namespace Tests\Unit;

use App\Services\DailyNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_increment_within_a_day_and_reset_on_the_next_day(): void
    {
        $service = app(DailyNumberService::class);
        Carbon::setTestNow('2026-09-21 09:00:00');

        $this->assertSame('RX-20260921-0001', $service->next('prescription', 'RX'));
        $this->assertSame('RX-20260921-0002', $service->next('prescription', 'RX'));
        $this->assertSame('PT-20260921-0001', $service->next('patient', 'PT'));

        Carbon::setTestNow('2026-09-22 09:00:00');

        $this->assertSame('RX-20260922-0001', $service->next('prescription', 'RX'));
        $this->assertSame('PT-20260922-0001', $service->next('patient', 'PT'));
    }
}
