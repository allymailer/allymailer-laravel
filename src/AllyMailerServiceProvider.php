<?php

declare(strict_types=1);

namespace AllyMailer\Wrapper;

use Illuminate\Contracts\Container\Container;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use AllyMailer\Client\ApiClient;
use AllyMailer\Client\Configuration;
use AllyMailer\AllyMailerClient;
use AllyMailer\Wrapper\Http\Controllers\WebhookController;
use AllyMailer\Wrapper\Mailer\AllyMailerTransport;

/**
 * Registers the AllyMailer API client (AllyMailerClient, ApiClient, Configuration) as singletons, the "allymailer" mail
 * transport and the webhook route.
 */
final class AllyMailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/allymailer.php', 'allymailer');

        $this->app->singleton(Configuration::class, static function (Container $app): Configuration {
            /** @var array{api_key?: string|null, host?: string|null, timeout?: float|int|string|null} $config */
            $config = $app->make('config')->get('allymailer', []);
            $apiKey = (string)($config['api_key'] ?? '');
            if ($apiKey === '') {
                throw new \InvalidArgumentException('AllyMailer: set the API key in config/allymailer.php (ALLYMAILER_API_KEY).');
            }
            $host = $config['host'] ?? null;

            return new Configuration(
                $apiKey,
                null === $host || '' === $host ? Configuration::DEFAULT_BASE_URL : Configuration::baseUrlFor((string)$host),
                (float)($config['timeout'] ?? 30),
                platform: 'laravel.2.1.2',
            );
        });
        $this->app->singleton(ApiClient::class, static fn (Container $app): ApiClient => new ApiClient($app->make(Configuration::class)));
        $this->app->singleton(AllyMailerClient::class, static fn (Container $app): AllyMailerClient => new AllyMailerClient($app->make(Configuration::class)));
        $this->app->alias(AllyMailerClient::class, 'allymailer');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/allymailer.php' => $this->app->configPath('allymailer.php')], 'allymailer-config');
        }

        $this->callAfterResolving('mail.manager', function (MailManager $manager): void {
            $manager->extend('allymailer', fn (): AllyMailerTransport => new AllyMailerTransport(
                $this->app->make(ApiClient::class),
                null,
                $this->app->bound('log') ? $this->app->make('log') : null,
            ));
        });

        $config = $this->app->make('config');
        if ($config->get('allymailer.webhook.enabled', true) && !$this->app->routesAreCached()) {
            $path = (string)$config->get('allymailer.webhook.path', '/allymailer/webhook');
            /** @var list<string> $middleware */
            $middleware = (array)$config->get('allymailer.webhook.middleware', []);
            $router = $this->app->make('router');
            $router->match(['GET', 'HEAD', 'POST'], $path, WebhookController::class)
                ->middleware($middleware)
                ->name('allymailer.webhook');
        }
    }
}
