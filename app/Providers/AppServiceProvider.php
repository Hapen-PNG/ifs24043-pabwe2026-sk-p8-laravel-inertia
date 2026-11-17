<?php

namespace App\Providers;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\Services\OpenAiPostGeneratorService;
use App\Features\Post\Contracts\PostRepositoryInterface;
use App\Features\Post\Models\Post;
use App\Features\Post\Policies\PostPolicy;
use App\Features\Post\Repositories\PostRepository;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
        $this->app->bind(AiClientInterface::class, OpenAiPostGeneratorService::class);
        $this->app->bind('db.connector.pgsql', function () {
            return new class extends PostgresConnector
            {
                protected function getDsn(array $config)
                {
                    $dsn = parent::getDsn($config);
                    $endpoint = $config['endpoint'] ?? null;

                    if ($endpoint === null || $endpoint === '') {
                        return $dsn;
                    }

                    if (! is_string($endpoint) || preg_match('/\Aep-[A-Za-z0-9-]+\z/', $endpoint) !== 1) {
                        throw new \InvalidArgumentException('DB_ENDPOINT must be a valid Neon endpoint ID.');
                    }

                    return $dsn.";options='endpoint={$endpoint}'";
                }
            };
        });
    }

    public function boot(): void
    {
        if (
            app()->environment('production')
            && filter_var(env('VERCEL', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            URL::forceScheme('https');
        }

        Gate::policy(Post::class, PostPolicy::class);
    }
}
