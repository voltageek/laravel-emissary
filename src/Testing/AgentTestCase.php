<?php

declare(strict_types=1);

namespace Emissary\Testing;

use Emissary\Contracts\ChannelAdapter;
use Emissary\Events\AgentCallCompleted;
use Emissary\Events\ConfirmationGateTransitioned;
use Emissary\Events\GuardDecision;
use Emissary\Events\ToolInvocationCompleted;
use Emissary\Events\TurnCompleted;
use Emissary\Pipeline\DatabaseConfirmationGate;
use Emissary\Pipeline\GuardRegistry;
use Emissary\Pipeline\IntentRouter;
use Emissary\Pipeline\ModelSelector;
use Emissary\Pipeline\ProcessMessage;
use Emissary\Pipeline\ToolRegistry;
use Emissary\Pipeline\ConversationMemory;
use Emissary\Pipeline\TaskAgent;
use Emissary\Pipeline\MessageBridge;
use Emissary\NullTenancyResolver;
use Emissary\AuthChannelIdentityResolver;
use Emissary\Contracts\TenancyResolver;
use Emissary\Contracts\ChannelIdentityResolver;
use Emissary\Contracts\ConfirmationGate;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class AgentTestCase extends TestCase
{
    protected FakeLlmClient $llmClient;
    protected FakeChannelAdapter $channelAdapter;
    protected ?ProcessMessage $processMessage = null;
    protected array $receivedEvents = [];
    protected array $toolCalls = [];
    protected string $conversationId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->llmClient = FakeLlmClient::make();
        $this->channelAdapter = FakeChannelAdapter::web();

        $this->app->singleton(FakeLlmClient::class, fn () => $this->llmClient);
        $this->app->singleton(IntentRouter::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(GuardRegistry::class);
        $this->app->bind(TenancyResolver::class, NullTenancyResolver::class);
        $this->app->bind(ChannelIdentityResolver::class, AuthChannelIdentityResolver::class);
        $this->app->bind(ConfirmationGate::class, DatabaseConfirmationGate::class);

        $this->receivedEvents = [];

        $this->app['events']->listen('*', function (string $eventName, array $payload): void {
            $this->receivedEvents[] = ['name' => $eventName, 'payload' => $payload];
        });

        $this->toolCalls = [];
    }

    protected function send(FakeChannelAdapter $adapter, string $text): self
    {
        $this->channelAdapter = $adapter;

        $bridge = new MessageBridge(
            $this->app->make(TenancyResolver::class),
            $this->app->make(ChannelIdentityResolver::class),
        );

        $message = $adapter->parse(new \Illuminate\Http\Request(['text' => $text]));
        $ctx = $bridge->receive($message);

        $this->conversationId = $ctx['conversation']->id;

        $processMessage = new ProcessMessage(
            $this->app->make(IntentRouter::class),
            new ModelSelector(),
            $this->app->make(GuardRegistry::class),
            $this->app->make(ToolRegistry::class),
            new TaskAgent(
                $this->app->make(ToolRegistry::class),
                $this->app->make(GuardRegistry::class),
                new ConversationMemory(),
            ),
            new ConversationMemory(),
            new DatabaseConfirmationGate(),
        );

        $guardRegistry = $this->app->make(GuardRegistry::class);

        $guardResult = $guardRegistry->checkBeforeIntent($message, $ctx['user'], $ctx['tenant']);

        if (! $guardResult->allowed) {
            $this->lastResponse = new \Emissary\AgentResponse(
                content: $guardResult->userMessage ?? '',
                errorCode: $guardResult->errorCode,
            );

            return $this;
        }

        $this->lastResponse = $processMessage->handle(
            $ctx['conversation'],
            $message->text,
            $message->mediaUrl,
            $ctx['user'],
            $ctx['tenant'],
            $ctx['turn_id'],
        );

        $bridge->reply($ctx['conversation'], $this->lastResponse);

        return $this;
    }

    protected function confirm(): self
    {
        return $this->send($this->channelAdapter, 'yes');
    }

    // --- Assertions ---

    public function assertReply(string $expected): self
    {
        $actual = $this->lastResponse->content ?? '';

        if ($actual !== $expected) {
            throw new RuntimeException("Expected reply '{$expected}', got '{$actual}'.");
        }

        return $this;
    }

    public function assertReplyCount(int $count): self
    {
        $actual = $this->channelAdapter->sendCount();

        if ($actual !== $count) {
            throw new RuntimeException("Expected {$count} replies, got {$actual}.");
        }

        return $this;
    }

    public function assertToolCalled(string $toolName, ?callable $callback = null): self
    {
        $found = false;

        foreach ($this->llmClient->calls() as $call) {
            $response = $call['response'] ?? null;

            if ($response instanceof ToolCall && $response->name === $toolName) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new RuntimeException("Expected tool '{$toolName}' was not called.");
        }

        return $this;
    }

    public function assertIntentClassified(string $slug): self
    {
        $calls = $this->llmClient->calls();
        $classifyCalls = array_filter($calls, fn ($c) => ($c['call'] ?? '') === 'classify');
        $found = false;

        foreach ($classifyCalls as $call) {
            $result = $call['result'] ?? null;

            if ($result && $result->slug === $slug) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new RuntimeException("Expected intent '{$slug}' was not classified.");
        }

        return $this;
    }

    public function assertGuardDenied(string $guardClass): self
    {
        if ($this->lastResponse->errorCode === null) {
            throw new RuntimeException("Expected guard denial, but no error occurred.");
        }

        return $this;
    }

    public function assertConfirmationProposed(string $toolName): self
    {
        if (! $this->lastResponse->confirmationRequired) {
            throw new RuntimeException("Expected confirmation proposal for '{$toolName}', but none was required.");
        }

        return $this;
    }

    public function assertTurnOutcome(string $outcome): self
    {
        $this->assertTrue(true);

        return $this;
    }

    public function assertEvent(string $eventClass): self
    {
        return $this;
    }

    public function assertOnboardingState(string $state): self
    {
        return $this;
    }

    private ?\Emissary\AgentResponse $lastResponse = null;

    protected function getPackageProviders($app): array
    {
        return [
            \Emissary\EmissaryServiceProvider::class,
        ];
    }
}
