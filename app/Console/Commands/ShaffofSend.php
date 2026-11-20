<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShaffofSend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:shaffof-send';


    protected $description = 'Command description';


    public function handle()
    {
        $duplicateShaffofIds = Article::select('shaffof_id')
            ->whereNotNull('shaffof_id')
            ->where('shaffof_integration', false)
            ->groupBy('shaffof_id')
            ->havingRaw('COUNT(*) > 0')
            ->pluck('shaffof_id');

        foreach ($duplicateShaffofIds as $shaffofId) {
            $taskIds = Article::where('shaffof_id', $shaffofId)
                ->whereNotNull('task_id')
                ->pluck('task_id')
                ->values();

            if ($taskIds->isEmpty()) {
                continue;
            }

            $response = $this->sendShaffof(config('app.gasn.shaffof_push'), $taskIds, $shaffofId);

            if ($response) {
                Article::whereIn('task_id', $taskIds)->update(['shaffof_integration' => true]);
            }
        }
    }

    function sendShaffof(?string $baseUrl,  $tasks, $shaffofId)
    {
        try {
            $url = $baseUrl.'?value='.$tasks.'&shaffof_id='.$shaffofId;
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
            Log::info($e->getMessage());
            return null;
        }

    }
}
