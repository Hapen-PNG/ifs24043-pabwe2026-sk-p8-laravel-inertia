<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_https_is_forced_in_vercel_production(): void
    {
        $this->withVercelEnvironment('1', function (): void {
            $this->app['env'] = 'production';

            (new AppServiceProvider($this->app))->boot();

            $this->assertStringStartsWith('https://', url('/'));
        });
    }

    public function test_scheme_is_not_forced_outside_production(): void
    {
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('http://', url('/'));
    }

    public function test_https_is_not_forced_in_non_vercel_production(): void
    {
        $this->withVercelEnvironment(null, function (): void {
            $this->app['env'] = 'production';

            (new AppServiceProvider($this->app))->boot();

            $this->assertStringStartsWith('http://', url('/'));
        });
    }

    public function test_postgres_connector_leaves_dsn_unchanged_without_endpoint(): void
    {
        foreach ([[], ['endpoint' => null], ['endpoint' => '']] as $config) {
            $this->assertStringNotContainsString('options=', $this->postgresDsn($config));
        }
    }

    public function test_postgres_connector_adds_neon_endpoint_to_dsn(): void
    {
        $dsn = $this->postgresDsn(['endpoint' => 'ep-example-123']);

        $this->assertStringEndsWith(";options='endpoint=ep-example-123'", $dsn);
    }

    public function test_postgres_connector_rejects_invalid_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->postgresDsn(['endpoint' => 'invalid-endpoint']);
    }

    public function test_postgres_connector_rejects_non_string_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->postgresDsn(['endpoint' => 123]);
    }

    private function postgresDsn(array $overrides = []): string
    {
        $connector = $this->app->make('db.connector.pgsql');
        $method = new ReflectionMethod($connector, 'getDsn');
        $method->setAccessible(true);

        return $method->invoke($connector, array_merge([
            'host' => '127.0.0.1',
            'database' => 'testing',
            'port' => 5432,
            'charset' => 'utf8',
        ], $overrides));
    }

    private function withVercelEnvironment(?string $value, callable $callback): void
    {
        $hadEnv = array_key_exists('VERCEL', $_ENV);
        $previousEnv = $_ENV['VERCEL'] ?? null;
        $hadServer = array_key_exists('VERCEL', $_SERVER);
        $previousServer = $_SERVER['VERCEL'] ?? null;
        $previousProcess = getenv('VERCEL');

        if ($value === null) {
            putenv('VERCEL');
            unset($_ENV['VERCEL'], $_SERVER['VERCEL']);
        } else {
            putenv("VERCEL={$value}");
            $_ENV['VERCEL'] = $value;
            $_SERVER['VERCEL'] = $value;
        }

        try {
            $callback();
        } finally {
            if ($previousProcess === false) {
                putenv('VERCEL');
            } else {
                putenv("VERCEL={$previousProcess}");
            }

            if ($hadEnv) {
                $_ENV['VERCEL'] = $previousEnv;
            } else {
                unset($_ENV['VERCEL']);
            }

            if ($hadServer) {
                $_SERVER['VERCEL'] = $previousServer;
            } else {
                unset($_SERVER['VERCEL']);
            }
        }
    }
}
