<?php

namespace App\Infrastructure\ExternalApis\ShaffofId;
use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client;

class ShaffofIdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShaffofIdClient::class, function () {
            return new ShaffofIdClient(
                new Client([
                    'base_uri' => config('app.shaffofId.url'),
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'verify' => false
                ])
            );
        });
    }


    public function boot(): void
    {
        //
    }
}
