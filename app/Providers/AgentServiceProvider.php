<?php

namespace App\Providers;

use App\Agent\Contracts\EmbeddingProvider;
use App\Agent\Contracts\MemoryStore;
use App\Agent\Memory\DatabaseMemoryStore;
use App\Agent\Memory\HashingEmbedder;
use App\Agent\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Merangkai komponen agent. Seluruh ketergantungan diikat lewat kontrak,
 * sehingga penyedia embedding, penyimpanan memori, maupun daftar tool dapat
 * ditukar tanpa menyentuh kode runtime.
 */
class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmbeddingProvider::class, fn () => new HashingEmbedder(
            (int) config('agent.memory.dimensions', 128),
        ));

        $this->app->singleton(MemoryStore::class, function ($app) {
            return match ((string) config('agent.memory.store', 'database')) {
                default => $app->make(DatabaseMemoryStore::class),
            };
        });

        $this->app->singleton(ToolRegistry::class, fn ($app) => new ToolRegistry(
            $app,
            (array) config('agent.tools', []),
        ));
    }
}
