<?php

namespace Core\Messaging;

use Core\Contracts\Consumer;
use Core\Contracts\Publisher;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class MessagingServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Configure and register bindings in the container.
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/service_bus.php', 'service_bus');
        $this->app->configure('service_bus');

        if (!$this->app->config->get('service_bus.service_id')) {
            throw new InvalidArgumentException('Event bus not configured. Please declare a unique ID for your service in /config/services.php file with key "service_id" or in your .env file with key "SERVICE_ID".');
        }

        $this->app->singleton(Publisher::class, function () {
            $manager = new MessageBusManager($this->app);
            return $manager->driver($this->app->config->get('service_bus.service_bus_driver'));
        });

        $this->app->singleton(Consumer::class, function () {
            $manager = new MessageBusManager($this->app);
            return $manager->driver($this->app->config->get('service_bus.service_bus_driver'));
        });
    }

    /**
     * Perform post-registration booting of services.
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ConsumeMessages::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return [
            Publisher::class,
            Consumer::class,
        ];
    }
}