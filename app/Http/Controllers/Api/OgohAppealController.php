<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleEnum;
use App\Http\Requests\OgohAppealAnswerRequest;
use App\Models\OgohAppeal;
use App\Services\OgohFuqaroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OgohAppealController extends BaseController
{
    public function __construct(protected OgohFuqaroService $service)
    {
        parent::__construct();
    }
    public function receive(Request $request): JsonResponse
    {
        try {
            [$status, $body] = $this->service->receive($request->all());
            return response()->json($body, $status);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['success' => false, 'message' => 'Server xatosi'], 500);
        }
    }

    public function index($id = null): JsonResponse
    {
        try {
            if ($id) {
                $appeal = $this->query()->with(['events', 'region', 'district', 'answeredBy'])->findOrFail($id);
                return $this->sendSuccess($appeal, 'Success');
            }

            $filters = request()->only(['status', 'region_id', 'district_id', 'appeal_id', 'start_date', 'end_date']);
            $data = $this->query()
                ->with(['region', 'district'])
                ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
                ->when($filters['region_id'] ?? null, fn($q, $v) => $q->where('region_id', $v))
                ->when($filters['district_id'] ?? null, fn($q, $v) => $q->where('district_id', $v))
                ->when($filters['appeal_id'] ?? null, fn($q, $v) => $q->where('appeal_id', $v))
                ->when($filters['start_date'] ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
                ->when($filters['end_date'] ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
                ->orderByDesc('id')
                ->paginate(request('per_page', 15));

            return $this->sendSuccess($data->items(), 'Success', pagination($data));
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    /**
     * Yakuniy javob (2, 3, 5) - fuqaroga ogohfuqaro orqali yuboriladi.
     */
    public function answer(OgohAppealAnswerRequest $request): JsonResponse
    {
        try {
            $appeal = $this->query()->findOrFail($request->id);
            $data = $this->service->answer(
                $appeal,
                (int)$request->status,
                $request->comment,
                $request->file('files', []),
                $this->user
            );
            return $this->sendSuccess($data->load('events'), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    /**
     * Oraliq xabar - status o'zgarmaydi.
     */
    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:ogoh_appeals,id',
            'comment' => 'required|string|min:5|max:2000',
        ]);

        try {
            $appeal = $this->query()->findOrFail($request->id);
            $event = $this->service->message($appeal, $request->comment, $this->user);
            return $this->sendSuccess($event, 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }

    private function query()
    {
        switch ($this->roleId) {
            case UserRoleEnum::RESPUBLIKA_KUZATUVCHI->value:
            case UserRoleEnum::RESPUBLIKA_KOMPLAYNS->value:
                return OgohAppeal::query();
            case UserRoleEnum::OPERATOR->value:
            case UserRoleEnum::INSPEKSIYA->value:
            case UserRoleEnum::HUDUDIY_KUZATUVCHI->value:
            case UserRoleEnum::KOMPLAYNS->value:
                return OgohAppeal::query()->where('region_id', $this->user->region_id);
            default:
                return OgohAppeal::query()->whereRaw('1 = 0');
        }
    }
}
