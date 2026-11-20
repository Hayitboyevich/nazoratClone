<?php

namespace App\Http\Controllers\Api;

use App\Enums\LabCertificateDeviationMeaningEnum;
use App\Enums\LabCertificateDocTypeEnum;
use App\Enums\LabCertificateStatusEnum;
use App\Enums\LabCertificateValueFormEnum;
use App\Enums\LabInstrumentStateEnum;
use App\Enums\LabInstrumentStatusEnum;
use App\Enums\LabInstrumentTypeEnum;
use App\Enums\LabInstrumentUnitEnum;
use App\Enums\UserRoleEnum;
use App\Http\Requests\LabInstrumentCertificateRequest;
use App\Http\Requests\LabInstrumentRequest;
use App\Models\LabInstrument;
use App\Enums\LogType;
use App\Models\LabInstrumentCertificate;
use App\Models\LabMethod;
use App\Models\LabStandart;
use App\Models\User;
use App\Services\HistoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Metrolog oynasi: o'lchash vositalari reyestri va ularning sertifikatlari.
 * O'zgartirish faqat LAB_METROLOGY roliga, ko'rish laboratoriya rollariga ham ochiq.
 */
class LabMetrologyController extends BaseController
{
    public function dictionaries(): JsonResponse
    {
        $map = fn(array $cases) => array_map(fn($c) => ['id' => $c->value, 'name' => $c->label()], $cases);

        return $this->sendSuccess([
            'instrument_types' => $map(LabInstrumentTypeEnum::cases()),
            'instrument_statuses' => $map(LabInstrumentStatusEnum::cases()),
            'instrument_states' => $map(LabInstrumentStateEnum::cases()),
            'certificate_statuses' => $map(LabCertificateStatusEnum::cases()),
            'certificate_doc_types' => $map(LabCertificateDocTypeEnum::cases()),
            'certificate_value_forms' => $map(LabCertificateValueFormEnum::cases()),
            'certificate_deviation_meanings' => $map(LabCertificateDeviationMeaningEnum::cases()),
            'units' => $map(LabInstrumentUnitEnum::cases()),
            // asbob bog'lanadigan sinov turlari (lab_method_ids)
            'methods' => LabMethod::query()->where('status', true)->orderBy('sort')->orderBy('id')->get(['id', 'code', 'name']),
            'expiry_warn_days' => LabInstrument::EXPIRY_WARN_DAYS,
        ], 'Success');
    }

    /**
     * Ro'yxat kartochkalari: har bir LabInstrumentStateEnum bo'yicha asboblar soni (?state= filtri bilan bir xil hisob).
     */
    public function statistics(): JsonResponse
    {
        if (!$this->canView()) return $this->forbidden();

        try {
            $instruments = $this->instrumentQuery()
                ->with('certificates:id,lab_instrument_id,status,issued_at,valid_to')
                ->get();

            $states = $instruments->map(fn($i) => $i->expiryState()['id']);
            $count = fn(LabInstrumentStateEnum $state) => match ($state) {
                // «Muddati o'tgan» kartochkasi sertifikati yo'qlarni ham o'z ichiga oladi
                LabInstrumentStateEnum::EXPIRED => $states->filter(fn($s) => in_array($s, [LabInstrumentStateEnum::EXPIRED->value, LabInstrumentStateEnum::NO_CERTIFICATE->value]))->count(),
                LabInstrumentStateEnum::CALIBRATION => $instruments->where('status', LabInstrumentStatusEnum::CALIBRATION->value)->count(),
                LabInstrumentStateEnum::REPAIR => $instruments->where('status', LabInstrumentStatusEnum::REPAIR->value)->count(),
                default => $states->filter(fn($s) => $s === $state->value)->count(),
            };

            return $this->sendSuccess([
                'all' => $instruments->count(),
                'states' => array_map(fn($s) => ['id' => $s->value, 'name' => $s->label(), 'count' => $count($s)], LabInstrumentStateEnum::cases()),
            ], 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function instruments($id = null): JsonResponse
    {
        if (!$this->canView()) return $this->forbidden();
        request()->validate([
            'state' => 'nullable|integer|in:' . implode(',', LabInstrumentStateEnum::values()),
            'type' => 'nullable|integer|in:' . implode(',', LabInstrumentTypeEnum::values()),
            'status' => 'nullable|integer|in:' . implode(',', LabInstrumentStatusEnum::values()),
            'lab_method_id' => 'nullable|integer',
            'usable' => 'nullable|boolean',
        ]);

        try {
            if ($id) {
                $instrument = $this->instrumentQuery()
                    ->with(['certificates.confirmer', 'region:id,name_uz', 'creator', 'methods'])
                    ->findOrFail($id);
                return $this->sendSuccess($this->withState($instrument), 'Success');
            }

            $filters = request()->only(['search', 'type', 'status', 'state', 'lab_method_id']);
            $data = $this->instrumentQuery()
                ->with(['certificates', 'region:id,name_uz', 'methods'])
                // shu sinov turiga bog'langan asboblar
                ->when($filters['lab_method_id'] ?? null, fn($q, $v) => $q->whereHas('methods', fn($q) => $q->where('lab_methods.id', $v)))
                // usable=1: sinovda ishlatsa bo'ladiganlari - ishlayotgan va bugun amaldagi sertifikati bor
                ->when(request()->boolean('usable'), fn($q) => $q->usable())
                ->when($filters['search'] ?? null, fn($q, $v) => $q->where(fn($q) => $q
                    ->where('code', 'ilike', "%$v%")
                    ->orWhere('name', 'ilike', "%$v%")
                    ->orWhere('serial_number', 'ilike', "%$v%")))
                ->when($filters['type'] ?? null, fn($q, $v) => $q->where('type', $v))
                ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
                ->whereExpiryState($filters['state'] ?? null)
                ->orderBy('code')
                ->paginate(request('per_page', 15));

            $items = $data->getCollection()->map(function (LabInstrument $instrument) {
                $this->withState($instrument);
                $instrument->unsetRelation('certificates');
                return $instrument;
            });

            return $this->sendSuccess($items, 'Success', pagination($data));
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function instrumentCreate(LabInstrumentRequest $request): JsonResponse
    {
        if (!$this->canManage()) return $this->forbidden();
        if (!$this->user->region_id) return $this->sendError('Foydalanuvchiga viloyat biriktirilmagan', [], 422);

        try {
            $instrument = DB::transaction(function () use ($request) {
                $instrument = LabInstrument::query()->create(array_merge(Arr::except($request->validated(), ['lab_method_ids']), [
                    'status' => $request->status ?? LabInstrumentStatusEnum::WORKING->value,
                    'region_id' => $this->user->region_id,
                    'created_by' => $this->user->id,
                ]));
                $methodIds = $this->methodIds($request);
                $instrument->methods()->sync($methodIds);
                $this->logHistory(LogType::LAB_INSTRUMENT_CREATE, $instrument, ['snapshot' => HistoryService::modelSnapshot($instrument) + ['lab_method_ids' => $methodIds]]);
                return $instrument;
            });
            return $this->sendSuccess($instrument->load('methods'), 'Asbob qo\'shildi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    public function instrumentUpdate($id, LabInstrumentRequest $request): JsonResponse
    {
        if (!$this->canManage()) return $this->forbidden();

        try {
            $instrument = $this->instrumentQuery()->findOrFail($id);
            $data = Arr::except($request->validated(), ['lab_method_ids']);
            if (!isset($data['status'])) unset($data['status']);

            // sinov turlari bog'lanishi ham tarixga yoziladi
            $old = $instrument->methods()->pluck('lab_methods.id')->map(fn($v) => (int)$v)->sort()->values()->all();
            $new = $this->methodIds($request);
            $instrument->fill(array_merge($data, ['updated_by' => $this->user->id]));
            $changes = HistoryService::modelChanges($instrument);
            if ($old !== $new) $changes['lab_method_ids'] = ['old' => $old, 'new' => $new];

            DB::transaction(function () use ($instrument, $new, $changes) {
                $instrument->save();
                $instrument->methods()->sync($new);
                if ($changes) $this->logHistory(LogType::LAB_INSTRUMENT_UPDATE, $instrument, ['changes' => $changes]);
            });
            return $this->sendSuccess($instrument->fresh('methods'), 'Saqlandi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    public function instrumentStatus($id, Request $request): JsonResponse
    {
        if (!$this->canManage()) return $this->forbidden();

        $request->validate([
            'status' => 'required|integer|in:' . implode(',', LabInstrumentStatusEnum::values()),
            'comment' => 'nullable|string|max:2000',
        ]);

        try {
            $instrument = $this->instrumentQuery()->findOrFail($id);
            $this->saveWithHistory($instrument, [
                'status' => (int)$request->status,
                'comment' => $request->comment ?? $instrument->comment,
                'updated_by' => $this->user->id,
            ], LogType::LAB_INSTRUMENT_STATUS, $request->comment);
            return $this->sendSuccess($instrument->fresh(), 'Holat o\'zgartirildi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    public function instrumentDelete($id): JsonResponse
    {
        if (!$this->canManage()) return $this->forbidden();

        try {
            $instrument = $this->instrumentQuery()->findOrFail($id);
            $hasConfirmed = $instrument->certificates()->where('status', LabCertificateStatusEnum::CONFIRMED->value)->exists();
            if ($hasConfirmed) {
                return $this->sendError("Tasdiqlangan sertifikati bor asbobni o'chirib bo'lmaydi, holatini «Hisobdan chiqarilgan» qiling", [], 422);
            }
            DB::transaction(function () use ($instrument) {
                $this->logHistory(LogType::LAB_INSTRUMENT_DELETE, $instrument, ['snapshot' => HistoryService::modelSnapshot($instrument)]);
                $instrument->delete();
            });
            return $this->sendSuccess(null, 'O\'chirildi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    public function certificates(): JsonResponse
    {
        if (!$this->canView()) return $this->forbidden();
        request()->validate([
            'lab_instrument_id' => 'nullable|integer',
            'status' => 'nullable|integer|in:' . implode(',', LabCertificateStatusEnum::values()),
            'doc_type' => 'nullable|integer|in:' . implode(',', LabCertificateDocTypeEnum::values()),
        ]);

        try {
            $status = (int)request('status');
            $data = $this->certificateQuery()
                ->with(['instrument:id,code,name,type,unit,range_from,range_to,region_id', 'confirmer'])
                ->when(request('lab_instrument_id'), fn($q, $v) => $q->where('lab_instrument_id', $v))
                ->when($status, fn($q, $v) => $q->where('status', $v))
                ->when(request('doc_type'), fn($q, $v) => $q->where('doc_type', $v))
                ->orderByDesc('issued_at')
                ->orderByDesc('id')
                ->paginate(request('per_page', 15));

            return $this->sendSuccess($data->items(), 'Success', pagination($data));
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    /**
     * Sertifikat darhol tasdiqlangan (2) holatda yaratiladi. Oldin tekshiruv: xato bo'lsa 422,
     * ogohlantirish bo'lsa 422 - ularga qaramay saqlash uchun ignore_warnings=1 bilan qayta yuboriladi.
     */
    public function certificateCreate(LabInstrumentCertificateRequest $request): JsonResponse
    {
        if (!$this->canManage()) return $this->forbidden();

        try {
            $instrument = $this->instrumentQuery()->findOrFail($request->lab_instrument_id);
            $certificate = new LabInstrumentCertificate(array_merge($this->certificateData($request), [
                'lab_instrument_id' => $instrument->id,
                'status' => LabCertificateStatusEnum::CONFIRMED->value,
                'confirmed_by' => $this->user->id,
                'confirmed_at' => now(),
                'created_by' => $this->user->id,
            ]));
            if ($blocked = $this->checkCertificate($certificate, $instrument, $request)) return $blocked;
            $certificate->fill($this->storeFile($request));

            DB::transaction(function () use ($certificate) {
                $certificate->save();
                $this->logHistory(LogType::LAB_CERTIFICATE_CREATE, $certificate, ['snapshot' => HistoryService::modelSnapshot($certificate)]);
            });
            return $this->sendSuccess($certificate->fresh('confirmer'), 'Sertifikat qo\'shildi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    /**
     * Tahrirlash (status o'zgarmaydi). Tekshiruv create bilan bir xil.
     * Yangi fayl kelsa eskisi diskdan o'chirilmaydi - uning yo'li tarixda (changes.file.old) qoladi.
     */
    public function certificateUpdate($id, LabInstrumentCertificateRequest $request): JsonResponse
    {
        if (!$this->canManage()) return $this->forbidden();

        try {
            $certificate = $this->certificateQuery()->with('instrument')->findOrFail($id);
            $certificate->fill($this->certificateData($request));
            if ($blocked = $this->checkCertificate($certificate, $certificate->instrument, $request)) return $blocked;

            $this->saveWithHistory($certificate, $this->storeFile($request), LogType::LAB_CERTIFICATE_UPDATE);

            return $this->sendSuccess($certificate->fresh('confirmer'), 'Saqlandi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    /**
     * Sertifikat holatini o'zgartirish:
     *  - REJECTED (3): bekor qilish, izoh majburiy - hisoblarda ishlatilmaydi
     *  - CONFIRMED (2): bekor qilinganini qayta tiklash; tekshiruv create bilan bir xil
     */
    public function certificateStatus($id, Request $request): JsonResponse
    {
        if (!$this->canManage()) return $this->forbidden();

        $request->validate([
            'status' => 'required|integer|in:' . implode(',', LabCertificateStatusEnum::values()),
            'comment' => 'nullable|required_if:status,' . LabCertificateStatusEnum::REJECTED->value . '|string|max:2000',
            'ignore_warnings' => 'nullable|boolean',
        ]);

        try {
            $certificate = $this->certificateQuery()->with('instrument')->findOrFail($id);
            $status = LabCertificateStatusEnum::from((int)$request->status);

            if ($certificate->status === $status->value) {
                return $this->sendError('Sertifikat allaqachon shu holatda', [], 422);
            }

            switch ($status) {
                case LabCertificateStatusEnum::CONFIRMED:
                    if ($blocked = $this->checkCertificate($certificate, $certificate->instrument, $request)) return $blocked;
                    $this->saveWithHistory($certificate, [
                        'status' => $status->value,
                        'confirmed_by' => $this->user->id,
                        'confirmed_at' => now(),
                        'comment' => $request->comment ?? $certificate->comment,
                    ], LogType::LAB_CERTIFICATE_STATUS, $request->comment);
                    break;

                case LabCertificateStatusEnum::REJECTED:
                    $this->saveWithHistory($certificate, [
                        'status' => $status->value,
                        'confirmed_by' => null,
                        'confirmed_at' => null,
                        'comment' => $request->comment,
                    ], LogType::LAB_CERTIFICATE_STATUS, $request->comment);
                    break;
            }

            return $this->sendSuccess($certificate->fresh('confirmer'), 'Holat o\'zgartirildi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    public function certificateDelete($id): JsonResponse
    {
        if (!$this->canManage()) return $this->forbidden();

        try {
            $certificate = $this->certificateQuery()->findOrFail($id);
            if ($certificate->isConfirmed()) {
                return $this->sendError("Tasdiqlangan sertifikatni o'chirib bo'lmaydi, avval bekor qiling (status = 3)", [], 422);
            }
            // fayl diskda qoladi - yozuvning to'liq holati (fayl yo'li bilan) tarixga yoziladi
            DB::transaction(function () use ($certificate) {
                $this->logHistory(LogType::LAB_CERTIFICATE_DELETE, $certificate, ['snapshot' => HistoryService::modelSnapshot($certificate)]);
                $certificate->delete();
            });
            return $this->sendSuccess(null, 'O\'chirildi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    private function methodIds(Request $request): array
    {
        return collect($request->input('lab_method_ids'))->map(fn($v) => (int)$v)->unique()->sort()->values()->all();
    }

    private function certificateData(LabInstrumentCertificateRequest $request): array
    {
        $data = collect($request->validated())->except(['lab_instrument_id', 'file', 'ignore_warnings'])->toArray();
        // multipart'da raqamlar matn bo'lib keladi
        $data['doc_type'] = (int)$data['doc_type'];
        $data['value_form'] = (int)$data['value_form'];
        // bo'sh yuborilsa standart qiymat (ustunlar NOT NULL)
        if (array_key_exists('deviation_meaning', $data)) {
            $data['deviation_meaning'] = (int)($data['deviation_meaning'] ?? LabCertificateDeviationMeaningEnum::CORRECTION->value);
        }
        if (array_key_exists('use_correction', $data)) {
            $data['use_correction'] = $data['use_correction'] ?? true;
        }

        // kalibrlash nuqtalari x bo'yicha tartiblangan holda saqlanadi
        if (isset($data['points'])) {
            $data['points'] = collect($data['points'])
                ->map(fn($p) => [
                    'x' => isset($p['x']) ? (float)$p['x'] : null,
                    'u' => isset($p['u']) ? (float)$p['u'] : null,
                    'd' => isset($p['d']) ? (float)$p['d'] : null,
                ])
                ->sortBy('x')->values()->toArray();
        }

        // tur almashtirilganda boshqa turning eski qiymatlari tozalanadi
        if ($data['doc_type'] === LabCertificateDocTypeEnum::VERIFICATION->value) {
            $data['points'] = [];
            $data['use_correction'] = false;
            $data['deviation_meaning'] = LabCertificateDeviationMeaningEnum::CORRECTION->value;
        } else {
            $data['delta'] = null;
        }

        return $data;
    }

    /**
     * Fayl tekshiruvdan o'tgandan keyingina diskka yoziladi (xato bo'lsa ortiqcha fayl qolmasin).
     */
    private function storeFile(Request $request): array
    {
        if (!$request->hasFile('file')) return [];

        $file = $request->file('file');
        return [
            'file' => $file->store('lab-certificates/' . now()->format('Y/m'), 'public'),
            'file_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Saqlashdan oldingi tekshiruv: xato -> 422 "Saqlab bo'lmaydi"; ogohlantirish -> 422 (ignore_warnings=1 bo'lsa o'tkaziladi).
     */
    private function checkCertificate(LabInstrumentCertificate $certificate, LabInstrument $instrument, Request $request): ?JsonResponse
    {
        [$errors, $warnings] = $certificate->validateForConfirm($instrument, $request->hasFile('file'));
        if ($errors) {
            return $this->sendError("Saqlab bo'lmaydi", ['errors' => $errors, 'warnings' => $warnings], 422);
        }
        if ($warnings && !$request->boolean('ignore_warnings')) {
            return $this->sendError('Diqqat: ogohlantirishlar bor', ['errors' => [], 'warnings' => $warnings], 422);
        }
        return null;
    }

    /**
     * Laboratoriya standartlari ma'lumotnomasi: id bilan - bitta yozuv, id'siz - ro'yxat (search, status, per_page).
     */
    public function standards($id = null): JsonResponse
    {
        if (!$this->canView()) return $this->forbidden();

        if ($id) {
            $standart = LabStandart::query()->find($id);
            if (!$standart) return $this->sendError('Standart topilmadi', [], 404);

            return $this->sendSuccess($standart, 'Success');
        }

        request()->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        try {
            $data = LabStandart::query()
                ->when(request('search'), fn($q, $v) => $q->where(fn($q) => $q
                    ->where('name', 'ilike', "%$v%")
                    ->orWhere('mark', 'ilike', "%$v%")
                    ->orWhere('profile', 'ilike', "%$v%")))
                ->when(request()->filled('status'), fn($q) => $q->where('status', request()->boolean('status')))
                ->orderBy('id')
                ->paginate(request('per_page', 15));

            return $this->sendSuccess($data->items(), 'Success', pagination($data));
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    /**
     * Asbob tarixi: asbobning o'zi va uning sertifikatlari (o'chirilganlari ham) bo'yicha amallar, yangisidan boshlab.
     */
    public function instrumentHistory($id): JsonResponse
    {
        if (!$this->canView()) return $this->forbidden();

        try {
            $instrument = $this->instrumentQuery()->withTrashed()->findOrFail($id);
            $history = $this->history();
            $rows = $history->getListByTypes(LogType::labInstrumentTypes(), $instrument->id)
                ->merge($history->getListByTypes(LogType::labCertificateTypes(), null, ['additionalInfo->lab_instrument_id' => $instrument->id]));

            return $this->sendSuccess($this->formatHistory($rows), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    /**
     * Bitta sertifikat tarixi (o'chirilgan sertifikatniki ham ko'rinadi).
     */
    public function certificateHistory($id): JsonResponse
    {
        if (!$this->canView()) return $this->forbidden();

        try {
            $rows = $this->history()->getListByTypes(LogType::labCertificateTypes(), (int)$id);
            $instrumentId = json_decode($rows->first()?->content ?? '{}', true)['additionalInfo']['lab_instrument_id'] ?? null;
            if (!$instrumentId || !$this->instrumentQuery()->withTrashed()->whereKey($instrumentId)->exists()) {
                return $this->sendError('Topilmadi', [], 404);
            }

            return $this->sendSuccess($this->formatHistory($rows), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    private function history(): HistoryService
    {
        return new HistoryService('lab_histories');
    }

    /**
     * fill -> o'zgarishlarni olish -> saqlash -> tarixga yozish (bitta tranzaksiyada). O'zgarish bo'lmasa tarix yozilmaydi.
     */
    private function saveWithHistory(Model $model, array $data, int $type, ?string $comment = null): void
    {
        $model->fill($data);
        $changes = HistoryService::modelChanges($model);

        DB::transaction(function () use ($model, $changes, $type, $comment) {
            $model->save();
            if ($changes) $this->logHistory($type, $model, ['changes' => $changes], $comment);
        });
    }

    private function logHistory(int $type, Model $model, array $info = [], ?string $comment = null): void
    {
        $this->history()->createHistory(
            guId: $model->id,
            status: (int)$model->status,
            type: $type,
            date: null,
            comment: $comment ?? '',
            additionalInfo: array_merge([
                'lab_instrument_id' => $model instanceof LabInstrumentCertificate ? $model->lab_instrument_id : $model->id,
            ], $info),
        );
    }

    private function formatHistory(Collection $rows): array
    {
        $rows = $rows->sortByDesc('id')->values()->map(function ($row) {
            $content = json_decode($row->content, true) ?? [];
            $info = $content['additionalInfo'] ?? [];
            $changes = $info['changes'] ?? null;
            $snapshot = $info['snapshot'] ?? null;

            // fayllar uchun ochiladigan havola
            if (isset($changes['file'])) {
                $changes['file']['old_url'] = $this->fileUrl($changes['file']['old']);
                $changes['file']['new_url'] = $this->fileUrl($changes['file']['new']);
            }
            if ($snapshot && array_key_exists('file', $snapshot)) {
                $snapshot['file_url'] = $this->fileUrl($snapshot['file']);
            }

            return [
                'id' => $row->id,
                'type' => (int)$row->type,
                'type_name' => LogType::getLabLabel($row->type),
                'entity' => in_array((int)$row->type, LogType::labCertificateTypes()) ? 'certificate' : 'instrument',
                'entity_id' => (int)$row->gu_id,
                'lab_instrument_id' => $info['lab_instrument_id'] ?? null,
                'user_id' => ($content['user'] ?? null) ?: null,
                'role_id' => $content['role'] ?? null,
                'status' => $content['status'] ?? null,
                'comment' => ($content['comment'] ?? '') ?: null,
                'changes' => $changes,
                'snapshot' => $snapshot,
                'created_at' => $row->created_at,
            ];
        });

        $users = User::query()->whereIn('id', $rows->pluck('user_id')->filter()->unique())
            ->get(['id', 'name', 'surname', 'middle_name'])->keyBy('id');

        return $rows->map(fn($row) => array_merge($row, [
            'user' => ($u = $users->get($row['user_id'])) ? Arr::only($u->toArray(), ['id', 'name', 'surname', 'middle_name']) : null,
        ]))->toArray();
    }

    private function fileUrl(?string $path): ?string
    {
        return $path ? url('storage/' . $path) : null;
    }

    private function withState(LabInstrument $instrument): LabInstrument
    {
        // «Joriy hujjat» - expiry_state ham shu sertifikatga qarab hisoblanadi
        $current = $instrument->currentCertificate();
        $instrument->setAttribute('expiry_state', $instrument->expiryState());
        $instrument->setAttribute('current_certificate', $current ? Arr::only($current->toArray(), [
            'id', 'doc_type', 'doc_type_name', 'number', 'issued_at', 'valid_to', 'days_left', 'status', 'status_name', 'points', 'value_form', 'delta', 'file_url', 'deviation_meaning', 'use_correction'
        ]) : null);
        return $instrument;
    }

    /**
     * Viloyat bo'yicha cheklov: respublika darajasidagi rollar barcha viloyatlarni ko'radi
     * (ixtiyoriy region_id filtri bilan), qolganlar - faqat o'z viloyatini.
     */
    private function instrumentQuery(): Builder
    {
        $query = LabInstrument::query();

        if ($this->seesAllRegions()) {
            return $query->when(request('region_id'), fn($q, $v) => $q->where('region_id', $v));
        }

        return $this->user->region_id
            ? $query->where('region_id', $this->user->region_id)
            : $query->whereRaw('1 = 0');
    }

    private function certificateQuery(): Builder
    {
        return LabInstrumentCertificate::query()->whereIn('lab_instrument_id', $this->instrumentQuery()->select('id'));
    }

    private function seesAllRegions(): bool
    {
        return in_array((int)$this->roleId, [UserRoleEnum::RES_LAB->value, UserRoleEnum::ADMIN->value]);
    }

    private function canManage(): bool
    {
        return (int)$this->roleId === UserRoleEnum::LAB_METROLOGY->value;
    }

    private function canView(): bool
    {
        return in_array((int)$this->roleId, [
            UserRoleEnum::LAB_METROLOGY->value,
            UserRoleEnum::LABORANT->value,
            UserRoleEnum::REG_LAB->value,
            UserRoleEnum::RES_LAB->value,
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::INSPECTOR->value,
            UserRoleEnum::INSPEKSIYA->value,
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return $this->sendError("Ruxsat yo'q", [], 403);
    }
}
