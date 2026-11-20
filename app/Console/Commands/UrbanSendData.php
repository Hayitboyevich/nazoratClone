<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Claim;
use App\Models\DxaResponse;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UrbanSendData extends Command
{
    protected $url = 'https://api.shaffofqurilish.uz/api/v1/request/urban-external-collector-api';

    protected $signature = 'urban:data {--type=}';



    protected $description = 'Command description';


    public function handle()
    {
        $type = $this->option('type');

        try {
            switch ($type) {

                case "1":
                case "2":
                    $this->processDxaResponses($type);
                    break;

                case "3":
                    $this->processArticle($type);
                    break;

                case "4":
                    $this->processClaim($type);
                    break;

                default:
                    $this->error('Noto‘g‘ri type kiritildi!');
                    return Command::FAILURE;
            }

            $this->info("\nYakunlandi!");
            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error("Operator:data exception", [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("Xatolik yuz berdi: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    private function processDxaResponses(string $type)
    {
        $taskIds = DxaResponse::query()
            ->whereNull('send_urban')
            ->where('notification_type', $type)
            ->limit(40)
            ->get()
            ->map(fn($row) => (string)($row->task_id ?: $row->manual_task_id));

        if ($taskIds->isEmpty()) {
            $this->info("Yuboriladigan ma'lumot yo‘q.");
            return;
        }

        $bar = $this->output->createProgressBar($taskIds->count());
        $bar->start();

        foreach ($taskIds as $taskId) {
            try {
                $this->sendToOperator($taskId, $type, DxaResponse::class);
                $bar->advance();
            } catch (\Throwable $e) {
                Log::error('DXA SEND ERROR', [
                    'task_id' => $taskId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $bar->finish();
    }

    private function processArticle(string $type)
    {
        $taskIds = Article::query()
            ->whereNull('send_urban')
            ->limit(10)
            ->get()
            ->pluck('id');

        if ($taskIds->isEmpty()) {
            $this->info("Article bo‘yicha yuboriladigan ma'lumot yo‘q.");
            return;
        }

        foreach ($taskIds as $taskId) {
            try {
                $this->sendToOperator($taskId, $type, Article::class);
            } catch (\Throwable $e) {
                Log::error('ARTICLE SEND ERROR', [
                    'task_id' => $taskId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function processClaim(string $type)
    {

        $taskIds = Claim::query()
            ->whereNull('send_urban')
            ->limit(20)
            ->get()
            ->pluck('guid');

        if ($taskIds->isEmpty()) {
            $this->info("Claim bo‘yicha yuboriladigan ma'lumot yo‘q.");
            return;
        }

        foreach ($taskIds as $taskId) {
            try {
                $this->sendToOperator($taskId, $type, Claim::class);
            } catch (\Throwable $e) {
                Log::error('CLAIM SEND ERROR', [
                    'task_id' => $taskId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function sendToOperator(string $taskId, string $type, string $modelClass)
    {
        if ($type == 4) {
            $model = $modelClass::query()
                ->where('guid', $taskId)
                ->first();
        }
        elseif($type == 3){
            $model = $modelClass::query()
                ->where('id', $taskId)
                ->first();
        }
        else {
            $model = $modelClass::query()
                ->where('task_id', $taskId)
                ->orWhere('manual_task_id', $taskId)
                ->first();
        }


        if (!$model) {
            Log::warning("Topilmadi (taskId): $taskId");
            return;
        }

        try {
            $client = new Client();
            $apiCredentials = config('app.passport.login') . ':' . config('app.passport.password');

            $response = $client->post($this->url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($apiCredentials),
                ],
                'json' => [
                    'type' => 'CONSTRUCTION_MINISTRY',
                    'dataType' => 'GASN',
                    'body' => [
                        'type' => $type,
                        'task_id' => $taskId,
                    ],
                ],
                'timeout' => 10,
                'connect_timeout' => 5,
            ]);

            if ($response->getStatusCode() == 200) {
                $model->update(['send_urban' => true]);
            }else{
                $model->update(['send_urban' => false]);
            }




        } catch (\Throwable $e) {

            Log::error("API timeout yoki xato: " . $e->getMessage(), [
                'task_id' => $taskId
            ]);

            $model->update(['send_urban' => false]);

            return;
        }

//        if ($response->ok()) {
//            $model->update(['send_urban' => true]);
//            Log::info("Operatorga yuborildi: $taskId");
//        } else {
//            $model->update(['send_urban' => false]);
//            Log::warning("Yuborilmadi (failed): $taskId");
//        }

//        usleep(150000);
    }

}
