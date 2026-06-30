<?php

declare(strict_types=1);

namespace Emissary\Contracts;

use Emissary\AgentResponse;
use Emissary\InboundMessage;
use Emissary\OutboundMessage;
use Illuminate\Http\Request;

interface ChannelAdapter
{
    public function parse(Request $request): InboundMessage;

    public function verify(Request $request): bool;

    public function formatResponse(AgentResponse $response): OutboundMessage;

    public function send(string $channelRef, OutboundMessage $message): void;
}
