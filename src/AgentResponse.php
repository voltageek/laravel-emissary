<?php

declare(strict_types=1);

namespace Emissary;

class AgentResponse
{
    public function __construct(
        public string $content,
        public ?string $intent = null,
        public ?array $toolCalls = null,
        public bool $confirmationRequired = false,
        public ?string $errorCode = null,
    ) {}

    public static function fromContent(string $content): self
    {
        return new self(content: $content);
    }

    public static function fromError(string $errorCode, string $message): self
    {
        return new self(
            content: $message,
            errorCode: $errorCode,
        );
    }

    public function toOutbound(): OutboundMessage
    {
        return new OutboundMessage(text: $this->content);
    }
}
