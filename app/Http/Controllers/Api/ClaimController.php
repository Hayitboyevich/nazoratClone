<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleEnum;
use App\Helpers\ClaimStatuses;
use App\Http\Requests\ClaimGovGetRequest;
use App\Http\Requests\ClaimRequests\AcceptTask;
use App\Http\Requests\ClaimRequests\AttachBLockAndOrganization;
use App\Http\Requests\ClaimRequests\AttachObject;
use App\Http\Requests\ClaimRequests\ClaimSendToMinstroy;
use App\Http\Requests\ClaimRequests\ConclusionClaimByDirector;
use App\Http\Requests\ClaimRequests\ConclusionClaimByInspector;
use App\Http\Requests\ClaimRequests\ConclusionOrganization;
use App\Http\Requests\ClaimRequests\ManualConfirmDirector;
use App\Http\Requests\ClaimRequests\rejectClaimByInspector;
use App\Http\Requests\ClaimRequests\RejectClaimByOperator;
use App\Http\Requests\ClaimRequests\RejectFromDirector;
use App\Http\Requests\ClaimRequests\SendToDirector;
use App\Http\Requests\ClaimUpdateRequest;
use App\Http\Requests\ConclusionComplianceRequest;
use App\Models\Article;
use App\Models\ClaimGov;
use App\Models\ClaimOrganizationReview;
use App\Models\User;
use App\Services\ArticleService;
use App\Services\ClaimService;
use App\Services\HistoryService;
use App\Services\MyHomeService;
use Illuminate\Support\Facades\Auth;

class ClaimController extends BaseController
{


    private ClaimService $claimService;
    private HistoryService $historyService;
    private ArticleService $articleService;
    private MyHomeService $homeService;

    public function __construct(
        ClaimService $claimService,
        ArticleService $articleService,
        MyHomeService $homeService,
    )
    {
        $this->middleware('auth');
        parent::__construct();
        $this->claimService = $claimService;
        $this->articleService = $articleService;
        $this->homeService = $homeService;
        $this->historyService = new HistoryService('claim_histories');
    }

    public function statisticsQuantity()
    {
        $dateFrom = request()->get('date_from', null);
        $dateTo = request()->get('date_to', null);

        $roleId = Auth::user()->getRoleFromToken() ?? null;

        $regionId = match ($roleId) {
            (string)UserRoleEnum::FVB_REG_KADR->value,
            (string)UserRoleEnum::SEOM_REG_KADR->value,
            (string)UserRoleEnum::INSPEKSIYA->value,
            (string)UserRoleEnum::EKOLOGIYA->value,
            (string)UserRoleEnum::HUDUDIY_KUZATUVCHI->value,
            (string)UserRoleEnum::BUXGALTER->value,
            (string)UserRoleEnum::NOGIRONLAR_JAM->value,
            (string)UserRoleEnum::NOGIRONLAR_ASSOT->value,
            (string)UserRoleEnum::UY_JOY_INSPEKSIYA->value,
            (string)UserRoleEnum::OPERATOR->value,
            (string)UserRoleEnum::VILOYAT_FVV->value,
            (string)UserRoleEnum::VILOYAT_SEOM->value,
            (string)UserRoleEnum::INSPECTOR->value => Auth::user()->region_id ?? Auth::user()->region_id ?? null,
            default => request()->get('region', null),
        };

//        $districtId = match ($roleId) {
//            (string)UserRoleEnum::FVB->value, (string)UserRoleEnum::SEOM->value => Auth::user()->district_id ?? Auth::user()->district_id ?? null,
//            default => request()->get('district', null),
//        };

        if ($roleId == (string)UserRoleEnum::INSPECTOR->value)
            $data = $this->claimService->getStatisticsForInspector();
        else
            $data = $this->claimService->getStatisticsCount(
                regionId: $regionId,
//                districtId: $districtId,
                districtId: null,
                expired: null,
                dateFrom: $dateFrom,
                dateTo: $dateTo
            );

        return $this->sendSuccess($data, 'Successfully sent!');
    }

    public function organizationStatisticsQuantity()
    {
        $dateFrom = request()->get('date_from', null);
        $dateTo = request()->get('date_to', null);

        $roleId = Auth::user()->getRoleFromToken() ?? null;

        $regionId = match ($roleId) {
            (string)UserRoleEnum::FVB_REG_KADR->value, (string)UserRoleEnum::SEOM_REG_KADR->value, (string)UserRoleEnum::EKOLOGIYA->value,
            (string)UserRoleEnum::NOGIRONLAR_JAM->value, (string)UserRoleEnum::NOGIRONLAR_ASSOT->value, (string)UserRoleEnum::VILOYAT_FVV->value,
            (string)UserRoleEnum::VILOYAT_SEOM->value, (string)UserRoleEnum::UY_JOY_INSPEKSIYA->value,
            => Auth::user()->region_id ?? Auth::user()->region_id ?? null,
            default => request()->get('region', null),
        };


//        $districtId = match ($roleId) {
//            (string)UserRoleEnum::FVB->value, (string)UserRoleEnum::SEOM->value => Auth::user()->district_id ?? Auth::user()->district_id ?? null,
//            default => request()->get('district', null),
//        };


        $data = $this->claimService->getOrganizationStatistics(
            roleId: $roleId,
            regionId: $regionId,
//            districtId: $districtId,
            districtId: null,
            dateFrom: $dateFrom,
            dateTo: $dateTo
        );

        return $this->sendSuccess($data, 'Successfully sent!');
    }

    public function getPDF()
    {
        $result = $this->claimService->getPDF(request()->get('task_id'));
        return $this->sendSuccess($result, 'Success');
    }

    public function getConclusionPDF()
    {
        $object = $this->claimService->getClaimByGUID(request()->get('task_id'));
        if (!$object)
            $this->sendError("Bunday ma'lumot mavjud emas");

        $result = $this->claimService->getConclusionPDF(request()->get('task_id'));
        if (isset($result['status']))
            return $this->sendError("Xulosa topilmadi");

        return $this->sendSuccess($result, 'Success');
    }

    public function getMyHomeData()
    {
        try {

            $homeId = request('home_id');
            $taskId = request('task_id');
            $claim = $this->claimService->getClaimByTaskId($taskId);


            $data = $this->homeService->getData($homeId, $claim);

            if (!isset($data->data)) return $this->sendError("Ma'lumot topilmadi");

            return $this->sendSuccess($data->data, 'Success');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function attachHomeid()
    {
        try {
            $homeId = request('home_id');
            $id = request('id');

            $claimObject = $this->claimService->getClaimById(
                id: $id,
                role_id: null
            );

            if (!$claimObject) {
                return $this->sendError("Ma'lumot topilmadi");
            }

            if ($claimObject->source_type === 'MKD') {
                if (!is_numeric($homeId) || (int) $homeId <= 0) {
                    return $this->sendError("Home ID noto'g'ri");
                }

               list($success, $body) = $this->homeService->sendData(
                    $claimObject,
                    $homeId
                );

                if (!$success) {
                    return $this->sendError($body['message'] ?? "Mening uyim tizimi bilan xatoli yuz berdi");
                }

                $claimObject->update([
                    'my_home_id' => (string) $homeId,
                ]);
            }

            return $this->sendSuccess('success', 'Success');

        } catch (\Throwable $exception) {
            report($exception);

            return $this->sendError(
                "Xatolik aniqlandi",
                $exception->getMessage()
            );
        }
    }

    public function tasksHistories($id)
    {
        $data = $this->historyService->getHistoryList(
            intval($id)
        );

        return $this->sendSuccess($data, 'Success');
    }

    public function showTask($id)
    {
        $roleId = Auth::user()->getRoleFromToken() ?? null;

        $data = $this->claimService->getClaimById(
            id: $id,
            role_id: (in_array($roleId, [45, 46, 17, 18, 19, 3, 34])) ? $roleId : null
        );

        if (!is_null($data->building_cadastral)) {
            $lift_list = $this->articleService->getLifts($data->building_cadastral);
        } else {
            $lift_list = null;
        }


        if (empty($lift_list->data)) {
            $object = Article::query()->find($data->object_id);
            if ($object && $object->cadastral_number) {
                $lift_list = $this->articleService->getLifts($object->cadastral_number);
            }
        }

        if (!$data) {
            return $this->sendError("Ma'lumot topilmadi!", [], 422);
        }

        $user = User::with('region')->find(Auth::id());

        if ($roleId == (string)UserRoleEnum::OPERATOR->value && $data->region != $user->region->soato)
            return $this->sendError("Ma'lumot topilmadi!", [], 422);
        if (!is_null($data->object)) $data->object['lifts'] = $lift_list?->data;

        return $this->sendSuccess($data, 'Success!');
    }



    public function tasksList()
    {
        $task_id = request()->get('task_id', null);
        $object_task_id = request()->get('object_task_id', null);
        $building_name = request()->get('name', null);
        $customer = request()->get('customer', null);
        $sender = request()->get('sender', null);
        $startDate = request()->get('start_date', null);
        $endDate = request()->get('end_date', null);
        $districtId = request()->get('district_id', null);
        $sortBy = request()->get('sort_by_date', 'desc');
        $type = request()->get('type', null);

        $status = request()->get('status', null);
        $sphereId = request()->get('sphere_id', null);
        $expired = request()->get('expired', 0);

        $roleId = Auth::user()->getRoleFromToken() ?? null;

        $regionId = match ($roleId) {
            (string)UserRoleEnum::FVB_REG_KADR->value,
            (string)UserRoleEnum::SEOM_REG_KADR->value,
            (string)UserRoleEnum::INSPEKSIYA->value,
            (string)UserRoleEnum::HUDUDIY_KUZATUVCHI->value,
            (string)UserRoleEnum::BUXGALTER->value,
            (string)UserRoleEnum::NOGIRONLAR_JAM->value,
            (string)UserRoleEnum::NOGIRONLAR_ASSOT->value,
            (string)UserRoleEnum::UY_JOY_INSPEKSIYA->value,
            (string)UserRoleEnum::EKOLOGIYA->value,
            (string)UserRoleEnum::OPERATOR->value,
            (string)UserRoleEnum::VILOYAT_FVV->value,
            (string)UserRoleEnum::VILOYAT_SEOM->value,
            (string)UserRoleEnum::KOMPLAYNS->value,
            (string)UserRoleEnum::INSPECTOR->value => Auth::user()->region_id ?? Auth::user()->region_id ?? null,
            default => request()->get('region_id', null),
        };


//        $districtId = match ($roleId) {
//            (string)UserRoleEnum::FVB->value,
//            (string)UserRoleEnum::SEOM->value => Auth::user()->district_id ?? Auth::user()->district_id ?? null,
//            default => request()->get('district_id', null),
//        };

        $data = $this->claimService->getTaskList(
            regionId: $regionId,
            task_id: $task_id,
            object_task_id: $object_task_id,
            name: $building_name,
            customer: $customer,
            sender: $sender,
            districtId: $districtId,
            sortBy: $sortBy,
            status: $status,
            expired: $expired,
            role_id: (in_array($roleId, [45, 46, 17, 18, 19, 3, 21, 23, 20, 22, 24, 25, 34, 35])) ? $roleId : null,
            start_date: $startDate,
            end_date: $endDate,
            sphereId: $sphereId,
            type: $type,
        );


        return $this->sendSuccess($data->items(), 'Successfully sent!', pagination($data));
    }

    public function sendToMinstroy(ClaimSendToMinstroy $request)
    {

        $response = $this->claimService->sendToMinstroy($request->guid, $request->comment);

        if ($response) {
            return $this->sendSuccess("Yuborildi!", 'Success');
        } else {
            return $this->sendError("API ERROR", [], 400);
        }

    }

    public function acceptTask(AcceptTask $request)
    {

        $response = $this->claimService->acceptTask($request);

        if ($response) {
            return $this->sendSuccess("Yuborildi!", 'Success');
        } else {
            return $this->sendError("API ERROR", [], 400);
        }

    }

    public function update(ClaimUpdateRequest $request)
    {
        try {
            $response = $this->claimService->update($request);
            if ($response) {
                return $this->sendSuccess("Yangilandi!", 'Success');
            }else{
                return $this->sendError("API ERROR", [], 400);
            }

        }catch (\Exception $exception){
            return $this->sendError("API ERROR", [], 400);
        }
    }

    public function getObjects($id)
    {
        $filters = request()->only(['name']);
        $data = $this->claimService->getObjects(
            id: $id,
            filters: $filters,
            type: request()->get('type')
        );


        if (!$data) {
            return $this->sendError("Tizimda xatolik", [], 422);
        }

        return $this->sendSuccess($data, 'Success!');
    }

    public function attachObject(AttachObject $request)
    {
        $response = $this->claimService->attachObject($request);

        if ($response) {
            return $this->sendSuccess("Biriktirildi!", 'Success');
        } else {
            return $this->sendError("API ERROR", [], "message");
        }
    }

    public function attachBlockAndOrganization(AttachBLockAndOrganization $request)
    {
        try {
            $blocks = $request['blocks'];
            $guid = $request['gu_id'];
            $errors = $this->claimService->checkBlockInProgress(blocks: $blocks, guid: $guid);

            if (!empty($errors)) {
                return $this->sendError("Bloklarda hatolik!", $errors, 400);
            }

            $response = $this->claimService->attachBlockAndOrganization($request);
            if ($response) {
                return $this->sendSuccess("Biriktirildi!", 'Success');
            }

            return $this->sendError("API ERROR", "message");
        }catch (\Exception $exception){
            return $this->sendError("API ERROR", $exception->getMessage(), 400);
        }

    }

    public function conclusionOrganization(ConclusionOrganization $request)
    {
        try {
            $response = $this->claimService->conclusionOrganization($this->user, $this->roleId, $request);

            $review = ClaimOrganizationReview::with('monitoring.claim')
                ->findOrFail($response['review_id']);

            $claim = $this->claimService->getClaimById(
                id: $review->monitoring->claim->id,
                role_id: null
            );

            return $this->sendSuccess($claim, 'Success');

        } catch (\Throwable $exception) {

            telegramNotification(
                'Arizani tasdiqlashda xatolik',
                $request->review_id,
                $exception->getMessage()
            );

            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }

    }

    public function resend()
    {
        try {
            $id = request('id');
            $comment = request('comment');

            $this->claimService->resend($this->user, $id, $comment);

            return $this->sendSuccess("Yuborildi!", 'Success');
        }catch (\Exception $exception){
            return $this->sendError("API ERROR", [], $exception->getMessage());
        }


    }

    public function conclusionCompliance(ConclusionComplianceRequest $request)
    {
        try {
            $data = $this->claimService->conclusionCompliance($this->user, $this->roleId, $request);
            return $this->sendSuccess($data, 'Success');
        }catch (\Exception $exception){
            return $this->sendError("Xatolik aniqlandi", [], $exception->getMessage());
        }
    }

    public function conclusionClaimByInspector(ConclusionClaimByInspector $request)
    {
        $claimObject = $this->claimService->getClaimById(id: $request['id'], role_id: null);
        if ($request['type'] == 20) {
            $errors = $this->claimService->checkBlockStatus(claim_id: $request['id']);
            if (!empty($errors)) {
                return $this->sendError('Bloklarda hatolik!', $errors, 400);
            }
        }

        if ($claimObject->source_type == 'MKD' && $request['my_home_id'] !== 'no')
        {
            list($success, $body) = $this->homeService->sendData(
                $claimObject,
                $request['my_home_id']
            );

            if (!$success) {
                return $this->sendError($body['message'] ?? "Mening uyim tizimi bilan xatoli yuz berdi");
            }
        }

        $response = $this->claimService->conclusionByInspector($request);

        if ($response) {
            return $this->sendSuccess('Yuborildi!', 'Success');
        } else {
            return $this->sendError("API ERROR", "message");
        }
    }

    public function conclusionClaimByDirector(ConclusionClaimByDirector $request)
    {

        if ($request['type'] == 20) {
            $errors = $this->claimService->checkBlockStatus(claim_id: $request['id']);
            if (!empty($errors)) {
                return $this->sendError('Bloklarda hatolik!', $errors, 400);
            }
        }

        $response = $this->claimService->conclusionByDirector($request, $this->user, $this->roleId);

        if ($response) {
            return $this->sendSuccess('Javob berildi!', 'Success');
        } else {
            return $this->sendError("API ERROR", "message");
        }
    }

    public function rejectByOperator(RejectClaimByOperator $request)
    {
        $response = $this->claimService->rejectClaimByOperator($request);

        if ($response) {
            return $this->sendSuccess('Javob berildi!', 'Success');
        } else {
            return $this->sendError("API ERROR", "message");
        }
    }

    public function rejectFromDirector(RejectFromDirector $request)
    {
        $response = $this->claimService->rejectFromDirector($this->user, $this->roleId, $request);

        if ($response) {
            return $this->sendSuccess('Qaytarildi!', 'Success');
        } else {
            return $this->sendError("API ERROR", "message");
        }
    }

    public function sendToDirector(SendToDirector $request)
    {

        $response = $this->claimService->sendToDirector($request);

        if ($response) {
            return $this->sendSuccess('Yuborildi!', 'Success');
        } else {
            return $this->sendError("API ERROR", "message");
        }
    }

    public function manualAccept(ManualConfirmDirector $request)
    {
        $roleId = Auth::user()->getRoleFromToken() ?? null;
        if ($roleId != UserRoleEnum::INSPEKSIYA->value)
            return $this->sendError("Siz bu amalni bajara olmaysiz!", "message");

        $response = $this->claimService->manualConfirmByDirector($request, $this->user, $this->roleId);

        if ($response) {
            return $this->sendSuccess('Yuborildi!', 'Success');
        } else {
            return $this->sendError("Bloklar yakunlanmagan", "message");
        }
    }

    public function statistics()
    {
        $claim_govs = ClaimGov::query();
        $result = $claim_govs->selectRaw("
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE status = ?) as in_process,
            COUNT(*) FILTER (WHERE status = ?) as sent_organization,
            COUNT(*) FILTER (WHERE status = ?) as organization_rejected,
            COUNT(*) FILTER (WHERE status = ?) as inspector,
            COUNT(*) FILTER (WHERE status = ?) as director,
            COUNT(*) FILTER (WHERE status = ?) as rejcted_from_inspector,
            COUNT(*) FILTER (WHERE status = ?) as confirmed,
            COUNT(*) FILTER (WHERE status = ?) as pending,
            COUNT(*) FILTER (WHERE status = ?) as cancelled,
            COUNT(*) FILTER (WHERE status = ?) as rejected
            ", [
            ClaimStatuses::TASK_STATUS_ACCEPTANCE,
            ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION,
            ClaimStatuses::TASK_STATUS_ATTACH_OBJECT,
            ClaimStatuses::TASK_STATUS_INSPECTOR,
            ClaimStatuses::TASK_STATUS_DIRECTOR,
            8,
            ClaimStatuses::TASK_STATUS_CONFIRMED,
            ClaimStatuses::PAY_PROGRESS,
            ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED,
            ClaimStatuses::TASK_STATUS_REJECTED
        ])->first();
        return $this->sendSuccess($result, 'Success');
    }
}
