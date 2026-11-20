<?php

namespace App\Services;

use Dflydev\DotAccessData\Data;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaMtxService
{
    private string $api;
    private string $webrtc;

    public function __construct()
    {
        $this->api = config('app.mediamtx.api');
        $this->webrtc = config('app.mediamtx.webrtc');
    }
    public function createOrUpdate(string $streamId, string $rtsp): string
    {
        $response = Http::timeout(5)->get(
            "{$this->api}/v3/config/paths/get/{$streamId}"
        );

        if ($response->successful()) {
            $result = Http::timeout(5)->patch(
                "{$this->api}/v3/config/paths/edit/{$streamId}",
                [
                    'source' => $rtsp,
                    'sourceOnDemand' => true,
                ]
            );
        } else {
            $result = Http::timeout(5)->post(
                "{$this->api}/v3/config/paths/add/{$streamId}",
                [
                    'source' => $rtsp,
                    'sourceOnDemand' => true,
                ]
            );
            dd($result);
        }

        if (! $result->successful()) {
            Log::error('MediaMTX path create/update failed', [
                'streamId' => $streamId,
                'status'   => $result->status(),
                'body'     => $result->body(),
            ]);
            throw new \RuntimeException("MediaMTX: failed to register stream {$streamId}");
        }

        return "{$this->webrtc}/{$streamId}";
    }


    public function delete(string $streamId): void
    {
        Http::delete(
            "{$this->api}/v3/config/paths/delete/{$streamId}"
        );
    }

}
