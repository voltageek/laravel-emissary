<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\AgentResponse;
use Emissary\Channel;
use Emissary\Contracts\ChannelAdapter;
use Emissary\Contracts\ChannelIdentityResolver;
use Emissary\Contracts\TenancyResolver;
use Emissary\InboundMessage;
use Emissary\Models\Conversation;
use Emissary\Models\ConversationMessage;
use Illuminate\Contracts\Auth\Authenticatable;
use Ramsey\Uuid\Uuid;

class MessageBridge
{
    private ?Authenticatable $user = null;
    private mixed $tenant = null;
    private ?Conversation $conversation = null;

    public function __construct(
        private TenancyResolver $tenancyResolver,
        private ChannelIdentityResolver $identityResolver,
    ) {}

    public function receive(InboundMessage $message): array
    {
        $this->user = $this->identityResolver->resolveUser($message);
        $this->tenant = $this->tenancyResolver->resolve($message);

        if ($this->tenant !== null) {
            $this->tenancyResolver->activate($this->tenant);
        }

        $this->conversation = Conversation::firstOrCreate(
            [
                'channel' => $message->channel->value,
                'channel_ref' => $message->conversationRef,
            ],
            [
                'tenant_id' => $this->tenant instanceof \Illuminate\Database\Eloquent\Model
                    ? $this->tenant->getKey()
                    : null,
            ],
        );

        $turnId = Uuid::uuid4()->toString();

        ConversationMessage::create([
            'conversation_id' => $this->conversation->id,
            'turn_id' => $turnId,
            'role' => 'user',
            'content' => $message->text,
            'media_url' => $message->mediaUrl,
        ]);

        return [
            'conversation' => $this->conversation,
            'user' => $this->user,
            'tenant' => $this->tenant,
            'turn_id' => $turnId,
        ];
    }

    public function reply(Conversation $conversation, AgentResponse $response)
    {
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'turn_id' => null,
            'role' => 'assistant',
            'content' => $response->content,
            'intent' => $response->intent,
            'error_code' => $response->errorCode,
        ]);
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function conversation(): ?Conversation
    {
        return $this->conversation;
    }
}
