<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Claim;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaimsSendShaffof extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'claims:send_to_shaffof';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $claims = Claim::select('shaffof_id')
            ->whereNotNull('shaffof_id')
            ->where('shaffof_integration', false)
            ->distinct()
            ->limit(50)
            ->pluck('shaffof_id');
        foreach ($claims as $shaffofId) {
            $guids = Claim::where('shaffof_id', $shaffofId)
                ->whereNotNull('guid')
                ->where('shaffof_integration', false)
                ->pluck('guid')
                ->values();

            if ($guids->isEmpty()) {
                continue;
            }

            $response = $this->sendShaffof(config('app.gasn.shaffof_push_claims'), $guids, $shaffofId);

            if ($response) {
                Claim::whereIn('guid', $guids)->update(['shaffof_integration' => true]);
            }
        }
    }

    function sendShaffof(?string $baseUrl, $guids, $shaffofId)
    {
        try {
            $url = $baseUrl . '?value=' . $guids . '&shaffof_id=' . $shaffofId;
            $response = Http::withBasicAuth(
                config('app.passport.login'),
                config('app.passport.password')
            )
                ->timeout(10)
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
