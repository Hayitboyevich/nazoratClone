<?php

namespace App\Console\Commands;

use App\Enums\UserRoleEnum;
use App\Models\Article;
use App\Models\Claim;
use App\Models\District;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenDataClaimIntegration extends Command
{

    protected $signature = 'app:open-data-claim';


    protected $description = 'Command description';


    public function handle()
    {
        $claims = Claim::query()
            ->whereNull('open_data_integration')
            ->whereNotNull('object_id')
            ->where('status', 20)->limit(20)->get();

        foreach ($claims as $claim) {
            $data =  $this->sendData($claim);
            if ($data){
                $claim->update(['open_data_integration' => true]);
            }else{
                $claim->update(['open_data_integration' => false]);
            }

            Log::info('OpenDataIntegration: Sending article ID ' . $claim->id);
            sleep(5);
        }


    }

    public function sendData($claim)
    {
        try {
            $url = config('app.opendata.claim');
            $object = Article::query()->find($claim->object_id);
            $user = $object->users()->wherePivot('role_id', UserRoleEnum::QURILISH->value)->first();

            $data = [
                'task_id' => (string)$claim->guid,
                'created_at' => $claim->created_at,
                'closed_at' => $claim->end_date,
                'object_name' => $claim->building_name,
                'region_name' => Region::query()->where('soato', $claim->region)->first()->name_uz,
                'region_soato' => $claim->region,
                'district_name' => District::query()->where('soato', $claim->district)->first()->name_uz,
                'district_soato' => $claim->district,
                'industry_name' => $claim->ind_name,
                'customer_name' => $claim->legal_name,
                'customer_inn_or_pinfl' => $claim->legal_tin,
                'builder_inn' => $user ? $user->pinfl : null,
                'builder_name' => $user ? ($user->full_name ?? $user->organization_name) : null,
                'photos' => []
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
            echo $e->getMessage();
        }
    }
}
