<?php

namespace App\Infrastructure\ExternalApis\ShaffofId;

use App\Models\User;
use GuzzleHttp\Client;

class ShaffofIdClient
{
    private string $accessTokenUrl = 'oauth/token';
    private string $userInfoUrl = 'oauth/userinfo';
    private string $refreshSessionUrl = 'oauth/logout';
    private string $sendDocumentUrl = 'api/documents';
    private string $checkSigningStatusUrl = 'api/documents/%s';

    public function __construct(private readonly Client $client)
    {
    }


    private function sendRequest($url, $method, $headers_with_body = [])
    {
        try {
            $res = $this->client->request($method, $url, $headers_with_body)->getBody()->getContents();
            return json_decode($res);
        }catch (\Throwable $e) {
            report($e);
            throw $e;
        }

    }

    public function getAccessToken(?string $grantType, ?string $codeVerify = null, ?string $code = null, ?string $scope = null, ?string $redirect_uri = null)
    {
        $headers['form_params'] = [
            'grant_type' => $grantType,
            'client_id' => config('app.shaffofId.client_id'),
            'client_secret' => config('app.shaffofId.client_secret')
        ];

        if ($code) {
            $headers['form_params']['code'] = $code;
        }
        if ($redirect_uri) {
            $headers['form_params']['redirect_uri'] = $redirect_uri;
        }
        if ($codeVerify) {
            $headers['form_params']['code_verifier'] = $codeVerify;
        }
        if ($scope) {
            $headers['form_params']['scope'] = $scope;
        }

        return $this->sendRequest(url: $this->accessTokenUrl, method: 'POST', headers_with_body: $headers);
    }

    public function getInfo(string $accessToken)
    {
        $headers = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken
            ]
        ];
        return $this->sendRequest(url: $this->userInfoUrl, method: 'GET', headers_with_body: $headers);
    }

    public function refreshSession($idToken)
    {
        $headers = [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'client_id' => config('app.shaffofId.client_id'),
                'id_token_hint' => $idToken,
                'post_logout_redirect_uri' => config('app.shaffofId.redirect_url'),
            ],
        ];
        return $this->sendRequest(url: $this->refreshSessionUrl, method: 'GET', headers_with_body: $headers);
    }

    public function sendSigningDocument(
        User $user,
        string $accessToken,
        ?string $returnUrl,
        string $title,
        array $body,
        string $uuid,
        ?string $callback = null,
        ?string $contentType = null,
        $test = null
    )
    {
        $data = [
            "title" => $title,
            "body" => $body,
            "methods" => ["eimzo"],
            "signer_pinfl" => $user->pinfl,
            'external_id' => $uuid,
            "callback_url" => $callback,
            "return_url" => $returnUrl,
            "ttl" => 86400,
        ];
        if ($test) {
           $data['test'] = true;
        }
        $headers = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => $contentType,
            ],
            'json' => $data
        ];
        return $this->sendRequest(url: $this->sendDocumentUrl, method: 'POST', headers_with_body: $headers);
    }


    public function checkSigningStatus(string $accessToken, string $externalUuid)
    {
        $headers = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken
            ]
        ];
        $url = sprintf($this->checkSigningStatusUrl, $externalUuid);
        return $this->sendRequest(url: $url, method: 'GET', headers_with_body: $headers);
    }

}
