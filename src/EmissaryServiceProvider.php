<?php

declare(strict_types=1);

namespace Emissary;

use Emissary\Channel;
use Emissary\Channels\MetaWhatsAppAdapter;
use Emissary\Channels\WahaWhatsAppAdapter;
use Emissary\Contracts\ChannelAdapter;
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
        $this->app->register(EmissaryEventServiceProvider::class);

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

        $this->app->bind(ChannelAdapter::class . '.whatsapp', function (): ChannelAdapter {
            $backend = config('emissary.channels.whatsapp.backend', 'waha');

            return match ($backend) {
                'meta' => $this->app->make(MetaWhatsAppAdapter::class),
                default => $this->app->make(WahaWhatsAppAdapter::class),
            };
        });
    }

    protected function publishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/emissary.php' => config_path('emissary.php'),
        ], 'emissary-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'emissary-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views/widget.blade.php' => resource_path('views/vendor/emissary/widget.blade.php'),
        ], 'emissary-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'emissary');

        if (file_exists(__DIR__ . '/../routes/webhooks.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Emissary\Commands\EmissaryChannelsList::class,
                \Emissary\Commands\EmissaryWebhookUrl::class,
                \Emissary\Commands\EmissarySetTelegramWebhook::class,
                \Emissary\Commands\EmissaryChannelTest::class,
                \Emissary\Commands\EmissaryChannelAdd::class,
                \Emissary\Commands\EmissaryReport::class,
                \Emissary\Commands\EmissaryReplay::class,
                \Emissary\Commands\EmissaryPrune::class,
                \Emissary\Commands\EmissaryOnboardingStatus::class,
                \Emissary\Commands\EmissaryOnboardingReset::class,
                \Emissary\Commands\EmissaryFixtureCapture::class,
                \Emissary\Commands\EmissaryWahaSessionStart::class,
                \Emissary\Commands\EmissaryWahaSessionStatus::class,
                \Emissary\Commands\EmissaryWahaSessionStop::class,
                \Emissary\Commands\EmissaryWahaSessionRestart::class,
                \Emissary\Commands\EmissaryWahaSessionQr::class,
                \Emissary\Commands\EmissaryWahaSessionList::class,
                \Emissary\Commands\EmissaryWahaSessionDelete::class,
            ]);
        }
    }

    protected function bootPluginProviders(): void
    {
        $this->app->booted(function (): void {
            $guardRegistry = $this->app[GuardRegistry::class];
            $guardRegistry->register(new \Emissary\Guards\RateLimitGuard());
            $guardRegistry->register(new \Emissary\Guards\JailbreakDetectionGuard());
            $guardRegistry->register(new \Emissary\Guards\CostCapGuard());
            $guardRegistry->register(new \Emissary\Guards\MaxTurnsGuard());
            $guardRegistry->register(new \Emissary\Guards\AuthenticatedUserGuard());
            $guardRegistry->register(new \Emissary\Guards\OnboardingGuard());

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
