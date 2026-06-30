<?php

declare(strict_types=1);

namespace Emissary\Contracts;

use Emissary\Models\Conversation;

interface ConfirmationGate
{
    public function propose(Conversation $conversation, array $action): string;

    public function execute(Conversation $conversation): array;

    public function cancel(Conversation $conversation): void;

    public function isExpired(Conversation $conversation): bool;
}
