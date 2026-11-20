<?php

namespace App\Console\Commands;

use App\Http\Resources\AiControlResource;
use App\Http\Resources\RegulationStatusResource;
use App\Models\AiControl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendAiControlCommand extends Command
{

    protected $urlInteg = 'https://api.ainazorat.uz/api/v1/violations/shaffof/violations/';
    protected $url = 'https://api.ainazorat.uz/api/v1/violations/shaffof/regulations/sync/';

    protected $signature = 'ai:send';


    protected $description = 'Command description';


    public function handle()
    {
        $controls = AiControl::query()
            ->whereNull('send_operator')
            ->whereNull('integration')
            ->limit(10)
            ->get();
        foreach ($controls as $control) {
                if (is_null($control->integration)){
                    $data = AiControlResource::make($control);

                    $this->sendOperator($this->urlInteg, $control, $data, 1);

                }else{
                    $data = [
                        'violation_id' => $control->control_id,
                        'type' => $control->type,
                        'regulations' => $control->regulations ? $control->regulations->map(function ($regulation) {
                            return [
                                'id' => $regulation->id,
                                'number' => $regulation->regulation_number,
                                'deadline' => $regulation->deadline,
                                'status' => RegulationStatusResource::make($regulation->regulationStatus),
                            ];
                        }) : null,
                    ];
                    $this->sendOperator($this->url, $control, $data, 2);
                }
        }

    }

    public function sendOperator($url, $model, $data, $type)
    {
        try {
            $response = Http::retry(0, 0)
                ->timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'accept' => 'application/json',
                    'Authorization' =>'Bearer ' .$this->getToken(),
                ])
                ->post($url, $data);

        } catch (\Throwable $e) {

            Log::error("API timeout yoki xato: " . $e->getMessage(), [
                'Id' => $model->id,
            ]);

            if ($type == 1){
                $model->update(['send_operator' => 3]);
            }else{
                $model->update(['integration' => false]);
            }
            return;
        }

        if ($response->successful()) {
            if ($type == 1){
                $model->update(['send_operator' => 2]);
            }else{
                $model->update(['integration' => true]);
            }
        } else {
            if ($type == 1){
                $model->update(['send_operator' => 3]);
            }else{
                $model->update(['integration' => false]);
            }

        }

        usleep(150000);
    }

    public function getToken()
    {
        try {
            $response = Http::retry(0, 0)
                ->timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'accept' => 'application/json',
                ])
                ->post('https://api.ainazorat.uz/api/v1/authentication/login/', [
                    'username' => 'shaffof-integration',
                    'password' => 'Shaffof-integration@123',
                ]);
           return $response->json()['access_token'];
        }catch (\Exception $exception){
            throw $exception;
        }
    }
}
