<?php

namespace App\Providers;

use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(RepositoryServiceProvider::class);

        $this->app->singleton('elasticsearch', function () {
            $config = config('elasticsearch');

            $clientBuilder = ClientBuilder::create()
                ->setHosts([$config['host']])
                ->setApiKey($config['api_key'])
                ->setHttpClient(new \GuzzleHttp\Client([
                    'timeout' => 5,
                    'connect_timeout' => 3,
                ]));


            if (!$config['ssl_verification']) {
                $clientBuilder->setSSLVerification(false);
            }

            $client = $clientBuilder->build();


//            try {
//                $ping = $client->ping()->asBool();
//            } catch (\Throwable $e) {
//            }

            return $client;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

    }
}
