<?php

declare(strict_types=1);

namespace Emissary\Contracts;

use Emissary\InboundMessage;

interface TenancyResolver
{
    public function resolve(InboundMessage $message): mixed;

    public function activate(mixed $tenant): void;
}
