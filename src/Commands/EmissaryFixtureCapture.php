<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Models\AgentEvent;
use Emissary\Models\ConversationMessage;
use Emissary\Models\LlmPayload;
use Emissary\Models\ToolInvocation;
use Illuminate\Console\Command;

class EmissaryFixtureCapture extends Command
{
    protected $signature = 'emissary:fixture:capture {turn_id : The turn UUID to capture} {--name= : Fixture name for the output file}';
    protected $description = 'Export a captured turn as a JSON regression fixture';

    public function handle(): int
    {
        $turnId = $this->argument('turn_id');
        $name = $this->option('name') ?: str_replace('-', '_', $turnId);

        $events = AgentEvent::where('turn_id', $turnId)->orderBy('created_at')->get();
        $tools = ToolInvocation::where('turn_id', $turnId)->orderBy('created_at')->get();
        $messages = ConversationMessage::where('turn_id', $turnId)->orderBy('created_at')->get();
        $payloads = LlmPayload::where('turn_id', $turnId)->orderBy('created_at')->get();

        $fixture = [
            'turn_id' => $turnId,
            'captured_at' => now()->toIso8601String(),
            'events' => $events->toArray(),
            'tools' => $tools->toArray(),
            'messages' => $messages->toArray(),
            'payloads' => $payloads->toArray(),
        ];

        $dir = base_path('tests/Fixtures/Agent');
        @mkdir($dir, 0755, true);

        $path = "{$dir}/{$name}.json";
        file_put_contents($path, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Fixture written to: {$path}");

        return self::SUCCESS;
    }
}
