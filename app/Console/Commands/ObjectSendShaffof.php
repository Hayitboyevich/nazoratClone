<?php

namespace App\Console\Commands;

use App\Enums\ObjectStatusEnum;
use App\Http\Resources\ArticleOpenDataResource;
use App\Models\Article;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class ObjectSendShaffof extends Command
{

    protected $signature = 'app:object-uy';


    protected $description = 'Command description';


    public function handle()
    {
        $articles = Article::query()
            ->whereIn('object_status_id', [ObjectStatusEnum::PROGRESS, ObjectStatusEnum::FROZEN, ObjectStatusEnum::SUSPENDED])
            ->where('uy_integration', 0)
            ->where('sphere_id', 57)
            ->limit(10)
            ->get();
        foreach ($articles as $article) {
            try {
                $data = ArticleOpenDataResource::make($article);




                $response = $this->sendData('https://api-uy.shaffofqurilish.uz/api/gasn-object', $data);
                if ($response)
                {
                    if ($response->getStatusCode() == 200)
                    {
                        $article->update(['uy_integration' => 1]);
                    }else{
                        $article->update(['uy_integration' => 2]);
                    }
                }else{
                    $article->update(['uy_integration' => 2]);
                }
                Log::info('response :'.  $response->getBody());


            } catch (\Exception $exception) {
                Log::info('Xatolik: task_id= ' . $article->task_id . '    ' . $exception->getMessage());
                echo $exception->getMessage() . ' ' . $article->task_id;
                continue;
            }
            sleep(3);
        }



    }


//    private function sendData(?string $url, $data)
//    {
//        try {
//            $response = Http::withBasicAuth('api-bank', 'STSNP6I36V6J')->post('https://api-uy.shaffofqurilish.uz/api/gasn-object', $data);
//            return $response;
//        } catch (\Exception $exception) {
//            Log::error($exception->getMessage());
//        }
//    }

    private function sendData(?string $url, $data)
    {
        try {
            $client = new Client();

            $apiCredentials = 'api-bank:STSNP6I36V6J';
            $headers = [
                'Authorization' => 'Basic ' . base64_encode($apiCredentials),
            ];

            $response = $client->post($url, [
                'headers' => $headers,
                'json'    => $data,
                'timeout' => 10,
                'connect_timeout' => 5,
                'verify'  => false
            ]);

            return $response;

        } catch (\Exception $e) {

            Log::error('responseError: '. $e->getMessage());
        }
    }

}
