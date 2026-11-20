<?php

namespace App\Services;

use GuzzleHttp\Client;

class SertService
{
    public function sertGetUserInfo(string $pinfl)
    {
        try {
            $client = new Client();
            $response = $client->post(config('app.sert.url') . $pinfl, [
                'auth' => [config('app.passport.login'), config('app.passport.password')],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() == 200) {
                $data = json_decode($response->getBody()->getContents());

                return $data->result->data->data;
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }
}
