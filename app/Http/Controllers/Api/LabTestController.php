<?php

namespace App\Http\Controllers\Api;

use App\Enums\LabTestStatusEnum;
use App\Http\Requests\LabTestFilterRequest;
use App\Http\Requests\LabTestProtocolRequest;
use App\Http\Requests\LabTestRequest;
use App\Http\Requests\LabTestReviewRequest;
use App\Services\LabTestService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Laboratoriya sinovlari. Qoidalar - LabTestService, so'rovlar - LabTestRepository, validatsiya - LabTest*Request.
 */
class LabTestController extends BaseController
{
    public function __construct(private LabTestService $service)
    {
        parent::__construct();
    }

    public function dictionaries(): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess($this->service->dictionaries(), 'Success'));
    }

    public function tests(LabTestFilterRequest $request, $id = null): JsonResponse
    {
        return $this->respond(function () use ($request, $id) {
            if ($id) {
                return $this->sendSuccess($this->service->show($id, $this->user, $this->roleId), 'Success');
            }

            $data = $this->service->all($request->validated(), $this->user, $this->roleId);
            return $this->sendSuccess($data->items(), 'Success', pagination($data));
        });
    }

    /** Bayonnoma fayli: tasdiqlanmaguncha namuna, tasdiqlangach - tasdiqlangan nusxa */
    public function pdf($id): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess($this->service->pdf($id, $this->user, $this->roleId), 'PDF'));
    }

    public function create(LabTestRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->create($request->validated(), $this->user, $this->roleId),
            'Sinov yaratildi'
        ));
    }

    public function update($id, LabTestRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->update($id, $request->validated(), $this->user, $this->roleId),
            'Saqlandi'
        ));
    }

    public function formProtocol($id, LabTestProtocolRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->formProtocol($id, $request->validated(), $this->user, $this->roleId),
            'Bayonnoma shakllantirildi'
        ));
    }

    public function review($id, LabTestReviewRequest $request): JsonResponse
    {
        return $this->respond(function () use ($id, $request) {
            $decision = LabTestStatusEnum::from((int)$request->status);
            $test = $this->service->review($id, $decision, $request->comment, $this->user, $this->roleId);
            return $this->sendSuccess($test, $decision->label());
        });
    }

    public function delete($id): JsonResponse
    {
        return $this->respond(function () use ($id) {
            $this->service->delete($id, $this->user, $this->roleId);
            return $this->sendSuccess(null, 'O\'chirildi');
        });
    }

    public function history($id): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->history($id, $this->user, $this->roleId),
            'Success'
        ));
    }

    /**
     * Service xatolarini javobga aylantiradi: HttpException - o'z kodi bilan (403 / 422), topilmasa - 404.
     */
    private function respond(Closure $action): JsonResponse
    {
        try {
            return $action();
        } catch (HttpException $exception) {
            return $this->sendError($exception->getMessage(), [], $exception->getStatusCode());
        } catch (ModelNotFoundException) {
            return $this->sendError('Sinov topilmadi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }
}
