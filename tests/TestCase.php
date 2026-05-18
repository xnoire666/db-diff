<?php

namespace Xnoire666\DbDiff\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Xnoire666\DbDiff\DbDiffServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [DbDiffServiceProvider::class];
    }
}
