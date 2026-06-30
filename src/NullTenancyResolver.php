<?php

declare(strict_types=1);

namespace Emissary;

use Emissary\Contracts\TenancyResolver;

class NullTenancyResolver implements TenancyResolver
{
    public function resolve(InboundMessage $message): mixed
    {
        return null;
    }

    public function activate(mixed $tenant): void
    {
        //
    }
}
