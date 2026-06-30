<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\Contracts\ConfirmationGate;
use Emissary\Events\ConfirmationGateTransitioned;
use Emissary\Models\Conversation;
use RuntimeException;

class DatabaseConfirmationGate implements ConfirmationGate
{
    private string $turnId = '';
    private string $conversationId = '';

    public function setTurnContext(string $turnId, string $conversationId): void
    {
        $this->turnId = $turnId;
        $this->conversationId = $conversationId;
    }

    public function propose(Conversation $conversation, array $action): string
    {
        $action['proposed_at'] = now()->toIso8601String();

        $conversation->update([
            'pending_action' => $action,
        ]);

        event(new ConfirmationGateTransitioned(
            turnId: $this->turnId,
            conversationId: $this->conversationId,
            transition: 'propose',
            toolName: $action['tool_name'] ?? null,
            fields: $action['fields'] ?? null,
        ));

        $template = $action['confirmation_template'] ?? null;

        if ($template === null) {
            return 'Confirm this action?';
        }

        $fields = $action['fields'] ?? [];

        foreach ($fields as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    public function execute(Conversation $conversation): array
    {
        $action = $conversation->pending_action;

        if ($action === null) {
            throw new RuntimeException('No pending action to execute.');
        }

        if ($this->isExpired($conversation)) {
            $conversation->update(['pending_action' => null]);

            event(new ConfirmationGateTransitioned(
                turnId: $this->turnId,
                conversationId: $this->conversationId,
                transition: 'expire',
                toolName: $action['tool_name'] ?? null,
                fields: $action['fields'] ?? null,
            ));

            throw new RuntimeException('Confirmation has expired.');
        }

        $conversation->update(['pending_action' => null]);

        event(new ConfirmationGateTransitioned(
            turnId: $this->turnId,
            conversationId: $this->conversationId,
            transition: 'execute',
            toolName: $action['tool_name'] ?? null,
            fields: $action['fields'] ?? null,
        ));

        return $action;
    }

    public function cancel(Conversation $conversation): void
    {
        $action = $conversation->pending_action;
        $conversation->update(['pending_action' => null]);

        event(new ConfirmationGateTransitioned(
            turnId: $this->turnId,
            conversationId: $this->conversationId,
            transition: 'cancel',
            toolName: $action['tool_name'] ?? null,
            fields: $action['fields'] ?? null,
        ));
    }

    public function isExpired(Conversation $conversation): bool
    {
        $action = $conversation->pending_action;

        if ($action === null) {
            return false;
        }

        $proposedAt = $action['proposed_at'] ?? null;

        if ($proposedAt === null) {
            return false;
        }

        $timeout = config('emissary.confirmation_timeout_seconds', 900);

        $proposedTime = \Carbon\Carbon::parse($proposedAt);

        return abs(now()->diffInSeconds($proposedTime)) > $timeout;
    }
}
