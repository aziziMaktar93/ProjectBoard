<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard against tests ever running against a real, non-isolated database.
     *
     * If Laravel's config gets cached (bootstrap/cache/config.php), it stops
     * re-reading .env and phpunit.xml's <env> overrides are silently ignored,
     * so RefreshDatabase would run migrate:fresh against whatever database
     * .env points at instead of the isolated in-memory SQLite one — wiping
     * real data. This has happened before; never let it happen silently again.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')) {
            throw new \RuntimeException(
                'Refusing to run tests: APP_ENV is not "testing". This usually means '.
                'bootstrap/cache/config.php is stale — run `php artisan config:clear`.'
            );
        }

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($database !== ':memory:') {
            throw new \RuntimeException(
                "Refusing to run tests: database.connections.{$connection}.database is ".
                "\"{$database}\", not \":memory:\". RefreshDatabase would run migrate:fresh ".
                'against a real database. This usually means bootstrap/cache/config.php is '.
                'stale and phpunit.xml\'s <env> overrides are being ignored — run '.
                '`php artisan config:clear` before testing again.'
            );
        }

        return $app;
    }
}
