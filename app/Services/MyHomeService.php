<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;

class MyHomeService
{

    protected const URL ='https://api-meninguyim.kommunal.uz/api/external/information/homes/by-filter';
    protected const SEND_URL = 'https://api-meninguyim.kommunal.uz/api/external/home/cadastre';

    public function __construct(protected Client $client)
    {}

    private function sendRequest($url, $method, $options = [])
    {
        try {
            $response = $this->client->request($method, $url, $options);

            $res = $response->getBody()->getContents();

            return json_decode($res);

        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response) {
                $body = json_decode($response->getBody()->getContents());

                throw new \Exception(
                    $body->message ?? $body->error ?? $e->getMessage(),
                    $response->getStatusCode()
                );
            }

            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
    public function getData($homeId = null, $claim = null)
    {
        $options = [
            'auth' => [
                'uyjoynazorat',
                'nC{\'LQ2hfuotx#hVqFm7',
                'basic',
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
            'verify' => false,
        ];

        $query = [];

        if ($homeId) {
            $query['id'] = $homeId;
        } elseif ($claim) {
            if (!$claim?->building_cadastral) {
                return null;
            }
            $query['home_cadastre'] = $claim->building_cadastral;
        } else {
            return null;
        }

        $url = self::URL . '?' . http_build_query($query);

        return $this->sendRequest(url: $url, method: 'GET', options: $options);
    }


    public function sendData($claim, $homeId = null)
    {
        try {
            $response = Http::withBasicAuth(
                'uyjoynazorat',
                "nC{'LQ2hfuotx#hVqFm7"
            )
                ->acceptJson()
                ->withoutVerifying()
                ->connectTimeout(10)
                ->timeout(30)
                ->post(self::SEND_URL . '/' . $homeId, [
                    'home_cadastre' => $claim->building_cadastral,
                ]);

            return [
                 $response->successful(),
                $response->json(),
            ];

        } catch (\Throwable $e) {
            report($e);
            return [
                 false,
                 $e->getMessage(),
            ];
        }
    }
}
