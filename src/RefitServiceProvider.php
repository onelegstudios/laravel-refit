<?php

declare(strict_types=1);

namespace Onelegstudios\Refit;

use Illuminate\Support\ServiceProvider;
use Onelegstudios\Refit\Console\Commands\RefitCommand;

class RefitServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/refit.php', 'refit');

        $this->app->singleton(Refit::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/refit.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'refit');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/refit.php' => config_path('refit.php'),
        ], ['refit', 'refit-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/refit'),
        ], ['refit', 'refit-views']);

        $this->commands([
            RefitCommand::class,
        ]);
    }
}
