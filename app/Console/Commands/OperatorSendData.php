<?php

namespace App\Console\Commands;

use App\Http\Resources\ArticleResource;
use App\Http\Resources\DxaResponseResource;
use App\Models\Article;
use App\Models\Claim;
use App\Models\DxaResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperatorSendData extends Command
{
    protected $url = 'https://api.ainazorat.uz/api/v1/facilities/sync/';
    protected $token = 'Token a0b3aad960b54f2a5b9a4b4dde2aeee6268ec53b';
    protected $signature = 'operator:data {--type=}';


    protected $description = 'Command description';

//    public function handle()
//    {
//        $response = Http::timeout(5)
//            ->withHeaders([
//                'accept' => 'application/json',
//                'Authorization' => 'Token a0b3aad960b54f2a5b9a4b4dde2aeee6268ec53b',
//            ])
//            ->post('https://api-inspeksiya.zerodev.uz/api/v1/facilities/conclutions/shaffof/', [
//                'inspector' => 1,
//                "facility" =>  "146873867"
//            ]);
//
//
//    }


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
            ->whereNull('send_operator')
            ->where('notification_type', $type)
            ->limit(40)
            ->get()
            ->map(fn($row) => (string)($row->task_id ?: $row->manual_task_id));

        if ($taskIds->isEmpty()) {
            $this->info("Yuboriladigan ma'lumot yo‘q.");
            return;
        }

        // Progress bar
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
            ->whereNull('send_operator')
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
            ->whereNull('send_operator')
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
            $response = Http::retry(0, 0)
                ->timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'accept' => 'application/json',
                    'Authorization' => $this->token,
                ])
                ->post($this->url . '?type=' . $type . '&task_id=' . $taskId);

        } catch (\Throwable $e) {

            Log::error("API timeout yoki xato: " . $e->getMessage(), [
                'task_id' => $taskId
            ]);

            $model->update(['send_operator' => false]);

            return;
        }

        if ($response->successful()) {
            $model->update(['send_operator' => true]);
            Log::info("Operatorga yuborildi: $taskId");
        } else {
            $model->update(['send_operator' => false]);
            Log::warning("Yuborilmadi (failed): $taskId");
        }

        usleep(150000);
    }
}
