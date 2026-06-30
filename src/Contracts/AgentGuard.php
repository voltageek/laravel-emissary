<?php

declare(strict_types=1);

namespace Emissary\Contracts;

use Emissary\GuardResult;
use Emissary\InboundMessage;
use Illuminate\Contracts\Auth\Authenticatable;

interface AgentGuard
{
    public function getName(): string;

    public function beforeIntent(
        InboundMessage $message,
        ?Authenticatable $user,
        mixed $tenant,
    ): GuardResult;

    public function beforeExecution(
        string $intent,
        ?Authenticatable $user,
        mixed $tenant,
    ): GuardResult;

    public function beforeTool(
        string $toolName,
        array $arguments,
        ?Authenticatable $user,
        mixed $tenant,
    ): GuardResult;
}
