<?php

namespace Rickyx12\DbSync;

use Illuminate\Support\ServiceProvider;
use Rickyx12\DbSync\Console\Commands\DbSyncCommand;

class DbSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/db-sync.php', 'db-sync');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DbSyncCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/db-sync.php' => config_path('db-sync.php'),
            ], 'db-sync-config');
        }
    }
}
