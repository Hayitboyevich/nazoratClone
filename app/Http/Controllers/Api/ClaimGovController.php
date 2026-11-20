<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ClaimGovAttachRequest;
use App\Http\Requests\ClaimGovConclusionDirectorRequest;
use App\Http\Requests\ClaimGovOrganizationRequest;
use App\Http\Requests\ClaimGovRejectRequest;
use App\Http\Requests\ClaimGovTaskIdRequest;
use App\Http\Requests\ClaimRequests\ConclusionClaimByInspector;
use App\Http\Resources\ClaimGovListResource;
use App\Http\Resources\ClaimGovResource;
use App\Http\Resources\ClaimSphereResource;
use App\Models\ClaimSphere;
use App\Services\ClaimGovService;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Js;

class ClaimGovController extends BaseController
{
    public function __construct(protected ClaimGovService $service, protected ClaimService $claimService)
    {
        parent::__construct();
    }

    public function sphere($id = null): JsonResponse
    {
        try {
            $spheres = ClaimSphere::all();
            return $this->sendSuccess(ClaimSphereResource::collection($spheres), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError("Ma'lumot topilmadi", $exception->getMessage());
        }
    }

    public function index($id = null): JsonResponse
    {
        try {
            $filters = request()->only(['task_id', 'name', 'organization', 'sphere', 'region_id', 'district_id', 'start_date', 'end_date', 'status', 'answered']);

            $data = $id
                ? $this->service->findById($id)
                : $this->service->all($this->user, $this->roleId, $filters)->paginate(request('per_page', 15));

            $resource = $id
                ? ClaimGovResource::make($data)
                : ClaimGovListResource::collection($data);

            return $this->sendSuccess(
                $resource,
                'Success',
                $id ? null : pagination($data)
            );
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function count(): JsonResponse
    {
        $filters = request()->only(['sphere', 'region', 'district', 'start_date', 'end_date', 'answered']);
        $data = $this->service->count($this->user, $this->roleId, $filters);
        return $this->sendSuccess($data, 'Success');

    }

    public function attachSphere(ClaimGovAttachRequest $request)
    {
        try {
            $data = $this->service->attachSphere($request);
            return $this->sendSuccess(ClaimGovResource::make($data), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function conclusionOrganization(ClaimGovOrganizationRequest $request): JsonResponse
    {
        try {
            $model = $this->service->conclusion($this->user, $this->roleId, $request);
            return $this->sendSuccess(ClaimGovResource::make($model), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function conclusionInspector(ConclusionClaimByInspector $request): JsonResponse
    {
        try {
            if ($request['type'] == 20) {
                $errors = $this->service->checkBlockStatus(claimId: $request['id']);
                if (!empty($errors)) {
                    return $this->sendError('Bloklarda hatolik!', $errors, 400);
                }
            }

            $response = $this->service->conclusionInspector($request);

            if ($response) {
                return $this->sendSuccess('Yuborildi!', 'Success');
            } else {
                return $this->sendError("API ERROR", "message");
            }
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function conclusionDirector(ClaimGovConclusionDirectorRequest $request): JsonResponse
    {
        try {
            if ($request['status'] == 20) {
                $errors = $this->service->checkBlockStatus(claimId: $request['id']);
                if (!empty($errors)) {
                    return $this->sendError('Bloklarda hatolik!', $errors, 400);
                }
            }
            
            $data = $this->service->conclusionDirector($this->user, $this->roleId, $request);
            return $this->sendSuccess(ClaimGovResource::make($data), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }

    }

    public function reject(ClaimGovRejectRequest $request): JsonResponse
    {
        try {
            $model = $this->service->reject($this->user, $request);
            return $this->sendSuccess(ClaimGovResource::make($model), 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }


    public function histories($id = null): JsonResponse
    {
        try {
            $data = $this->service->getHistory($id);
            return $this->sendSuccess($data, 'History get successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function organizationPDF($reviewId): JsonResponse
    {
        try {
            $data = $this->service->organizationPdf($reviewId);
            return $this->sendSuccess($data, 'Pdf get successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function oldReviews()
    {
        try {
            $objectId = request('object_id');
            $sphereId = request('sphere_id');
            $data = $this->service->getOldReviews($objectId, $sphereId);

            if (!$data) return $this->sendError('Ma\'lumot topilmadi');

            return $this->sendSuccess($data, 'Success');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function pdf($id)
    {
        try {
            $data = $this->service->pdf($id);
            return $this->sendSuccess($data, 'Pdf get successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function authorPdf($id)
    {
        try {
            $data = $this->service->authorPdf($id);
            return $this->sendSuccess($data, 'Pdf get successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function technicPdf($id)
    {
        try {
            $data = $this->service->technicPdf($id);
            return $this->sendSuccess($data, 'Pdf get successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function getRepoPDF($taskId)
    {
        try {
            $data = $this->service->getRepoPDF($taskId);
            return $this->sendSuccess($data, 'Conclusion PDF');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function myGovPdf($id)
    {
        try {
            $data = $this->service->getMyGovPdf($id);
            return $this->sendSuccess($data, 'Pdf get successfully.');
        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function organizationStatistics()
    {
        try {
            $data = $this->service->orgStatistic($this->user, $this->roleId);
            return $this->sendSuccess($data, 'Statistics get successfully.');
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function getConclusionPDF(ClaimGovTaskIdRequest $request)
    {
        $validated = $request->validated();
        $task_id = $validated['task_id'];
        $result = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get(config('app.mygov.url_mc') . "get-repo-list?id=$task_id")->object();
        if (isset($result->guid) && $result->guid) {
            $file = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get(config('app.mygov.url_mc') . "get-repo?guid=$result->guid")->object();

        } else {
            $result = (array)$result;
            if (!isset($result['status'])) {
                $count = count($result);
                if (isset($result[$count - 1]->guid) && $result[$count - 1]->guid)
                    $guid = $result[$count - 1]->guid;
                else
                    $guid = $result[0]->guid;
                $file = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->get(config('app.mygov.url_mc') . "get-repo?guid=$guid");
            } else
                return ['status' => 404];
        }
        return $this->sendSuccess($file->json(), 'Pdf get successfully.');
    }
}
