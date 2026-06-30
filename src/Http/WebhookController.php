<?php

declare(strict_types=1);

namespace Emissary\Http;

use Emissary\Channels\TelegramAdapter;
use Emissary\Channels\WebChatAdapter;
use Emissary\Channels\WhatsAppAdapter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function whatsapp(Request $request, WhatsAppAdapter $adapter)
    {
        if ($request->isMethod('GET')) {
            $challenge = $adapter->handshake($request);

            if ($challenge === null) {
                return response('', 403);
            }

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        if (! $adapter->verify($request)) {
            return response('', 401);
        }

        $message = $adapter->parse($request);

        return $this->dispatchToPipeline($message, $adapter);
    }

    public function telegram(Request $request, TelegramAdapter $adapter)
    {
        if (! $adapter->verify($request)) {
            return response('', 401);
        }

        $message = $adapter->parse($request);

        return $this->dispatchToPipeline($message, $adapter);
    }

    public function web(Request $request, WebChatAdapter $adapter)
    {
        if (! $adapter->verify($request)) {
            return response('', 401);
        }

        $message = $adapter->parse($request);

        return $this->dispatchToPipeline($message, $adapter);
    }

    private function dispatchToPipeline($message, $adapter)
    {
        // The pipeline dispatch is wired by ProcessMessage job or direct handler.
        // For now, this is a stub that returns the parsed message.
        return response()->json([
            'status' => 'received',
            'channel' => $message->channel->value,
            'conversation_ref' => $message->conversationRef,
        ]);
    }
}
