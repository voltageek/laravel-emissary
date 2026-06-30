<?php

declare(strict_types=1);

namespace Emissary\Testing;

use Carbon\Carbon;

class Clock extends Carbon
{
    private static ?Carbon $frozen = null;

    public static function fake(string $now): static
    {
        $instance = static::parse($now);
        self::$frozen = $instance;
        Carbon::setTestNow($instance);

        return $instance;
    }

    public function advance(int $seconds): void
    {
        $new = $this->copy()->addSeconds($seconds);
        self::$frozen = $new;
        Carbon::setTestNow($new);
    }

    public static function now($tz = null): static
    {
        if (self::$frozen !== null) {
            return self::$frozen->copy()->setTimezone($tz ?? date_default_timezone_get());
        }

        return parent::now($tz);
    }
}
