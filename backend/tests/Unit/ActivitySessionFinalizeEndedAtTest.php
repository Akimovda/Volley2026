<?php

namespace Tests\Unit;

use App\Services\ActivityCalorieService;
use App\Services\ActivitySessionService;
use App\Services\AthleteProfileService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * finalize() теперь принимает ended_at от клиента (тот же паттерн, что и started_at
 * в Build 46, коммит 53b0ace) — санити-проверка на чистой логике, без БД.
 */
class ActivitySessionFinalizeEndedAtTest extends TestCase
{
    private function resolveEndedAt(?float $endedAtTs): Carbon
    {
        $service = new ActivitySessionService(new AthleteProfileService(), new ActivityCalorieService());
        $ref = new \ReflectionMethod($service, 'resolveEndedAt');
        $ref->setAccessible(true);

        return $ref->invoke($service, $endedAtTs);
    }

    public function test_null_ended_at_falls_back_to_now(): void
    {
        $before = now();
        $result = $this->resolveEndedAt(null);
        $after  = now();

        $this->assertTrue($result->betweenIncluded($before, $after));
    }

    public function test_valid_unix_timestamp_is_used_as_is(): void
    {
        $ts = Carbon::create(2026, 7, 9, 7, 12, 0, 'UTC')->timestamp;

        $result = $this->resolveEndedAt((float) $ts);

        $this->assertSame($ts, $result->timestamp);
    }

    #[DataProvider('invalidTimestampsProvider')]
    public function test_invalid_timestamp_falls_back_to_now(float $badTs): void
    {
        $before = now();
        $result = $this->resolveEndedAt($badTs);
        $after  = now();

        $this->assertTrue($result->betweenIncluded($before, $after));
    }

    public static function invalidTimestampsProvider(): array
    {
        return [
            'zero (epoch 1970)'        => [0.0],
            'negative'                 => [-1720512938.0],
            'far future (year 2938)'   => [99999999999.0],
        ];
    }

    public function test_timestamp_within_one_hour_future_is_accepted(): void
    {
        $ts = now()->addMinutes(30)->timestamp;

        $result = $this->resolveEndedAt((float) $ts);

        $this->assertSame($ts, $result->timestamp);
    }

    public function test_timestamp_more_than_one_hour_in_future_is_rejected(): void
    {
        $ts = now()->addHours(2)->timestamp;

        $before = now();
        $result = $this->resolveEndedAt((float) $ts);
        $after  = now();

        $this->assertTrue($result->betweenIncluded($before, $after));
    }
}
