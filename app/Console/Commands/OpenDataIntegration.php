<?php

namespace App\Console\Commands;

use App\Enums\UserRoleEnum;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenDataIntegration extends Command
{

    protected $signature = 'app:open-data-object';


    protected $description = 'Command description';

    public function handle()
    {
        $articles = Article::query()->whereNull('open_data_integration')->limit(15)->get();
        foreach ($articles as $article) {
            $data = $this->sendData($article);
            $article->update([
                'open_data_integration' => $data ? true : false
            ]);

            Log::info('OpenDataIntegration: Sending article ID ' . $article->id);

            sleep(5);
        }

        echo 'Tugadi 15talik keyingisi';

    }

    public function sendData($article)
    {
        try {
            $url = config('app.opendata.object');
            $user = $article->users()->wherePivot('role_id', UserRoleEnum::BUYURTMACHI->value)->first();

            $data = [
                'task_id' => (string)$article->task_id,
                'accepted_at' => $article->created_at->format('Y-m-d H:i:s'),
                'object_name' => $article->name,
                'region_name' => $article->region->name_uz,
                'region_soato' => $article->region->soato,
                'district_name' => $article->district->name_uz,
                'district_soato' => $article->district->soato,
                'customer_name' => $user ? ($user->full_name ?? $user->organization_name) : null,
                'customer_inn_or_pinfl' => $user ? $user->pinfl : null,
                'positive_opinion_number' => $article->positive_opinion_number,
                'number_protocol' => $article->number_protocol,
            ];
            $response = Http::withBasicAuth(
                config('app.opendata.login'),
                config('app.opendata.password')
            )
                ->timeout(10)
                ->connectTimeout(5)
                ->post($url, $data);

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
