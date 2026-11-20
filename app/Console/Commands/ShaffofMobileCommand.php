<?php

namespace App\Console\Commands;

use App\Models\Article;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShaffofMobileCommand extends Command
{

    protected $signature = 'shaffof:mobile';


    protected $description = 'Command description';


    public function handle(): void
    {
        $total = Article::query()->whereNull('shaffof_mobile')->count();

        if ($total === 0) {
            $this->info('Article mavjud emas.');
            return;
        }

        $client = new Client([
            'base_uri' => 'https://mobile.shq.uz',
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $failed = 0;

        Article::query()
            ->select('id')
            ->whereNull('shaffof_mobile')
            ->chunkById(500, function ($articles) use ($client, $bar, &$processed, &$failed) {
                $ids = $articles->pluck('id')->toArray();

                try {
                    $response = $client->post('/api/webhooks/gasn/objects', [
                        'headers' => [
                            'Authorization' => 'Bearer whk_I40hyZsRoiA4VHKa70Y6PThIQpyZRx0KKEqIE0xQ',
                            'Accept' => 'application/json',
                        ],
                        'json' => [
                            'ids' => $ids,
                            'events' => 'updated',
                        ],
                    ]);

                    if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                        Article::query()
                            ->whereIn('id', $ids)
                            ->update(['shaffof_mobile' => true]);

                        Log::info('GASN webhook batch sent', [
                            'count' => count($ids),
                            'status' => $response->getStatusCode(),
                        ]);
                    }

                } catch (\Throwable $exception) {
                    $failed += count($ids);

                    Log::error('GASN webhook batch failed', [
                        'ids' => $ids,
                        'message' => $exception->getMessage(),
                    ]);
                }

                $processed += count($ids);
                $bar->advance(count($ids));
            });

        $bar->finish();

        $this->newLine(2);
        $this->info("Jami: {$total}");
        $this->info("Muvaffaqiyatli: " . ($total - $failed));
        $this->error("Xatolik: {$failed}");
    }
}
