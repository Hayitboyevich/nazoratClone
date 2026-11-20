<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class ShaffofIntegration extends Command
{

    protected $signature = 'app:shaffof';

    protected $description = 'Command description';


    public function handle()
    {
        $articles = Article::query()
            ->where('sphere_id', 57)
            ->whereNull('shaffof_id')
            ->where('send_shaffof', false)
            ->limit(20)
            ->get();
        foreach ($articles as $article) {
            $shaffofId = null;

            if (!empty($article->reestr_number)) {
                $expertise = $this->getData(config('app.gasn.shaffof_expertise'), $article->reestr_number);
                $shaffofId = $expertise['result']['data']['shaffof_id'] ?? null;
            }
            if (!$shaffofId && !empty($article->number_protocol)) {
                $protocol = $this->getData(config('app.gasn.shaffof_protocol'), $article->number_protocol);
                $shaffofId = $protocol['result']['data']['shaffof_id'] ?? null;
            }

            $updateData = ['send_shaffof' => true];
            if ($shaffofId) {
                $updateData['shaffof_id'] = $shaffofId;
            }
            $article->update($updateData);
            sleep(3);

        }
    }

    function getData(?string $baseUrl, ?string $param = null)
    {
        try {
            $url = $baseUrl . '=' . $param;
            $response = Http::withBasicAuth(
                config('app.passport.login'),
                config('app.passport.password')
            )
                ->timeout(10)
                ->connectTimeout(5)
                ->get($url);

            if ($response->successful()) {
                return $response->json() ?? null;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

    }
}
