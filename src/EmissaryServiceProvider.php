<?php

declare(strict_types=1);

namespace Emissary;

use Emissary\Contracts\ChannelCredentialStore;
use Emissary\Contracts\ChannelIdentityResolver;
use Emissary\Contracts\ConfirmationGate;
use Emissary\Contracts\TenancyResolver;
use Emissary\Pipeline\DatabaseConfirmationGate;
use Emissary\Pipeline\GuardRegistry;
use Emissary\Pipeline\IntentRouter;
use Emissary\Pipeline\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class EmissaryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/emissary.php', 'emissary'
        );

        $this->singletons();

        $this->bindings();
    }

    public function boot(): void
    {
        $this->publishables();

        $this->bootPluginProviders();
    }

    protected function singletons(): void
    {
        $this->app->singleton(IntentRouter::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(GuardRegistry::class);
    }

    protected function bindings(): void
    {
        $this->app->bind(TenancyResolver::class, NullTenancyResolver::class);

        $this->app->bind(ChannelIdentityResolver::class, match (config('emissary.onboarding.mode')) {
            'channel_first', 'hybrid' => Identity\GuestCreatingChannelIdentityResolver::class,
            default                    => AuthChannelIdentityResolver::class,
        });

        $this->app->bind(
            ChannelCredentialStore::class,
            config('emissary.channel_credential_store'),
        );

        $this->app->bind(ConfirmationGate::class, DatabaseConfirmationGate::class);
    }

    protected function publishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/emissary.php' => config_path('emissary.php'),
        ], 'emissary-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'emissary-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([

            ]);
        }
    }

    protected function bootPluginProviders(): void
    {
        $this->app->booted(function (): void {
            $providers = $this->app->tagged('emissary.providers');

            foreach ($providers as $provider) {
                if ($provider instanceof Contracts\AgentToolProvider) {
                    $this->app[ToolRegistry::class]->registerProvider($provider);
                    $this->app[GuardRegistry::class]->register(...$provider->getGuards());
                    $this->app[IntentRouter::class]->registerIntents($provider->getIntents());
                    $this->app[IntentRouter::class]->registerClassificationHints(
                        $provider->getIntentClassificationHints(),
                    );
                }
            }
        });
    }
}
