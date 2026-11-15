<?php

namespace App\Providers;

use App\Services\Gateway\FakeGateway;
use App\Services\Gateway\GatewayInterface;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GatewayInterface::class, fn () => match (config('gateway.driver')) {
            'fake' => new FakeGateway(),
            default => throw new RuntimeException('Driver gateway tidak dikenal: ' . config('gateway.driver')),
        });
    }

    public function boot(): void
    {
        //
    }
}
