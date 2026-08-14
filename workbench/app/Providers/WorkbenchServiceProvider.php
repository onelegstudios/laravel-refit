<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * Laradocs defaults to the running application's base_path('docs'), which
     * under the workbench is Testbench's skeleton rather than this repository.
     * Pointing it at the package's own docs/ is what makes `composer serve`
     * render the documentation at /docs.
     */
    public function register(): void
    {
        $this->app->make('config')->set('laradocs.docs.path', dirname(__DIR__, 3).'/docs');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
