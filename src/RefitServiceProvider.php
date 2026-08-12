<?php

declare(strict_types=1);

namespace Onelegstudios\Refit;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Onelegstudios\Refit\Console\Commands\RefitCommand;
use Onelegstudios\Refit\Contracts\Task;

class RefitServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/refit.php', 'refit');

        $this->app->singleton(Refit::class, function (Application $app): Refit {
            $refit = new Refit;

            /** @var list<class-string<Task>> $tasks */
            $tasks = $app->make('config')->get('refit.tasks', []);

            foreach ($tasks as $task) {
                $refit->task($app->make($task));
            }

            return $refit;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * Refit is a one-shot scaffolding command: it serves no routes and publishes
     * no views, so console is the only context it wires anything up in.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/refit.php' => config_path('refit.php'),
        ], ['refit', 'refit-config']);

        $this->commands([
            RefitCommand::class,
        ]);
    }
}
