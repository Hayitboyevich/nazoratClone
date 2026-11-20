<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConstructionWork;
use App\Enums\DxaResponseStatusEnum;
use App\Exceptions\NotFoundException;
use App\Helpers\ClaimStatuses;
use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\ClaimGov;
use App\Models\DxaResponse;
use App\Services\MyGovService;
use Http\Client\Exception;
use http\Url;
use Illuminate\Support\Facades\Auth;

class MyGovController extends Controller
{
    private MyGovService $myGovService;

    public function __construct(MyGovService $myGovService)
    {
        $this->myGovService = $myGovService;
    }

    public function showTask($id)
    {
        $data = $this->myGovService->getDxaTaskById(task_id: $id);

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }

    public function getObjectsByPinfl()
    {
        $data = $this->myGovService->getObjectsByPinfl(pinfl: request()->get('pinfl'));

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }

    public function getObjectsByCustomer()
    {
        $data = $this->myGovService->getObjectsByCustomer(pinfl: request()->get('pinfl'));

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }

    public function getObjectsByCadastr()
    {
        $data = $this->myGovService->getObjectsByCadastralNumber(request('cadastr'), request('sphereId'));

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }

    public function getObjectDeclaration()
    {
        try {
            $taskId = request('task_id');
            $inn    = request('inn');


            if (!$taskId || !$inn) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ma\'lumot topilmadi'
                ], 200);
            }

            $data = $this->myGovService->getDeclaration($taskId, $inn);

            return response()->json([
                'success' => true,
                'data'    => $data
            ]);

        } catch (NotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 200);
        }
    }

    public function getObjectsList()
    {
        $filters = request()->only(['date_from', 'date_to', 'regions', 'stir', 'page', 'per_page']);

        $data = $this->myGovService->getObjectList($filters);

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }
        return $data;
    }

    public function getRegisterByCadastre()
    {
        $cadastre = request('cadastre');

        if (!$cadastre) {
            return response()->json([
                'success' => false,
                'message' => "Kadastr raqami noto'g'ri"
            ], 404);
        }

        return DxaResponse::query()
            ->where('cadastral_number', $cadastre)
            ->get()
            ->map(fn ($response) => [
                'task_id' => $response->task_id,
                'cadastral_number' => $response->cadastral_number,
                'notification_type' => $response->notification_type,
                'status' => $response->status?->status,
                'created_at' => $response->created_at,
                'url' => in_array($response->dxa_response_status_id, [
                    DxaResponseStatusEnum::ACCEPTED,
                    DxaResponseStatusEnum::REJECTED
                ])
                    ? url('api/conclusion/' . $response->task_id . '/download')
                    : null,
            ]);

    }

    public function getClaimByCadastre()
    {
        $cadastre = request('cadastre');

        if (!$cadastre) {
            return response()->json([
                'success' => false,
                'message' => "Kadastr raqami noto'g'ri"
            ], 404);
        }

        return Claim::query()
            ->whereNot('status', ClaimStatuses::TASK_STATUS_ANOTHER)
            ->where('building_cadastral', $cadastre)
            ->get()
            ->map(fn ($claim) => [
                'task_id' => $claim->guid,
                'cadastral_number' => $claim->building_cadastral,
                'status' => ClaimStatuses::getLabel($claim->status),
                'created_at' => $claim->created_at,
                'url' => in_array($claim->status, [
                    ClaimStatuses::TASK_STATUS_CONFIRMED,
                    ClaimStatuses::TASK_STATUS_REJECTED,
                    ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED,
                ])
                    ? url('api/claim/conclusion/' . $claim->guid . '/download')
                    : null,
            ]);

    }



    public function getObjectsByOrganization()
    {

        $data = $this->myGovService->getObjectOrganization();

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }

    public function getObjectsByDesign()
    {
        $data = $this->myGovService->getObjectDesign();

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }

    public function getObjectsRegulations()
    {
        $filters = request()->only(['gnk_id', 'number_protocol', 'reestr_number']);


        $data = $this->myGovService->getObjectsRegulations($filters);

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }

    public function getObjectTax()
    {
        $objectId = request('object_id');
        $data = $this->myGovService->getObjectTax($objectId);

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }

    public function getObjectReestr()
    {
        $filters = request()->only(['reestr_number']);

        $data = $this->myGovService->getObjectByReestr($filters);

        if (!$data) {
            return response()->json(['success' => false, 'message' => "Ma'lumot topilmadi"], 404);
        }

        return $data;
    }


}
