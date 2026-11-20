<?php

namespace App\Console\Commands;

use App\Enums\ConstructionWork;
use App\Enums\UserRoleEnum;
use App\Models\Article;
use App\Services\ArticleService;
use App\Services\MyGovService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TaxObjectSend extends Command
{

    protected $signature = 'tax:send';

    public function __construct(protected  MyGovService $service)
    {
        parent::__construct();
    }

    public function handle()
    {

        $authUsername = config('app.passport.login');
        $authPassword = config('app.passport.password');
        $articles = Article::query()->whereNull('send_tax')->limit(20)->get();
        foreach ($articles as $article) {
            try {
                $data = $this->service->getObjectTax($article->id);
                $response = Http::withBasicAuth($authUsername, $authPassword)
                    ->timeout(15)
                    ->connectTimeout(10)
                    ->post('https://api.shaffofqurilish.uz/api/v1/constructionSave', $data);


                if ($response->successful()) {
                    $article->update(['send_tax' => true]);
//                    echo $article->task_id.' ketti ';
                } else {
                    $article->update(['send_tax' => false]);
                    Log::error('API Error: article_id: ' . $article->id . '  ' . $response->body());
                }

                sleep(2);

            } catch (\Exception $exception) {
                Log::error('Tax Error: ' . $exception->getMessage());
            }
        }
    }

}
