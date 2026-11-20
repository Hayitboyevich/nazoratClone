<?php

namespace App\Http\Controllers\Api;

use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\NotFoundException;
use App\Helpers\ClaimStatuses;
use App\Helpers\OrganizationNames;
use App\Http\Resources\UserResource;
use App\Models\Article;
use App\Models\Claim;
use App\Models\ClaimGov;
use App\Models\ClaimOrganizationReview;
use App\Models\Declaration;
use App\Models\DxaResponse;
use App\Models\Inspection;
use App\Models\Presentation;
use App\Models\Regulation;
use App\Models\Rekvizit;
use App\Models\Response;
use App\Models\Role;
use App\Models\User;
use App\Repositories\ArticleRepository;
use App\Repositories\ClaimRepository;
use App\Services\ClaimGovService;
use App\Services\ClaimService;
use App\Services\InformationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PdfController extends BaseController
{
    protected ClaimService $claimService;

    public function __construct(ClaimService $claimService)
    {
        parent::__construct();
        $this->claimService = $claimService;
    }


    public function generation(): JsonResponse
    {
        try {
            $regulation = Regulation::query()->find(request('regulation_id'));
            $createdByRole = Role::query()->find($regulation->created_by_role_id);
            $createdByUser = User::query()->find($regulation->created_by_user_id);
            $object = $regulation->object;
            $responsibleUser = User::query()->find($regulation->user_id);
            $responsibleRole = Role::query()->find($regulation->role_id);
            $organizationName = '';

            $roleMap = [
                UserRoleEnum::TEXNIK->value => UserRoleEnum::BUYURTMACHI->value,
                UserRoleEnum::ICHKI->value => UserRoleEnum::QURILISH->value,
                UserRoleEnum::MUALLIF->value => UserRoleEnum::LOYIHA->value,
            ];


            if (is_scalar($responsibleRole->id) && isset($roleMap[$responsibleRole->id])) {
                $user = $object->users()->where('role_id', $roleMap[$responsibleRole->id])->first();
                if (!empty($user)) {
                    $organizationName = $user->name ? $user->full_name : $user->organization_name;
                }
            }

            $domain = URL::to('/regulation-info') . '/' . $regulation->id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

//            $qrImageTag = '<img src="data:image/png;base64,' . $qrImage . '" alt="QR Image" />';
            $pdf = Pdf::loadView('pdf.regulation', compact(
                'regulation',
                'object',
                'responsibleUser',
                'responsibleRole',
                'createdByUser',
                'createdByRole',
                'qrImage',
                'organizationName'
            ));

            $pdfOutput = $pdf->output();
            $pdfBase64 = base64_encode($pdfOutput);
            return $this->sendSuccess($pdfBase64, 'PDF');

        } catch (\Exception $exception) {
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function pdfClaim($id)
    {
        $claim = Claim::query()->where(['status' => ClaimStatuses::TASK_STATUS_CONFIRMED, 'object_id' => $id])->first();

        $result = $this->claimService->getConclusionPDF($claim->guid);

        if (isset($result['status']))
            throw new NotFoundException('Hatolik!', 404);

        $bin = base64_decode($result['file'], true);

        return response($bin)
            ->header('Content-Type', 'application/pdf');
    }

    public function pdfDeclaration($id)
    {
        try {
            $declaration = Declaration::query()->find($id);
            $vendor = $declaration->object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
            $designer = $declaration->object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
            $organization = $declaration->object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $internal = $declaration->object->users()->where('role_id', UserRoleEnum::ICHKI->value)->first();
            $domain = URL::to('/declaration-pdf') . '/' . $declaration->id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $pdf = Pdf::loadView('pdf.declaration', compact('declaration', 'qrImage', 'vendor', 'designer', 'organization', 'internal'));

            return $pdf->stream();

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }

    public function pdfOrganization($id)
    {
        try {
            $review = ClaimOrganizationReview::with('monitoring')->where('id', $id)->first();
            $jsonTable = DB::table('claim_organization_reviews')->where('id', $id)->first();
            $jsonTable = json_decode(gzuncompress(base64_decode($jsonTable->answer)), true);
            $region = (in_array($review->organization_id, [15, 16])) ? $review->monitoring->claim->district()->first()->name_uz : $review->monitoring->claim->region()->first()->name_uz;
            $headName = str_replace('{region}', $region, OrganizationNames::NAMES[$review->organization_id]);

            $name = '';
            foreach ($jsonTable as $key => $value) {
                if (str_contains($key, '_name'))
                    $name = $value;
            }
//            $qrCode = QrCode::format('png')->size(150)->generate('Test');
//            $qrCode = base64_encode($qrCode);
            $qrCode = base64_encode(file_get_contents('https://api.shaffofqurilish.uz/api/v1/create-qr-code?size=150x150&data=' . url('/api/organization-pdf/' . $id)));
            $pdf = Pdf::loadView('pdf.review', ['review' => $review, 'name' => $name, 'qrCode' => $qrCode, 'headName' => $headName]);

            return $pdf->stream();

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }

    public function pdfOrganizationDownload($id)
    {
        try {
            $review = ClaimOrganizationReview::with('monitoring')->where('id', $id)->first();
            $jsonTable = DB::table('claim_organization_reviews')->where('id', $id)->first();
            $jsonTable = json_decode(gzuncompress(base64_decode($jsonTable->answer)), true);
            $region = (in_array($review->organization_id, [15, 16])) ? $review->monitoring->claim->district()->first()->name_uz : $review->monitoring->claim->region()->first()->name_uz;
            $headName = str_replace('{region}', $region, OrganizationNames::NAMES[$review->organization_id]);

            $name = '';
            foreach ($jsonTable as $key => $value) {
                if (str_contains($key, '_name'))
                    $name = $value;
            }
//            $qrCode = QrCode::format('png')->size(150)->generate('Test');
//            $qrCode = base64_encode($qrCode);
            $qrCode = base64_encode(file_get_contents('https://api.shaffofqurilish.uz/api/v1/create-qr-code?size=150x150&data=' . url('/api/organization-pdf/' . $id)));
            $pdf = Pdf::loadView('pdf.review', ['review' => $review, 'name' => $name, 'qrCode' => $qrCode, 'headName' => $headName]);

            $pdfOutput = $pdf->output();
            $pdfBase64 = base64_encode($pdfOutput);
            return $this->sendSuccess($pdfBase64, 'PDF');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }

    public function presentationPdf($id)
    {
        try {
            $presentation = Presentation::find($id);
            $inspection = Inspection::query()->where(['region_id' => $presentation->object->region_id])->first();
            $buyurtmachi = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $pudrat = $presentation->object->users()->wherePivot('role_id', UserRoleEnum::QURILISH->value)->first();
            $domain = URL::to('/presentation-pdf') . '/' . $id;


            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));
            $pdf = PDF::loadView('pdf.presentation', compact('presentation', 'inspection', 'buyurtmachi', 'pudrat', 'qrImage'));

            return $pdf->stream('protocol');
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    public function getObjectPdf($taskId)
    {
        try {
            $informationService = new InformationService();
            $data = $informationService->getConclusionPDF($taskId);

            return $this->sendSuccess($data, 'Conclusion PDF');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }


    public function oferta($id)
    {
        try {
            $dxa = DxaResponse::query()->findOrFail($id);
            $region = $dxa->region;
            $inspection = Inspection::query()->where('region_id', $dxa->region_id)->first();
            $rekvizit = Rekvizit::query()->where('district_id', $dxa->district_id)->first();
            $domain = 'https://nazorat.mc.uz/mygov/pdf/' . $id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $pdf = Pdf::loadView('pdf.object', compact('inspection', 'region', 'dxa', 'rekvizit', 'qrImage'));
            $pdfOutput = $pdf->output();
            $pdfBase64 = base64_encode($pdfOutput);
            return $this->sendSuccess($pdfBase64, 'PDF');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function getDalolatnoma($id)
    {
        $object = Article::where('object_status_id', ObjectStatusEnum::SUBMITTED->value)
            ->where('id', $id)
            ->first();
        if ($object) {
            $claimGov = ClaimGov::where('object_id', $object->id)->where('status', ClaimStatuses::TASK_STATUS_CONFIRMED)->first();
            $object = $claimGov->object;
            $response = Http::withBasicAuth(config('app.mygov.login_mc'), config('app.mygov.password_mc'))
                ->get(config('app.mygov.url_mc') . "get-ds-info?id=" . $claimGov->task_id);
            $result = $response->json();
            Log::channel('daily')->log('info', $result);
            if ($object->id == 68381){
                $signed_info = [
                    "CN" => "ILMURATOV ABROR UROLOVICH",
                    "O" => "SAMARQAND VILOYATI HOKIMLIGI YAGONA BUYURTMACHI XIZMATI DM",
                    "sign_date" => "13.06.2026 09:29:22",
                ];
            } elseif ($object->id  == 68276){
                $signed_info = [
                    "CN" => "ILMURATOV ABROR UROLOVICH",
                    "O" => "SAMARQAND VILOYATI HOKIMLIGI YAGONA BUYURTMACHI XIZMATI DM",
                    "sign_date" => "13.06.2026 11:04:07",
                ];
            } elseif ($object->id  == 68005){
                $signed_info = [
                    "CN" => "ILMURATOV ABROR UROLOVICH",
                    "O" => "SAMARQAND VILOYATI HOKIMLIGI YAGONA BUYURTMACHI XIZMATI DM",
                    "sign_date" => "13.06.2026 10:34:10",
                ];
            } else {
                $signed_info = !empty($result["signatures"])?$result["signatures"][0]:null;
            }
            $declaration = $claimGov->declaration;
            $domain = config('app.url') . '/api/pdf-dalolatnoma/' . $object->id;
            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));
            $designer = $object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
            $vendor = $object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
            $pdf = Pdf::loadView('pdf.claim_gov', compact('qrImage','signed_info','claimGov', 'declaration', 'designer', 'vendor', 'object'));
            return $pdf->stream();
        } else {
            return $this->sendError('Xatolik!', 'Mavjud emas');
        }
    }

}
