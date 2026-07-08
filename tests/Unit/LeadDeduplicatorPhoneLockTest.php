<?php

namespace Tests\Unit;

use App\Support\Leads\LeadDeduplicator;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LeadDeduplicatorPhoneLockTest extends TestCase
{
    public function test_it_runs_the_callback_once_when_the_lock_is_acquired(): void
    {
        $calls = 0;

        $result = LeadDeduplicator::withPhoneLock(1, '79991234567', function () use (&$calls) {
            $calls++;

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(1, $calls);
    }

    public function test_it_runs_the_callback_exactly_once_when_lock_acquisition_throws(): void
    {
        // Воспроизводит прод-инцидент: Cache::lock()->block() бросает исключение из
        // acquire() (у нас это был fopen на битые права каталога кэша) ДО вызова
        // колбэка. Лид не должен теряться — колбэк обязан выполниться один раз.
        $lock = \Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andThrow(new \RuntimeException('fopen(...): Failed to open stream'));
        $lock->shouldNotReceive('release');

        Cache::shouldReceive('lock')->once()->andReturn($lock);

        $calls = 0;
        $result = LeadDeduplicator::withPhoneLock(1, '79991234567', function () use (&$calls) {
            $calls++;

            return 'created-once';
        });

        $this->assertSame('created-once', $result);
        $this->assertSame(1, $calls, 'callback must run exactly once when the cache/lock layer is broken');
    }

    public function test_it_does_not_rerun_the_callback_when_release_throws_after_success(): void
    {
        // Зеркальный случай: колбэк успешно выполнился, а сбой произошёл в
        // release() (finally у Lock::block()) — колбэк НЕ должен повториться.
        $lock = \Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->andReturn(true);
        $lock->shouldReceive('release')->once()->andThrow(new \RuntimeException('release failed'));

        Cache::shouldReceive('lock')->once()->andReturn($lock);

        $calls = 0;

        try {
            LeadDeduplicator::withPhoneLock(1, '79991234567', function () use (&$calls) {
                $calls++;

                return 'created-once';
            });
            $this->fail('Expected the release() failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('release failed', $e->getMessage());
        }

        $this->assertSame(1, $calls, 'callback must not be re-run when only release() fails');
    }

    public function test_it_skips_the_lock_entirely_when_phone_is_null(): void
    {
        $calls = 0;

        $result = LeadDeduplicator::withPhoneLock(1, null, function () use (&$calls) {
            $calls++;

            return 'no-phone';
        });

        $this->assertSame('no-phone', $result);
        $this->assertSame(1, $calls);
    }
}
