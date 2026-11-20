<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendCadastralNumberToTurarJoyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-cadastral-number-to-turar-joy-command
                            {--limit= : Test uchun nechta obyekt yuborilsin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Articles jadvalidagi kadastr raqamlarini 10 talab TurarJoy API ga yuborish';

    private const CHUNK = 10;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sent = 0;
        $failed = 0;
        $limit = (int)$this->option('limit');

        if ($limit > 0) {
            $this->query()
                ->limit($limit)
                ->get()
                ->chunk(self::CHUNK)
                ->each(function ($articles) use (&$sent, &$failed) {
                    $this->handleChunk($articles, $sent, $failed);
                });
        } else {
            $this->query()
                ->chunkById(self::CHUNK, function ($articles) use (&$sent, &$failed) {
                    $this->handleChunk($articles, $sent, $failed);
                });
        }

        $this->info("Jami yuborilgan: $sent, xatolik: $failed");
    }

    private function query(): Builder
    {
        return Article::query()
            ->whereNull('send_turarjoy')
            ->whereNotNull('cadastral_number')
            ->where('sphere_id', 57)
            ->where('cadastral_number', '!=', '')
            ->orderBy('id');
    }

    private function handleChunk($articles, int &$sent, int &$failed): void
    {
        foreach ($articles as $article) {
            $number = trim($article->cadastral_number);

            if ($number === '') {
                continue;
            }

            if ($this->send($number)) {
                $article->update(['send_turarjoy' => true]);

                $sent++;
                $this->info("Yuborildi: #$article->id - $number");
            } else {
                $failed++;
                $this->error("Yuborilmadi: #$article->id - $number");
            }
        }

        sleep(1);
    }

    private function send(string $number): bool
    {
        try {
            $response = Http::withBasicAuth(
                config('app.turarjoy.login'),
                config('app.turarjoy.password')
            )
                ->acceptJson()
                ->timeout(30)
                ->connectTimeout(10)
                ->post(config('app.turarjoy.url'), [
                    'cadastr_number' => $number,
                ]);

            if (!$response->successful() || $response->json('status') !== true) {
                Log::info('TurarJoy cadastral send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'cadastr_number' => $number,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $exception) {
            Log::info('TurarJoy cadastral send error: ' . $exception->getMessage(), [
                'cadastr_number' => $number,
            ]);

            return false;
        }
    }
}
