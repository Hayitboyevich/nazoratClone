<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Claim;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ClaimsShaffofGetId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shaffof:get_id';

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
        $claims = Claim::query()
            ->select('object_id')
            ->WhereNotNull('object_id')
            ->whereNull('shaffof_id')
            ->where('get_shaffof_id', 0)
            ->distinct()
            ->limit(20)
            ->pluck('object_id');

        foreach ($claims as $object_id) {
            $article = Article::query()
                ->find($object_id);
            $shaffofId = null;

            if ($article->reestr_number){
                $expertise = $this->getData(config('app.gasn.shaffof_expertise'), $article->reestr_number);
                $shaffofId = $expertise['result']['data']['shaffof_id'] ?? null;
            }
            if (is_null($shaffofId) && !empty($article->number_protocol)){
                $protocol = $this->getData(config('app.gasn.shaffof_protocol'), $article->number_protocol);
                $shaffofId = $protocol['result']['data']['shaffof_id'] ?? null;
            }

            $claim = Claim::query()->where('object_id',$object_id);

            if ($shaffofId) {
                $claim->update(['shaffof_id' => $shaffofId, 'get_shaffof_id' => 1]);
            } else {
                $claim->update(['get_shaffof_id' => 2]);
            }
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
