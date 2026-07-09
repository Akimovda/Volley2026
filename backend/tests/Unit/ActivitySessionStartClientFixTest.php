<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ActivitySessionController;
use App\Services\ActivityCalorieService;
use App\Services\ActivitySessionService;
use App\Services\AthleteProfileService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Build 46: клиент (iOS/watchOS/Android) шлёт local_id вместо client_uuid и
 * started_at как Unix timestamp — контроллер и сервис должны это принимать
 * без изменения поведения для старых клиентов. Тесты только на чистой логике
 * (без БД) — нормализация UUID и sanity-проверка started_at.
 */
class ActivitySessionStartClientFixTest extends TestCase
{
    private function resolveClientUuid(array $data): ?string
    {
        $controller = (new \ReflectionClass(ActivitySessionController::class))->newInstanceWithoutConstructor();
        $ref = new \ReflectionMethod($controller, 'resolveClientUuid');
        $ref->setAccessible(true);

        return $ref->invoke($controller, $data);
    }

    private function resolveStartedAt(?float $startedAtTs): Carbon
    {
        $service = new ActivitySessionService(new AthleteProfileService(), new ActivityCalorieService());
        $ref = new \ReflectionMethod($service, 'resolveStartedAt');
        $ref->setAccessible(true);

        return $ref->invoke($service, $startedAtTs);
    }

    // --- local_id / client_uuid ---

    public function test_local_id_is_used_when_client_uuid_absent(): void
    {
        $this->assertSame('ABC-123', $this->resolveClientUuid(['local_id' => 'abc-123']));
    }

    public function test_client_uuid_takes_priority_over_local_id(): void
    {
        $this->assertSame('FROM-CLIENT-UUID', $this->resolveClientUuid([
            'client_uuid' => 'from-client-uuid',
            'local_id'    => 'from-local-id',
        ]));
    }

    public function test_both_absent_returns_null(): void
    {
        $this->assertNull($this->resolveClientUuid([]));
    }

    #[DataProvider('caseVariantsProvider')]
    public function test_uuid_normalized_to_uppercase(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->resolveClientUuid(['local_id' => $input]));
    }

    public static function caseVariantsProvider(): array
    {
        return [
            'lowercase'       => ['a1b2c3d4-e5f6', 'A1B2C3D4-E5F6'],
            'already upper'   => ['A1B2C3D4-E5F6', 'A1B2C3D4-E5F6'],
            'mixed case'      => ['aB1c-D2eF', 'AB1C-D2EF'],
        ];
    }

    // --- started_at sanity ---

    public function test_null_started_at_falls_back_to_now(): void
    {
        $before = now();
        $result = $this->resolveStartedAt(null);
        $after  = now();

        $this->assertTrue($result->betweenIncluded($before, $after));
    }

    public function test_valid_unix_timestamp_is_used_as_is(): void
    {
        // 2026-07-09 06:55:38 UTC
        $ts = Carbon::create(2026, 7, 9, 6, 55, 38, 'UTC')->timestamp;

        $result = $this->resolveStartedAt((float) $ts);

        $this->assertSame($ts, $result->timestamp);
    }

    #[DataProvider('invalidTimestampsProvider')]
    public function test_invalid_timestamp_falls_back_to_now(float $badTs): void
    {
        $before = now();
        $result = $this->resolveStartedAt($badTs);
        $after  = now();

        $this->assertTrue($result->betweenIncluded($before, $after));
    }

    public static function invalidTimestampsProvider(): array
    {
        return [
            'zero (epoch 1970)'    => [0.0],
            'negative'             => [-1720512938.0],
            'far future (99999999999)' => [99999999999.0],
        ];
    }

    public function test_timestamp_within_one_hour_future_is_accepted(): void
    {
        $ts = now()->addMinutes(30)->timestamp;

        $result = $this->resolveStartedAt((float) $ts);

        $this->assertSame($ts, $result->timestamp);
    }

    public function test_timestamp_more_than_one_hour_in_future_is_rejected(): void
    {
        $ts = now()->addHours(2)->timestamp;

        $before = now();
        $result = $this->resolveStartedAt((float) $ts);
        $after  = now();

        $this->assertTrue($result->betweenIncluded($before, $after));
    }
}
