<?php

declare(strict_types=1);

use Carbon\Carbon;
use Emissary\Testing\Clock;

test('fake freezes time at the given moment', function (): void {
    Clock::fake('2026-06-30 10:00:00');

    $now = Clock::now();

    expect($now->toDateTimeString())->toBe('2026-06-30 10:00:00');
});

test('advance moves frozen time forward by seconds', function (): void {
    $clock = Clock::fake('2026-06-30 10:00:00');
    $clock->advance(900);

    $now = Clock::now();

    expect($now->toDateTimeString())->toBe('2026-06-30 10:15:00');
});

test('advance via static Clock fake', function (): void {
    $clock = Clock::fake('2026-06-30 10:00:00');
    $clock->advance(3600);

    expect(Clock::now()->toDateTimeString())->toBe('2026-06-30 11:00:00');
});

test('now returns Carbon instance', function (): void {
    Clock::fake('2026-06-30 10:00:00');

    expect(Clock::now())->toBeInstanceOf(Carbon::class);
});
