<?php

namespace App\Console\Commands;

use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Article;
use App\Models\ClaimMonitoring;
use App\Models\ClaimOperator;
use App\Models\UserInfo;
use App\Services\CameraService;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;

class LogClear extends Command
{
    protected $signature = 'log:clear';
    protected $description = 'Send objects to external API';


    public function handle()
    {
//        File::put(storage_path('logs/laravel.log'), '');
//        $this->info('Log files have been cleared!');

//        Redis::connection()->del('bodycameras');


        $cameraService = app(CameraService::class);

        Article::query()
            ->where('object_status_id', ObjectStatusEnum::PROGRESS)
            ->select('id')
            ->chunkById(500, function ($articles) use ($cameraService) {
                foreach ($articles as $article) {
                    try {
                        $cameraService->getBodyCamera($article->id);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });






    }
}
