<?php

declare(strict_types=1);

namespace Emissary;

use Emissary\Events\AgentCallCompleted;
use Emissary\Events\ConfirmationGateTransitioned;
use Emissary\Events\GuardDecision;
use Emissary\Events\ToolInvocationCompleted;
use Emissary\Events\TurnCompleted;
use Emissary\Events\UserOnboardingTransitioned;
use Emissary\Listeners\CaptureLlmPayload;
use Emissary\Listeners\LogAgentEvent;
use Emissary\Listeners\LogToolInvocation;
use Emissary\Listeners\LogTraceSpan;
use Emissary\Listeners\UpdateCostLedger;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EmissaryEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AgentCallCompleted::class => [
            LogAgentEvent::class,
        ],
        GuardDecision::class => [
            LogAgentEvent::class,
        ],
        ConfirmationGateTransitioned::class => [
            LogAgentEvent::class,
        ],
        TurnCompleted::class => [
            LogAgentEvent::class,
        ],
        UserOnboardingTransitioned::class => [
            LogAgentEvent::class,
        ],
        ToolInvocationCompleted::class => [
            LogToolInvocation::class,
        ],
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(LogAgentEvent::class);
        $this->app->singleton(LogToolInvocation::class);
        $this->app->singleton(UpdateCostLedger::class);
        $this->app->singleton(CaptureLlmPayload::class);
        $this->app->singleton(LogTraceSpan::class);
    }
}
