<?php

namespace App\Services;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class CameraService
{
    private const URL ='https://nc.shaffofqurilish.uz/api/v1/devices/cameras/urls?status=online';
    private  const BODY_CAMERA = 'https://api.ainazorat.uz/api/v1/devices/by-facility/';
    private  const LOGIN = 'https://api.ainazorat.uz/api/v1/authentication/login/';

    private const REDIS_KEY = 'cameras';
    private const BODYCAMERA_KEY = 'bodycameras';
    private const INDEX_KEY = 'cameras:shaffof_index';

    public function __construct(protected Client $client){}


    public function setData(): int
    {
        $response = $this->client->get(self::URL, [
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false
        ]);

        $data = json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        if (!is_array($data)) {
            throw new \RuntimeException('Camera API noto‘g‘ri formatda ma’lumot qaytardi.');
        }

        $cameras = [];
        $index = [];

        foreach ($data as $camera) {
            if (!is_array($camera)) {
                continue;
            }

            $cameraId = $camera['id'] ?? null;

            if ($cameraId === null || $cameraId === '') {
                continue;
            }

            $cameraId = (string) $cameraId;

            $cameras[$cameraId] = json_encode(
                $camera,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            $shaffofId = $camera['shaffof_id'] ?? null;

            if ($shaffofId !== null && trim((string) $shaffofId) !== '') {
                $shaffofId = trim((string) $shaffofId);
                $index[$shaffofId][] = $cameraId;
            }
        }

        if (empty($cameras)) {
            throw new \RuntimeException(
                'Kameralar topilmadi. Redis ma’lumotlari o‘zgartirilmadi.'
            );
        }

        $suffix = bin2hex(random_bytes(6));

        $temporaryCameraKey = self::REDIS_KEY . ':tmp:' . $suffix;
        $temporaryIndexKey = self::INDEX_KEY . ':tmp:' . $suffix;

        try {
            $cameraArguments = [$temporaryCameraKey];

            foreach ($cameras as $cameraId => $cameraJson) {
                $cameraArguments[] = $cameraId;
                $cameraArguments[] = $cameraJson;
            }

            Redis::command('HSET', $cameraArguments);

            if (!empty($index)) {
                $indexArguments = [$temporaryIndexKey];

                foreach ($index as $shaffofId => $cameraIds) {
                    $indexArguments[] = (string) $shaffofId;
                    $indexArguments[] = json_encode(
                        array_values(array_unique($cameraIds)),
                        JSON_THROW_ON_ERROR
                    );
                }

                Redis::command('HSET', $indexArguments);
            }

            Redis::command('RENAME', [
                $temporaryCameraKey,
                self::REDIS_KEY,
            ]);

            if (!empty($index)) {
                Redis::command('RENAME', [
                    $temporaryIndexKey,
                    self::INDEX_KEY,
                ]);
            } else {
                Redis::command('DEL', [self::INDEX_KEY]);
            }

            \Illuminate\Support\Facades\Log::info('Camera Redis cache updated', [
                'api_records' => count($data),
                'saved_cameras' => count($cameras),
                'shaffof_index_count' => count($index),
            ]);

            return count($cameras);
        } catch (\Throwable $e) {
            Redis::command('DEL', [$temporaryCameraKey]);
            Redis::command('DEL', [$temporaryIndexKey]);

            \Illuminate\Support\Facades\Log::error('Camera Redis cache update failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getByShaffofId(string|int $shaffofId): array
    {
        $shaffofId = trim((string) $shaffofId);

        if ($shaffofId === '') {
            return [];
        }

        $cameraIdsJson = Redis::hget(
            self::INDEX_KEY,
            $shaffofId
        );

        if (empty($cameraIdsJson)) {
            return [];
        }

        $cameraIds = json_decode($cameraIdsJson, true);

        if (!is_array($cameraIds) || empty($cameraIds)) {
            return [];
        }

        $cameraIds = array_values(array_unique(array_map(
            'strval',
            $cameraIds
        )));

        $cameraData = Redis::hmget(
            self::REDIS_KEY,
            $cameraIds
        );

        $result = [];

        foreach ($cameraData as $cameraJson) {
            if (!is_string($cameraJson) || $cameraJson === '') {
                continue;
            }

            $camera = json_decode($cameraJson, true);

            if (!is_array($camera)) {
                continue;
            }

            $result[] = $camera;
        }

        return $result;
    }


    public function getToken(): string
    {
        return Cache::remember('bodycamera_token', now()->addDay(), function () {
            $response = Http::retry(0, 0)
                ->timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'accept' => 'application/json',
                ])
                ->post(self::LOGIN, [
                    'username' => 'shaffof-integration',
                    'password' => 'Shaffof-integration@123',
                ]);

            $response->throw();

            return $response->json('access_token')
                ?? null;
        });
    }

    public function getBodyCamera(int $id)
    {
        $redis = Redis::connection();

        $cachedCamera = $redis->hget(self::BODYCAMERA_KEY, (string) $id);

        if ($cachedCamera !== false && $cachedCamera !== null) {
            $cachedData = json_decode($cachedCamera, true);

            if (
                json_last_error() === JSON_ERROR_NONE &&
                isset($cachedData['saved_at']) &&
                array_key_exists('data', $cachedData)
            ) {
                $savedAt = Carbon::parse($cachedData['saved_at']);

                if ($savedAt->greaterThan(now()->subMinutes(5))) {
                    return $cachedData['data'];
                }
            }
        } else {
            $cachedData = null;
        }

        $response = Http::retry(0, 0)
            ->timeout(10)
            ->connectTimeout(5)
            ->withHeaders([
                'accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getToken(),
            ])
            ->get(self::BODY_CAMERA . $id);

        if ($response->status() === 200) {
            $data = [
                'id' => $id,
                'data' => $response->json(),
                'saved_at' => now()->toDateTimeString(),
            ];

            $redis->hset(
                self::BODYCAMERA_KEY,
                (string) $id,
                json_encode(
                    $data,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );

            return $data['data'];
        }

        if ($cachedData !== null && array_key_exists('data', $cachedData)) {
            return $cachedData['data'];
        }

        return null;
    }

    public function getOnlineBodyCameras(): array
    {

        $redis = Redis::connection();

        return collect($redis->hgetall(self::BODYCAMERA_KEY))
            ->map(fn ($value) => json_decode($value, true))
            ->filter(fn ($camera) =>
                json_last_error() === JSON_ERROR_NONE &&
                data_get($camera, 'data.is_online') === true
            )
            ->map(fn ($camera) => $camera['id'])
            ->values()
            ->all();
    }

}
