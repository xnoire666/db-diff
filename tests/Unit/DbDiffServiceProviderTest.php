<?php

namespace Xnoire666\DbDiff\Tests\Unit;

use Xnoire666\DbDiff\Console\Commands\DbDiffCommand;
use Xnoire666\DbDiff\Tests\TestCase;

class DbDiffServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertIsArray(config('db-diff.connections'));
        $this->assertArrayHasKey('mysql1', config('db-diff.connections'));
        $this->assertArrayHasKey('mysql2', config('db-diff.connections'));
    }

    public function test_db_diff_command_is_registered(): void
    {
        $commands = $this->app[\Illuminate\Contracts\Console\Kernel::class]->all();

        $this->assertArrayHasKey('db:diff', $commands);
        $this->assertInstanceOf(DbDiffCommand::class, $commands['db:diff']);
    }

    public function test_config_file_is_publishable(): void
    {
        $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
            \Xnoire666\DbDiff\DbDiffServiceProvider::class,
            'db-diff-config'
        );

        $this->assertNotEmpty($paths);
    }
}
