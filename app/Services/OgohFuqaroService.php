<?php

namespace App\Services;

use App\Enums\OgohAppealStatusEnum;
use App\Models\District;
use App\Models\OgohAppeal;
use App\Models\OgohAppealEvent;
use App\Models\Region;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * "Ogoh fuqaro" integratsiyasi.
 *  - ogohfuqaro murojaatni POST qiladi -> receive() -> ogoh_appeals
 *  - inspeksiya javob beradi -> answer()/message() -> ogoh_appeal_events (outbox) -> send()
 *  - yuborilmay qolganlarini `php artisan ogoh:send-events` qayta yuboradi
 */
class OgohFuqaroService
{
    /** Har bir muvaffaqiyatsiz urinishdan keyingi kutish (daqiqa) */
    const RETRY_MINUTES = [1, 2, 5, 10, 30, 60, 120, 240, 480, 720];

    /**
     * Ogoh fuqarodan kelgan murojaatni saqlaydi.
     *
     * @return array [httpStatus, body]
     */
    public function receive(array $data): array
    {
        $validator = Validator::make($data, [
            'appeal_id' => 'required|integer|min:1',
            'content' => 'nullable|string',
            'sphere' => 'nullable|array',
            'applicant' => 'nullable|array',
            'location' => 'nullable|array',
            'files' => 'nullable|array',
            'callback_url' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return [422, ['success' => false, 'message' => "Ma'lumotlar noto'g'ri", 'errors' => $validator->errors()]];
        }

        // takroriy yuborish: o'sha id qaytariladi
        if ($exists = OgohAppeal::query()->where('appeal_id', $data['appeal_id'])->first()) {
            return [200, ['success' => true, 'duplicate' => true, 'id' => (string)$exists->id]];
        }

        $location = $data['location'] ?? [];
        $provinceSoato = Arr::get($location, 'province.id');
        $districtSoato = Arr::get($location, 'region.id');
        $region = $provinceSoato ? Region::query()->where('soato', $provinceSoato)->first() : null;
        $district = $districtSoato ? District::query()->where('soato', $districtSoato)->first() : null;

        try {
            $appeal = DB::transaction(function () use ($data, $location, $region, $district) {
                $appeal = OgohAppeal::query()->create([
                    'appeal_id' => $data['appeal_id'],
                    'integration' => $data['integration'] ?? null,
                    'sphere_id' => Arr::get($data, 'sphere.id'),
                    'sphere_name' => Arr::get($data, 'sphere.name'),
                    'sphere_parent_id' => Arr::get($data, 'sphere.parent_id'),
                    'sphere_parent_name' => Arr::get($data, 'sphere.parent_name'),
                    'content' => $data['content'] ?? null,
                    'full_name' => Arr::get($data, 'applicant.full_name'),
                    'phone_number' => Arr::get($data, 'applicant.phone_number'),
                    'gender' => Arr::get($data, 'applicant.gender'),
                    'birth_date' => $this->date(Arr::get($data, 'applicant.birth_date')),
                    'region_id' => $region?->id ?? $district?->region_id,
                    'district_id' => $district?->id,
                    'province_name' => Arr::get($location, 'province.name'),
                    'region_name' => Arr::get($location, 'region.name'),
                    'mahalla_name' => Arr::get($location, 'mahalla.name'),
                    'address' => $location['address'] ?? null,
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'files' => $data['files'] ?? [],
                    'callback_url' => $data['callback_url'] ?? null,
                    'appeal_created_at' => $this->date($data['created_at'] ?? null, true),
                    'status' => OgohAppealStatusEnum::ACCEPTED->value,
                    'payload' => $data,
                ]);

                // fuqaroga "qabul qilindi" xabari; cron yuboradi (ogohfuqaro hali bizning javobimizni kutib turibdi)
                $this->addEvent($appeal, OgohAppealStatusEnum::ACCEPTED->value, null);

                return $appeal;
            });
        } catch (QueryException $e) {
            // parallel kelgan takroriy so'rov
            if ($exists = OgohAppeal::query()->where('appeal_id', $data['appeal_id'])->first()) {
                return [200, ['success' => true, 'duplicate' => true, 'id' => (string)$exists->id]];
            }
            throw $e;
        }

        return [201, ['success' => true, 'id' => (string)$appeal->id]];
    }

    /**
     * Yakuniy javob: 2 hal qilindi, 3 rad etildi, 5 tushuntirish berildi.
     *
     * @param UploadedFile[] $files
     */
    public function answer(OgohAppeal $appeal, int $status, string $comment, array $files, $user): OgohAppeal
    {
        if ($appeal->isFinal()) {
            throw new \Exception('Murojaatga allaqachon javob berilgan');
        }

        $resultFiles = $this->storeFiles($appeal, $files);

        $event = DB::transaction(function () use ($appeal, $status, $comment, $resultFiles, $user) {
            $appeal->update([
                'status' => $status,
                'comment' => $comment,
                'result_files' => $resultFiles,
                'answered_by' => $user?->id,
                'answered_at' => now(),
            ]);
            return $this->addEvent($appeal, $status, $comment, $resultFiles, $user);
        });

        $this->send($event);

        return $appeal->refresh();
    }

    /**
     * Fuqaroga oraliq xabar (status o'zgarmaydi).
     */
    public function message(OgohAppeal $appeal, string $comment, $user): OgohAppealEvent
    {
        $event = $this->addEvent($appeal, null, $comment, [], $user);
        $this->send($event);
        return $event->refresh();
    }

    public function addEvent(OgohAppeal $appeal, ?int $status, ?string $comment, array $resultFiles = [], $user = null): OgohAppealEvent
    {
        return $appeal->events()->create([
            'event_id' => (string)Str::uuid(),
            'status' => $status,
            'comment' => $comment,
            'result_files' => $resultFiles,
            'user_id' => $user?->id,
            'occurred_at' => now(),
            'send_status' => OgohAppealEvent::SEND_PENDING,
        ]);
    }

    /**
     * Callbackni ogohfuqaroga yuboradi. Xatoda keyingi urinish vaqtini belgilaydi.
     */
    public function send(OgohAppealEvent $event): bool
    {
        $config = config('services.ogohfuqaro');
        $appeal = $event->appeal;
        $url = $config['callback_url'] ?: $appeal->callback_url;

        if (!$url) {
            return $this->fail($event, null, null, 'Callback URL sozlanmagan', true);
        }

        $payload = array_filter([
            'event_id' => $event->event_id,
            'appeal_id' => (int)$appeal->appeal_id,
            'external_id' => (string)$appeal->id,
            'status' => $event->status,
            'comment' => $event->comment,
            'result_files' => $event->result_files ?: null,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ], fn($v) => $v !== null);

        $event->increment('attempts');

        try {
            $response = Http::timeout((int)($config['timeout'] ?? 20))
                ->connectTimeout(5)
                ->acceptJson()
                ->withBasicAuth((string)$config['username'], (string)$config['password'])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Ogoh fuqaro callback xatosi: ' . $e->getMessage(), ['event_id' => $event->id]);
            return $this->fail($event, null, null, $e->getMessage(), true);
        }

        $status = $response->status();
        if ($response->successful()) {
            $event->update([
                'send_status' => OgohAppealEvent::SEND_SENT,
                'http_status' => $status,
                'response' => $response->json(),
                'error' => null,
                'next_attempt_at' => null,
                'sent_at' => now(),
            ]);
            return true;
        }

        // 408/425/429/5xx - vaqtinchalik; boshqa 4xx - so'rovda xato, qayta urinish foydasiz
        $retryable = $status >= 500 || in_array($status, [408, 425, 429], true);
        $error = Arr::get($response->json() ?? [], 'error.code', 'HTTP ' . $status);
        Log::error('Ogoh fuqaro callback rad etildi: ' . $error, ['event_id' => $event->id, 'body' => $response->body()]);

        return $this->fail($event, $status, $response->json() ?? ['body' => Str::limit($response->body(), 2000)], $error, $retryable);
    }

    /**
     * Navbatdagi yuborilmagan callbacklarni yuboradi.
     */
    public function sendPending(int $limit = 50): array
    {
        $stats = ['sent' => 0, 'failed' => 0];
        $events = OgohAppealEvent::query()
            ->with('appeal')
            ->where('send_status', OgohAppealEvent::SEND_PENDING)
            ->where(fn($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            $this->send($event) ? $stats['sent']++ : $stats['failed']++;
        }
        return $stats;
    }

    private function fail(OgohAppealEvent $event, ?int $httpStatus, $response, string $error, bool $retryable): bool
    {
        $attempts = (int)$event->attempts;
        $giveUp = !$retryable || $attempts >= count(self::RETRY_MINUTES);

        $event->update([
            'send_status' => $giveUp ? OgohAppealEvent::SEND_FAILED : OgohAppealEvent::SEND_PENDING,
            'http_status' => $httpStatus,
            'response' => $response,
            'error' => Str::limit($error, 1000),
            'next_attempt_at' => $giveUp ? null : now()->addMinutes(self::RETRY_MINUTES[max(0, $attempts - 1)]),
        ]);
        return false;
    }

    /**
     * @param UploadedFile[] $files
     */
    private function storeFiles(OgohAppeal $appeal, array $files): array
    {
        $result = [];
        foreach ($files as $file) {
            $path = $file->store('ogoh-appeals/' . $appeal->id, 'public');
            $result[] = [
                // ogohfuqaro faqat to'liq http(s) havolani qabul qiladi
                'url' => url(Storage::disk('public')->url($path)),
                'name' => $file->getClientOriginalName(),
            ];
        }
        return $result;
    }

    private function date($value, bool $withTime = false): ?string
    {
        if (!$value || !($ts = strtotime($value))) {
            return null;
        }
        return date($withTime ? 'Y-m-d H:i:s' : 'Y-m-d', $ts);
    }
}
