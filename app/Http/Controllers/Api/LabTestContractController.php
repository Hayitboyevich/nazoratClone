<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LabTestContractAdditionalRequest;
use App\Http\Requests\LabTestContractBlocksRequest;
use App\Http\Requests\LabTestContractCancelRequest;
use App\Http\Requests\LabTestContractFilterRequest;
use App\Http\Requests\LabTestContractItemsRequest;
use App\Http\Requests\LabTestContractObjectRequest;
use App\Http\Requests\LabTestContractRequest;
use App\Http\Requests\LabTestContractReturnRequest;
use App\Services\LabTestContractService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Laboratoriya arizalari / shartnomalari («Laboratoriya kabinetlari» oqimi).
 * Qoidalar - LabTestContractService, so'rovlar - LabTestContractRepository.
 */
class LabTestContractController extends BaseController
{
    public function __construct(private LabTestContractService $service)
    {
        parent::__construct();
    }

    public function dictionaries(): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess($this->service->dictionaries($this->user), 'Success'));
    }

    public function statistics(): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess($this->service->statistics($this->user, $this->roleId), 'Success'));
    }

    /** Laborantlar ro'yxati (boshliq kabineti) */
    public function employees(): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess($this->service->employees($this->user, $this->roleId), 'Success'));
    }

    public function contracts(LabTestContractFilterRequest $request, $id = null): JsonResponse
    {
        return $this->respond(function () use ($request, $id) {
            if ($id) {
                return $this->sendSuccess($this->service->show($id, $this->user, $this->roleId), 'Success');
            }

            $data = $this->service->all($request->validated(), $this->user, $this->roleId);
            return $this->sendSuccess($data->items(), 'Success', pagination($data));
        });
    }

    public function history($id): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess($this->service->history($id, $this->user, $this->roleId), 'Success'));
    }

    /** Shartnoma fayli (PDF, base64): tasdiqlanmaguncha namuna, buyurtmachi tasdiqlagach - tasdiqlangan */
    public function pdf($id): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess($this->service->pdf($id, $this->user, $this->roleId), 'PDF'));
    }

    /** Laborant sinov o'tkazadigan ishlar */
    public function myItems(LabTestContractFilterRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $filters = array_merge($request->validated(), ['contract_id' => $request->integer('contract_id') ?: null]);
            $data = $this->service->assignedItems($filters, $this->user, $this->roleId);
            return $this->sendSuccess($data->items(), 'Success', pagination($data));
        });
    }

    /** Shartnoma biriktirilgan obyektlar ro'yxati: shartnomalari va sinovlar yig'masi bilan */
    public function objects(LabTestContractFilterRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $data = $this->service->objects($request->validated(), $this->user, $this->roleId);
            return $this->sendSuccess($data->items(), 'Success', pagination($data));
        });
    }

    public function create(LabTestContractRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->create($request->validated(), $this->user, $this->roleId),
            'Ariza yaratildi va laborantga biriktirildi'
        ));
    }

    /** Tasdiqlangan shartnomaga qo'shimcha shartnoma (yangi blok yoki oldin tanlanmagan sinovlar uchun) */
    public function createAdditional($id, LabTestContractAdditionalRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->createAdditional($id, $request->validated(), $this->user, $this->roleId),
            "Qo'shimcha shartnoma yaratildi va laborantga biriktirildi"
        ));
    }

    public function update($id, LabTestContractRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->update($id, $request->validated(), $this->user, $this->roleId),
            'Saqlandi'
        ));
    }

    public function saveBlocks($id, LabTestContractBlocksRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->saveBlocks($id, $request->validated('blocks'), $this->user, $this->roleId),
            'Bloklar saqlandi'
        ));
    }

    public function saveItems($id, LabTestContractItemsRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->saveItems($id, $request->validated('items'), $this->user, $this->roleId),
            'Ishlar saqlandi'
        ));
    }

    public function send($id): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->send($id, $this->user, $this->roleId),
            'Shartnoma buxgalterga yuborildi'
        ));
    }

    /** Buxgalter, inspeksiya boshlig'i yoki buyurtmachi tasdig'i */
    public function approve($id): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->approve($id, $this->user, $this->roleId),
            'Tasdiqlandi'
        ));
    }

    /** Buxgalter yoki inspeksiya boshlig'i laborantga qaytaradi */
    public function returnToEmployee($id, LabTestContractReturnRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->returnToEmployee($id, $request->comment, $request->validated('reasons') ?? [], $this->user, $this->roleId),
            'Laborantga qaytarildi'
        ));
    }

    /** Buyurtmachi shartnomani bekor qiladi */
    public function cancel($id, LabTestContractCancelRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->cancel($id, $request->comment, $this->user, $this->roleId),
            'Ariza bekor qilindi'
        ));
    }

    public function attachObject($id, LabTestContractObjectRequest $request): JsonResponse
    {
        return $this->respond(fn() => $this->sendSuccess(
            $this->service->attachObject($id, (int)$request->object_id, $this->user, $this->roleId),
            'Obyekt biriktirildi'
        ));
    }

    public function delete($id): JsonResponse
    {
        return $this->respond(function () use ($id) {
            $this->service->delete($id, $this->user, $this->roleId);
            return $this->sendSuccess(null, 'O\'chirildi');
        });
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
            return $this->sendError('Ariza topilmadi');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage(), 400);
        }
    }
}
