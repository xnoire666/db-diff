<?php

namespace Xnoire666\DbDiff;

use Illuminate\Support\ServiceProvider;
use Xnoire666\DbDiff\Console\Commands\DbDiffCommand;

class DbDiffServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/db-diff.php', 'db-diff');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DbDiffCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/db-diff.php' => config_path('db-diff.php'),
            ], 'db-diff-config');
        }
    }
}
