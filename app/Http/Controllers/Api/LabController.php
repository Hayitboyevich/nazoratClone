<?php

namespace App\Http\Controllers\Api;

use App\Enums\LabCheckListTestingStatusEnum;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Requests\LabObjectsGetRequest;
use App\Scopes\LabClaimUserScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachLaborantRequest;
use App\Http\Requests\GetLabGradRequest;
use App\Http\Requests\LabAttachObjectRequest;
use App\Http\Requests\LabCheckListConditionRequest;
use App\Http\Requests\LabCheckListConInfoRequest;
use App\Http\Requests\LabCheckListFirstTestingTypeRequest;
use App\Http\Requests\LabCheckListGetQuestionMarksRequest;
use App\Http\Requests\LabCheckListHistoryRequest;
use App\Http\Requests\LabCheckListMainInfoRequest;
use App\Http\Requests\LabCheckListRequest;
use App\Http\Requests\LabCheckListStatementMarkRequest;
use App\Http\Requests\LabCheckListStatementRequest;
use App\Http\Requests\LabCheckListTestingFilterRequest;
use App\Http\Requests\LabClaimManuallyRequest;
use App\Http\Requests\LabGetBlockTestsRequest;
use App\Http\Requests\LabGradirofkaRequest;
use App\Http\Requests\LabProbationRequest;
use App\Http\Requests\LabProtocolRequest;
use App\Http\Requests\LabRequest;
use App\Http\Requests\LabTestCategoryRequest;
use App\Http\Requests\LabTestEditRequest;
use App\Http\Requests\LabTestPriceRequest;
use App\Http\Requests\PassLabRequest;
use App\Exports\LabProtocolExport;
use App\Http\Resources\LabCheckListInfoResouce;
use App\Http\Resources\LabClaimResource;
use App\Http\Resources\LaborantUserListResource;
use App\Http\Resources\LabStatisticsResource;
use App\Models\Article;
use App\Models\ArticleUser;
use App\Models\District;
use App\Models\Inspection;
use App\Models\LabCheckListChosenTestingType;
use App\Models\LabCheckListTestingCondition;
use App\Models\LabCheckListTestingStatementMark;
use App\Models\LabClaim;
use App\Models\LabClaimUser;
use App\Models\LabContract;
use App\Models\LabGradirovkaCoefficient;
use App\Models\LabGradirovkaInfo;
use App\Models\LabGradirovkaMainInfo;
use App\Models\LabMeasurementInstrument;
use App\Models\LabProtocolConstruction;
use App\Models\LabProtocolConstructionIndex;
use App\Models\LabTestActionInfo;
use App\Models\LabTestCategory;
use App\Models\LabTestingOrg;
use App\Models\LabTestingProtocolMain;
use App\Models\Region;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WorkType;
use App\Repositories\Interfaces\LabCheckListRepositoryInterface;
use App\Services\LabCheckListService;
use App\Services\LabService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LabController extends Controller
{
    private LabService $labService;
    private LabCheckListService $labCheckListService;

    public function __construct(LabService $labService, LabCheckListService $labCheckListService)
    {
        $this->labService = $labService;
        $this->labCheckListService = $labCheckListService;
    }

    protected function getAuthData(): array
    {
        $user = Auth::user();
        return [$user, $user?->getRoleFromToken()];
    }

    /////////////////////////////////////////////LabClaim///////////////////////////////////////////////////////////////////////////////////////////////
    public function getListLabClaims()
    {
        $filters = request()->only(['region_id', 'district_id', 'customer_name', 'lab_claim_id', 'test_type', 'lab_id', 'per_page']);
        $lab_claims_list = $this->labService->getClaims($filters);
        if (!$lab_claims_list['success']) {
            return $this->sendingError($lab_claims_list['message'], $lab_claims_list['code']);
        }
        return $this->sendingSuccessWithPagination(LabClaimResource::collection($lab_claims_list['data']->paginate(!empty($filters['per_page']) ? $filters['per_page'] : 10)), 'Arizalar ro\'yxati shakllandi!');

    }

    public function getLabWorkTypes()
    {
        $items = WorkType::query()
            ->where('is_laboratoriya', true)
            ->when(!empty(request()->input('id')), function ($query) {
                $query->where('id', request()->input('id'));
            })
            ->with('LabQuestions')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) {
                $item->questions = $item->LabQuestions;
                unset($item->LabQuestions);
                return $item;
            });

        return $this->sendingSuccess($items, 'Success!');
    }

    public function createLabClaimManually(LabClaimManuallyRequest $request)
    {
        $validated = $request->validated();
        $lab_claim = $validated['lab_claim_info'];
        $customer_info = $validated['lab_customer_info'];
        if ((int)$lab_claim['lab_test_category_id'] === 1 or (int)$lab_claim['lab_test_category_id'] === 2 or (int)$lab_claim['lab_test_category_id'] === 120) {
            $paths = [];
            if (request()->hasFile('lab_claim_info.project_estimate_file_path')) {
                foreach (request()->file('lab_claim_info.project_estimate_file_path') as $file) {
                    $dateDir = now()->format('Y-m-d');
                    $paths[] = $file->store("project_estimates/$dateDir", 'public');
                }
            }
            if (!empty($customer_info['user_id'])) {
                $user = User::find($validated['lab_customer_info']['user_id']);
            } else {
                if (User::where('pinfl', $customer_info['pinfl'])->exists()) {
                    $user = User::where('pinfl', $customer_info['pinfl'])->first();
                } else {
                    $user = User::create([
                        'name' => $customer_info['name'] ?? null,
                        'surname' => $customer_info['surname'] ?? null,
                        'middle_name' => $customer_info['middle_name'] ?? null,
                        'phone' => $customer_info['phone'] ?? null,
                        'login' => $customer_info['passport_number'] ?? null,
                        'organization_name' => $customer_info['organization_name'] ?? null,
                        'password' => bcrypt($customer_info['pinfl']),
                        'user_status_id' => UserStatusEnum::ACTIVE,
                        'pinfl' => $customer_info['pinfl'],
                        'identification_number' => $customer_info['pinfl'],
                    ]);
                    UserRole::query()->create([
                        'user_id' => $user->id,
                        'role_id' => UserRoleEnum::BUYURTMACHI->value,
                    ]);
                }
            }
            DB::beginTransaction();
            try {
                $user_type = preg_match('/^\d{14}$/', $user->pinfl) ? 'J' : 'Y';
                $region_id = Region::query()->where('id', $lab_claim['region_id'])->first()->id;
                $inspection_director_id = Inspection::query()->where('region_id', $region_id)->first()->id;
                $district_id = District::query()->where('id', $lab_claim['district_id'])->first()->id;
                $user_reg_id = Auth::user()->region_id;
                $lab_testing_org_id = LabTestingOrg::query()->where('region_id', $user_reg_id)->first()->id;
                $saved_lab_claim = LabClaim::query()->create(
                    [
                        'object_name' => $lab_claim['object_name'],
                        'construction_works' => $lab_claim['construction_works'],
                        'sphere_id' => $lab_claim['sphere_id'],
                        'lab_test_category_id' => $lab_claim['lab_test_category_id'],
                        'lab_testing_org_id' => $lab_testing_org_id,
                        'object_type_id' => $lab_claim['object_type_id'],
                        'region_id' => $region_id,
                        'type' => 2,
                        'region_soato' => $lab_claim['region_id'],
                        'district_id' => $district_id,
                        'district_soato' => $lab_claim['district_id'],
                        'address' => $lab_claim['address'],
                        'reestr_num' => !empty($lab_claim['reestr_num']) ? $lab_claim['reestr_num'] : null,
                        'phone' => !empty($lab_claim['phone']) ? $lab_claim['phone'] : null,
                        'cad_num' => !empty($lab_claim['cad_num']) ? $lab_claim['cad_num'] : null,
                        'project_estimate_file_path' => $paths,
                        'inspection_director_id' => $inspection_director_id,
                        'claim_type' => $user_type,
                        'director_full_name' => !empty($lab_claim['director_full_name']) ? $lab_claim['director_full_name'] : null,
                        'manually_created' => true,
                    ]
                );
                LabClaimUser::query()->create([
                    'user_id' => $user->id,
                    'role_id' => UserRoleEnum::BUYURTMACHI->value,
                    'lab_claim_id' => $saved_lab_claim->id
                ]);

                LabTestCategory::query()->where('id', $saved_lab_claim['lab_test_category_id'])->update([
                    'used' => true
                ]);
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
            LabClaimUser::query()->create([
                'lab_claim_id' => $saved_lab_claim->id,
                'user_id' => $validated['laborant_id'],
                'role_id' => UserRoleEnum::LABORANT->value,
            ]);
            $saved_lab_claim->update(['status' => LabClaim::STATUS_IN_LAB]);
            return $this->sendingSuccess($saved_lab_claim, 'Ariza yaratildi!');
        }
        return $this->sendingError('Bunday sinov turini tanlash mumkin emas!', 400);

    }

    public function saveManuallyLabContract($claim_id)
    {
        $lab_claim = LabClaim::query()->find($claim_id);
        if ($lab_claim->status !== LabClaim::STATUS_IN_LAB_CONTRACT_ACTION) return $this->sendingError('Bu arizada bunday amal bajarish mumkin emas!', 403);
        $request = request()->only('signed_contract');
        $contract = LabContract::query()->where('lab_claim_id', $claim_id)->first();
        $date_dir = now()->format('Y-m-d');
        $path = $request['signed_contract']->store("lab-contracts-manually/$date_dir", 'public');
        $contract->update([
            'signed_con_path' => $path,
            'status' => LabClaim::STATUS_COMPLETED
        ]);
        $lab_claim->update(['status' => LabClaim::STATUS_COMPLETED]);
        return $this->sendingSuccess($contract, 'Shartnoma saqlandi!');

    }

    public function getLabUsers()
    {
        $filters = request()->only(['name']);
        return $this->sendingSuccess(LaborantUserListResource::collection($this->labService->getLabUsers($filters)), 'Ro\'yxat shakllandi!');
    }

    public function createTest(LabTestCategoryRequest $req)
    {
        $req->validated();
        $parent_id = request()->input('parent_id');
        $name = request()->input('name');
        $description = request()->input('description');
        $amount = request()->input('amount');
        $unity = request()->input('unity');
        $test = $this->labService->createTest(
            parent_id: $parent_id,
            name: $name,
            description: $description,
            amount: $amount,
            unity: $unity
        );

        if (!$test) {
            return $this->sendingError('Serverda xatolik!', 500);
        }
        return $this->sendingSuccess($test, 'Test yaratildi!');

    }

    public function createClaim(LabRequest $labRequest)
    {
        $data = $labRequest->validated();
        if ((int)$data['lab_test_category_id'] === 1 or (int)$data['lab_test_category_id'] === 2 or (int)$data['lab_test_category_id'] === 120) {
            $paths = [];
            if (request()->hasFile('project_estimate_file_path')) {
                foreach (request()->file('project_estimate_file_path') as $file) {
                    $dateDir = now()->format('Y-m-d');
                    $paths[] = $file->store("project_estimates/$dateDir", 'public');
                }
            }
            [$user, $user_role] = $this->getAuthData();
            $info = $this->labService->createClaim(data: $data, path_file: $paths, user: $user, user_role: (int)$user_role);
            return $this->sendingSuccess($info, 'Ariza yaratildi!');
        }
        return $this->sendingError('Bunday sinov turini tanlash mumkin emas!', 400);
    }

    public function getLabClaim($id)
    {
        $lab_claim = $this->labService->getLabClaim(id: $id);
        if (!$lab_claim['success']) {
            return $this->sendingError($lab_claim['message'], $lab_claim['code']);
        }
        return $this->sendingSuccess(new LabClaimResource($lab_claim['data']), 'Ariza topildi!');
    }

    public function getOrganizations()
    {
        return response()->json([
            'success' => true,
            'result' => [
                'data' => $this->labService->getOrganizations(request()->only(['soato', 'region_id'])),
            ]
        ]);
    }

    public function getTestCategories()
    {
        [$user, $user_role] = $this->getAuthData();
        $parent_id = request()->input('parent_id');
        $tests = $this->labService->getTestCategories(user: $user, parent_id: $parent_id);
        if (!$tests['success']) {
            return $this->sendingError($tests['message'], $tests['code']);
        }
        return $this->sendingSuccess($tests['data'], 'Sinovlar ro\'yxati qaytarildi!');
    }

    public function labClaimStatistics()
    {
        $labs = $this->labService->getClaims();
        if (!$labs['success']) {
            return $this->sendingError($labs['message'], $labs['code']);
        }
        return $this->sendingSuccess(new LabStatisticsResource($labs['data']->get()),
            'Arizalar ro\'yxati shakllandi!');
    }

    public function updateLabClaim($id)
    {
        $laborant_id = request()->input('laborant_id');
        $type = request()->filled('type') ? (int)request()->input('type') : null;
        $comment = request('comment') ?? null;
        $contract_num = request()->input('contract_num');
        $lab_claim = $this->labService->findClaim(id: $id);
        [$user, $user_role] = $this->getAuthData();
        if (!$lab_claim) {
            return $this->sendingError('Sinov turi topilmadi', 404);
        }
        if ($type === LabClaim::TYPE_DECLINE) {
            $rejected_info = $this->labService->rejectClaim(lab_claim: $lab_claim, user: $user, user_role: (int)$user_role, comment: $comment);
            return $this->sendingSuccess($rejected_info, 'Ariza qaytarildi!');
        }


        $info = $this->labService
            ->claimLabAction(lab_claim: $lab_claim, laborant: $laborant_id, contract_num: $contract_num);
        if (!$info) {
            return $this->sendingError('Serverda xatolik!', 500);
        }
        return $this->sendingSuccess($info, 'Ariza yuborildi!');

    }

    public function setPrice(LabTestPriceRequest $request)
    {
        $req = $request->validated();
        [$user, $user_role] = $this->getAuthData();
        $price = $req['price'];
        $id = (int)$req['id'];
        $old_price_id = request()->input('old_price_id');

        $price = $this->labService->setPrice(price: $price, id: $id, user: $user, user_role: (int)$user_role, old_price_id: $old_price_id);
        if (!$price['success']) {
            return $this->sendingError($price['message'], $price['code']);
        }
        return $this->sendingSuccess($price, 'Sinov turi narxi belgilandi!');

    }

    public function editTestCategory(LabTestEditRequest $request)
    {
        $req = $request->validated();
        $id = (int)$req['id'];
        $name = $req['name'];
        $description = request()->input('description');
        $unity = request()->input('unity');
        $amount = request()->input('amount');
        $test = $this->labService->editTest(id: $id, name: $name, description: $description, amount: $amount, unity: $unity);
        if (!$test['success']) return $this->sendingError($test['message'], $test['code']);
        return $this->sendingSuccess($test, 'Amal muvaffaqiyatli bajarildi!');
    }

    public function deleteTestCategory()
    {
        $id = request()->input('id');
        $test = $this->labService->deleteTestCategory(id: $id);
        if (!$test['success']) return $this->sendingError($test['message'], $test['code']);
        return $this->sendingSuccess($test, 'Sinov o\'chirildi!');

    }

    public function customerContractAction()
    {
        $type = request()->input('type') ? (int)request()->input('type') : null;
        $id = (int)request()->input('id');
        $lab_claim = $this->labService->findClaim(id: $id);
        if (!$lab_claim) {
            return $this->sendingError('Ariza topilmadi', 404);
        }
        $contract = $this->labService->findContract(id: $id);
        if (!$contract) {
            return $this->sendingError('Ariza bo\'yicha shartnoma topilmadi', 404);
        }

        $contract = match ($type) {
            LabClaim::TYPE_DECLINE => $this->labService->customerContractAction(lab_claim: $lab_claim, contract: $contract, status: LabClaim::STATUS_CANCELLED),
            LabClaim::TYPE_SEND => $this->labService->customerContractAction(lab_claim: $lab_claim, contract: $contract, status: LabClaim::STATUS_COMPLETED)
        };
        if (!$contract) {
            return $this->sendingError('Serverda xatolik!', 500);
        }

        return $this->sendingSuccess($contract, 'Ariza va shartnoma bo\'yicha amal muvaffaqiyatli bajarildi!');


    }

    public function getTestWithPrice($id)
    {
        [$user, $user_role] = $this->getAuthData();
        return $this->sendingSuccess($this->labService->getTestWithPrice(id: $id, user: $user), 'Narxli test sinovlari!');
    }

    public function getLabContract($id)
    {
        return $this->sendingSuccess($this->labService->getLabContract(id: $id), 'Shartnoma topildi!');
    }

    public function getEksInfo()
    {
        return $this->labService->getEksInfo(request('reestr_num'));
    }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function attachLaborantToObject(AttachLaborantRequest $request)
    {
        $req = $request->validated();
        $id = (int)$req['object_id'];
        $laborant_id = (int)$req['laborant_id'];
        $info = $this->labService->attachLaborantToObject($id, $laborant_id);
        if (!$info['success']) {
            return $this->sendingError($info['message'], $info['code']);
        }
        return $this->sendingSuccess($info['data'], 'Ariza yuborildi!');
    }

    public function checkListAnswer(LabCheckListRequest $request)
    {
        $req = $request->validated();
        $info = $this->labService->checkListAnswer(data: $req);
        if ($info === null) {
            return $this->sendingError('Yuklashda xatolik yuzberdi!', 400);
        }
        return $this->sendingSuccess([], 'Yuklandi!');
    }

    public function probationCreate(LabProbationRequest $request)
    {

    }


    public function checkListAnswersInfo($id)
    {
        $history_id = $id;
        $info = $this->labService->getUserAnswerInfo($history_id);
        return LabCheckListInfoResouce::make($info);
    }

    public function checkListAnswersNotification()
    {
        [$user, $user_role] = $this->getAuthData();
        return $this->sendingSuccess(data: $this->labService->checkListAnswersNotification($user, $user_role), message: 'Yuborildi!');
    }

    public function labCheckListHistories(LabCheckListHistoryRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(LabCheckListInfoResouce::collection($this->labService->labCheckListHistories($validated)), 'Yuborildi!');
    }

    public function labCheckListTestingTypes()
    {
        return $this->sendingSuccess($this->labCheckListService->getLabCheckListTestingTypes(), 'Yuborildi!');
    }

    public function saveMainInfo(LabCheckListMainInfoRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->saveMainInfo($validated), message: 'Muvaffaqiyatli saqlandi!');
    }

    public function saveTestingConInfo(LabCheckListConInfoRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->saveConInfo(data: $validated), message: 'Muvaffaqiyatli saqlandi!');
    }

    public function getQuestionMarks(LabCheckListGetQuestionMarksRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->getQuestionMarks(filter: $validated), message: 'Yuborildi!');
    }

    public function getBlockTests(LabGetBlockTestsRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->getBlockTests(filter: $validated), message: 'Yuborildi!');
    }

    public function saveStatement(LabCheckListStatementRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->saveLabCheckListStatement(data: $validated), message: 'Muvaffaqiyatli saqlandi!');
    }

    public function saveFirstChosenTestType(LabCheckListFirstTestingTypeRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->saveFirstChosenTestType(data: $validated), message: 'Muvaffaqiyatli saqlandi!');
    }

    public function saveQuestionConditions(LabCheckListConditionRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->saveQuestionConditions(data: $validated), message: 'Muvaffaqiyatli saqlandi!');
    }

    public function getFirstChosenTestingType(LabCheckListFirstTestingTypeRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->getFirstChosenTestType(filter: $validated), message: 'Yuborildi!');
    }

    public function getTestsConditions(LabCheckListTestingFilterRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->getTestsConditions(filter: $validated), message: 'Yuborildi!');
    }

    public function getStatement($id)
    {
        return $this->sendingSuccess(data: $this->labCheckListService->getStatement(id: $id), message: 'Yuborildi!');
    }

    public function getWorkTypeAverageMarks(LabCheckListTestingFilterRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->getSchmidtReadyTests(filter: $validated), message: 'Yuborildi!');
    }

    public function getSchmidtReadyTests(LabCheckListTestingFilterRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->getSchmidtReadyTests(filter: $validated), message: 'Yuborildi!');
    }

    public function saveStatementMarks(labCheckListStatementMarkRequest $request)
    {
        $validated = $request->validated();
        return $this->sendingSuccess(data: $this->labCheckListService->saveStatementMark(data: $validated), message: 'Muvaffaqiyatli saqlandi!');
    }

    public function getSchemePdf($main_info_id)
    {
        return $this->labCheckListService->getSchemePdf($main_info_id);
    }

    public function answerRegLabel()
    {
        $data = request()->only(['status', 'lab_check_list_chosen_testing_type_id']);
        $first_chosen_type = LabCheckListChosenTestingType::query()->findOrFail($data['lab_check_list_chosen_testing_type_id']);
        $user = Auth::user();
        return $first_chosen_type->update([
            'actions_status' => $data['status'] == 1 ? LabCheckListTestingStatusEnum::STATEMENT_ACCEPTED->value : LabCheckListTestingStatusEnum::STATEMENT_REJECTED->value,
            "reg_lab_answered" => $data['status'] == 1 ? 1 : 2,
            "reg_lab_answered_at" => Carbon::now(),
            "reg_lab_id" => $user->id
        ]);
    }

    public function getStatementPdf($statement_id)
    {
//        $main_info = $this->labCheckListRepository->getMainInfoById($main_info_id);
//        $object = $main_info->load('belongedObject')->belongedObject;
//        $work_type_question = $main_info->load('workTypeQuestion')->workTypeQuestion;
//        $questions = $main_info->load(['testingQuestion', 'testingQuestion.marks', 'testingQuestion.marks.marksChild'])->testingQuestion;
        $name = "";
        $statement = $this->labCheckListService->getStatement($statement_id);
        $first_chosen = LabCheckListChosenTestingType::query()->findOrFail($statement->lab_check_list_chosen_testing_type_id);
        $object = Article::query()->findOrFail($statement->article_id);
        $lab = User::query()->findOrFail($first_chosen->lab_id);
        $customer = ArticleUser::query()->where('article_id', $object->id)
            ->where('role_id', UserRoleEnum::BUYURTMACHI->value)
            ->with('user')
            ->first()->user;
        $statement_marks = LabCheckListTestingStatementMark::query()->where('lab_check_list_statement_id', $statement->id)->get();
        $ready_schmidts = $this->labCheckListService->getSchmidtReadyTests(filter: ['statement_id' => $statement->id]);
        $average_marks = $ready_schmidts['average_marks'];
        $num = $average_marks->count();
        $avarage_concrete = 0;
        foreach ($statement_marks as $key => $statement_mark) {
            if ($first_chosen->question_type === 'В') {
                $avarage_concrete = ($avarage_concrete + $statement_mark->concrete_sustainability) / ($key + 1);
            } else {
                $avarage_concrete = ($avarage_concrete + $statement_mark->second_num) / ($key + 1);
            }
        }

        $name = "bayonnoma-" . $statement->id;

        $region_inspection = Inspection::query()->where('region_id', $object->region_id)->first();
        if ($first_chosen->reg_lab_answered) {
            $domain = config('app.url') . '/api/get-statement-pdf/' . $statement->id;
            $qr_image = base64_encode(QrCode::format('png')->size(200)->generate($domain));
            $reg_lab = User::query()->findOrFail($first_chosen->lab_id);
            $pdf = Pdf::loadView('lab-pdf.bayonnoma', compact('reg_lab', 'qr_image', 'reg_lab', 'avarage_concrete', 'statement_marks', 'average_marks', 'lab', 'statement', 'object', 'region_inspection', 'customer', 'first_chosen', 'num'));
        } else {
            $pdf = Pdf::loadView('lab-pdf.bayonnoma', compact('lab', 'avarage_concrete', 'statement_marks', 'average_marks', 'lab', 'statement', 'object', 'region_inspection', 'customer', 'first_chosen', 'num'));
        }
        $dompdf = $pdf->getDomPDF();

        if ($first_chosen->reg_lab_answered) {
            $day = Carbon::parse($first_chosen->reg_lab_answered_at)->format('d');
            $month = Carbon::parse($first_chosen->reg_lab_answered_at)->format('m');
            if ($month == '01') {
                $month = 'yanvar';
            } elseif ($month == '02') {
                $month = 'fevral';
            } elseif ($month == '03') {
                $month = 'mart';
            } elseif ($month == '04') {
                $month = 'aprel';
            } elseif ($month == '05') {
                $month = 'may';
            } elseif ($month == '06') {
                $month = 'iyun';
            } elseif ($month == '07') {
                $month = 'iyul';
            } elseif ($month == '08') {
                $month = 'avgust';
            } elseif ($month == '09') {
                $month = 'sentyabr';
            } elseif ($month == '10') {
                $month = 'oktyabr';
            } elseif ($month == '11') {
                $month = 'noyabr';
            } elseif ($month == '12') {
                $month = 'dekabr';
            }
            $year = Carbon::parse($first_chosen->reg_lab_answered_at)->format('Y');
            /** 👇 SHART */
            $dompdf->render();

            $canvas = $dompdf->get_canvas();
            $fontMetrics = $dompdf->getFontMetrics();
//        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $width = $canvas->get_width();
            $height = $canvas->get_height();


            $height = $canvas->get_height();
            $text = "«{$day}» {$month} {$year} yildagi {$statement->document_number}-sonli sinov bayonnomasining {PAGE_NUM}-beti.";
            $fontSize = 8;

            $pageWidth = $canvas->get_width();
            $textWidth = $fontMetrics->getTextWidth($text, null, $fontSize);

// 👇 ENDI TO‘LIQ O‘NGGA
            $x = $pageWidth - $textWidth;
            $y = $canvas->get_height() - 55;

            $canvas->page_text($x, $y, $text, null, $fontSize, [0, 0, 0]);
//        $canvas->page_text(
//            $x,                 // X
//            $height - 50,
//            $text,
//            null,
//            8,
//            [0,0,0]
//        );
            $text2 = "Sinov natijalari faqat buyurtmachi tomonidan taqdim etilgan namunalarga va sinovdan o'tkazilgan ob'ektlarga nisbatan qo'llaniladi.";
            $fontSize = 8;

            $pageWidth = $canvas->get_width();
            $textWidth = $fontMetrics->getTextWidth($text2, null, $fontSize);

// 👇 ENDI TO‘LIQ O‘NGGA
            $x = $pageWidth - $textWidth - 50;
            $y = $canvas->get_height() - 40;

            $canvas->page_text($x, $y, $text2, null, $fontSize, [0, 0, 0]);

            $text3 = "Sinov bayyonnomasini laboratoriya bo’limi ruxsatisiz to'liq yoki qisman o’zgartirib tarqatish taqiqlanadi.";
            $fontSize = 8;

            $pageWidth = $canvas->get_width();
            $textWidth = $fontMetrics->getTextWidth($text3, null, $fontSize);

// 👇 ENDI TO‘LIQ O‘NGGA
            $x = $pageWidth - $textWidth - 50;
            $y = $canvas->get_height() - 25;

            $canvas->page_text($x, $y, $text3, null, $fontSize, [0, 0, 0]);
//        $pdf->output();
        }
        return $pdf->stream("$name.pdf");
    }

    public function getStatements(LabCheckListTestingFilterRequest $request)
    {
        $validated = $request->validated();
        $statements = $this->labCheckListService->getStatementsByFilters(filters: $validated);
        return $this->sendingSuccess(data: ['statements_id' => $statements], message: 'Yuborildi!');
    }


    public function getObjectInfo(LabCheckListTestingFilterRequest $request)
    {
        $validated = $request->validated();
        $object = Article::query()->findOrFail($validated['article_id']);
        $user = ArticleUser::query()->where('article_id', $object->id)
            ->where('role_id', UserRoleEnum::LABORANT->value)
            ->with('user')
            ->first()->user;
        $customer = ArticleUser::query()->where('article_id', $object->id)
            ->where('role_id', UserRoleEnum::BUYURTMACHI->value)
            ->with('user')
            ->first()->user;
        $data = [
            'object_name' => $object->name,
            'object_task_id' => $object->task_id,
            'customer_info' => [
                'full_name' => $customer->surname . ' ' . $customer->name . ' ' . $customer->middle_name,
                'phone' => $customer->phone,
            ]
        ];
        return $this->sendingSuccess(data: $data, message: 'Yuborildi!');
    }

    public function allObjectsList(LabObjectsGetRequest $request)
    {
        $filter = $request->validated();
        $objects = Article::query()->when(!empty($filter['task_id']), function ($query) use ($filter) {
            $query->where(function ($q) use ($filter) {
                $q->where('task_id', $filter['task_id'])
                    ->orWhere('id', $filter['task_id']);
            });
        })->when(!empty($filter['region_id']), function ($query) use ($filter) {
            $query->where('region_id', $filter['region_id']);
        })->when(!empty($filter['district_id']), function ($query) use ($filter) {
            $query->where('district_id', $filter['district_id']);
        })
            ->paginate(!empty($filters['per_page']) ? $filters['per_page'] : 10);
        return $this->sendingSuccessWithPagination(data: $objects, message: 'Muvaffaqiyatli olingan!');
    }

    public function attachObject(LabAttachObjectRequest $request)
    {
        $validated = $request->validated();
        [$user, $user_role] = $this->getAuthData();
        $object = Article::query()->where('id', $validated['article_id'])->first();
        $lab_contract = LabContract::query()->where('lab_claim_id', $validated['lab_claim_id'])->first();
        $lab_claim = LabClaim::query()->where('id', $validated['lab_claim_id'])->first();
        try {
            $articleUser = ArticleUser::query()->firstOrCreate(['article_id' => $validated['article_id'], 'user_id' => $user->id, 'role_id' => $user_role]);
            $object->update(['lab_contract_id' => $lab_contract->id]);
            $lab_claim->update(['object_id' => $object->id, 'attached_to_object' => true]);
        } catch (\Throwable $e) {
            report($e);

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            return $this->sendingError(message: $e->getMessage(), code: $status);
        }

        return $this->sendingSuccess(data: $lab_claim, message: 'Muvaffaqiyatli saqlandi!');
    }

    public function createLabGradirovka($object_id, $block_id, LabGradirofkaRequest $request)
    {
        $validated = $request->validated();
        $validated['object_id'] = $object_id;
        $validated['block_id'] = $block_id;
        $data = $validated;
        DB::transaction(function () use ($validated, $data) {
            $main_info = $this->saveGradMainInfo($validated);
            foreach ($data['scheme_info'] as $key => $info) {
                $info['lab_gradirovka_main_info_id'] = $main_info->id;
                LabGradirovkaInfo::query()->create($info);
            };
            $data['coefficient']['lab_gradirovka_main_info_id'] = $main_info->id;
            LabGradirovkaCoefficient::query()->create($data['coefficient']);
        });
        return $this->sendingSuccess(data: [], message: 'Muvaffaqiyatli saqlandi!');
    }

    public function passLabTest($main_grad_id, PassLabRequest $request)
    {
        $validated = $request->validated();
        try {
            foreach ($validated['pass_test_info'] as $key => $test_id) {
                DB::transaction(function () use ($test_id, $main_grad_id) {
                    LabTestActionInfo::query()->create($test_id);
                });
            }
        } catch (\Exception $exception) {
            return $this->sendingError($exception->getMessage(), 400);
        }
        return $this->sendingSuccess(data: [], message: 'Muvaffaqiyatli saqlandi!');
    }

    public function getLabGradInfo($object_id, $block_id, GetLabGradRequest $request)
    {
        $validated = $request->validated();
        $lab_grad_main_info = LabGradirovkaMainInfo::query()
            ->where(function (Builder $query) use ($block_id, $object_id, $validated) {
                $query
                    ->where('object_id', $object_id)
                    ->where('block_id', $block_id)
                    ->where('work_type_id', $validated['work_type_id'])
                    ->when(!empty(request()->question_id), function ($query) use ($validated) {
                        $query->where('question_id', $validated['question_id']);
                    });
            })->with(['info', 'coefficient', 'testActionInfo'])
            ->get();
        return $this->sendingSuccess(data: ['lab_grad_info' => $lab_grad_main_info], message: 'Yuborildi!');
    }

    public function getLabMeasurements()
    {
        return $this->sendingSuccess(data: ['lab_measurements' => LabMeasurementInstrument::query()->where('status', true)->get()], message: 'Yuborildi!');
    }

    public function crateProtocol($lab_grad_id, LabProtocolRequest $request)
    {
        $validated = $request->validated();
        $validated['lab_gradirovka_main_info_id'] = $lab_grad_id;
        try {
            $info = DB::transaction(function () use ($validated, $lab_grad_id) {
                $saving_main_info = $this->saveMainInfoProtocol($validated);
                foreach ($validated['items'] as $key => $item_info) {
                    $measurements = $item_info['measurements'];
                    unset($item_info['measurements']);
                    $item_info = $this->saveProtocolItemsInfo($saving_main_info->id, $item_info);
                    foreach ($measurements as $measurement) {
                        $measurement['lab_protocol_construction_id'] = $item_info->id;
                        $this->saveProtocolMeasurements($measurement);
                    }
                };
                $main_grad = LabGradirovkaMainInfo::query()->find($lab_grad_id);
                $main_grad->update(['status' => 1]);
                return $main_grad;
            });
            return $this->sendingSuccess($info, message: 'Ma\'lumotlar muvaffaqiyatli saqlandi!');
        } catch (\Exception $exception) {
            return $this->sendingError($exception->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->sendingError($e->getMessage(), 400);
        }
    }

    private function saveMainInfoProtocol(array $data)
    {
        unset($data['items'], $data['scheme']);
        return LabTestingProtocolMain::query()->create($data);
    }

    private function saveProtocolItemsInfo(int $prot_main_info_id, array $data)
    {
        $data['lab_protocol_id'] = $prot_main_info_id;
        return LabProtocolConstruction::query()->create($data);
    }

    private function saveProtocolMeasurements(array $data)
    {
        return LabProtocolConstructionIndex::query()->create($data);
    }

    public function downloadProtocol($lab_grad_id)
    {
        $main_grad = LabGradirovkaMainInfo::query()->find($lab_grad_id);
        if (!$main_grad) {
            return $this->sendingError('Sinov topilmadi!', 404);
        }

        $protocol = LabTestingProtocolMain::query()
            ->where('lab_gradirovka_main_info_id', $lab_grad_id)
            ->with('constructions.measurements')
            ->first();
        if (!$protocol) {
            return $this->sendingError('Sinov bayonnomasi ma\'lumotlari topilmadi!', 404);
        }

        $object = Article::query()->find($main_grad->object_id);
        $customer = ArticleUser::query()
            ->where('article_id', $main_grad->object_id)
            ->where('role_id', UserRoleEnum::BUYURTMACHI->value)
            ->with('user')
            ->first()?->user;
        $customerName = $customer
            ? ($customer->organization_name ?: trim("{$customer->surname} {$customer->name} {$customer->middle_name}"))
            : null;

        $labContract = LabContract::query()->find($main_grad->lab_contract_id);
        $labClaim = $labContract
            ? LabClaim::withoutGlobalScope(LabClaimUserScope::class)->find($labContract->lab_claim_id)
            : null;
        $workType = WorkType::query()->find($main_grad->work_type_id);

        $data = [
            'object_name' => $object->name ?? null,
            'customer_name' => $customerName,
            'customer_inn' => $customer?->identification_number ?: $customer?->pinfl,
            'customer_phone' => $customer?->phone,
            'application_number' => $labClaim->reestr_num ?? null,
            'work_type_name' => $workType->name ?? null,
            'avg_vf' => $protocol->avg_vf,
            'design_class' => $protocol->design_class,
            'homogeneity' => $protocol->homogeneity ?? [],
            'test_place' => $protocol->test_place,
            'deviations' => $protocol->deviations,
            'temperature' => $protocol->temperature,
            'humidity' => $protocol->humidity,
            'note' => $protocol->note,
            'equipment' => $protocol->equipment ?? [],
            'items' => $protocol->constructions->map(fn ($construction) => [
                'construction_name' => $construction->construction_name,
                'location' => $construction->location,
                'concrete_age' => $construction->concrete_age,
                'rm' => $construction->rm,
                'vf' => $construction->vf,
                'kt_kc' => $construction->kt_kc,
                'meets_norm' => $construction->meets_norm,
                'measurements' => $construction->measurements->map(fn ($m) => [
                    'place' => $m->place,
                    'h' => $m->h,
                    'r' => $m->r,
                    'mark' => $m->mark,
                ])->all(),
            ])->all(),
        ];

        $scheme = $main_grad->test_scheme_name === 'G' ? LabProtocolExport::SCHEME_G : LabProtocolExport::SCHEME_V;
        $fileName = "sinov-bayonnoma-{$lab_grad_id}-{$scheme}.xlsx";

        return Excel::download(new LabProtocolExport($scheme, $data), $fileName);
    }

    private function saveGradMainInfo($data)
    {
        unset($data['coefficient'], $data['scheme_info']);
        $main_info = LabGradirovkaMainInfo::query()->create($data);
        return $main_info;
    }

    public function sendingError($message, $code)
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }

    public function sendingSuccess($data, $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'result' => [
                'data' => $data
            ]
        ]);
    }

    public function sendingSuccessWithPagination($data, $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'result' => [
                'data' => $data->items(),
            ],
            'meta' => [
                'currentPage' => $data->currentPage(),
                'perPage' => $data->perPage(),
                'lastPage' => $data->lastPage(),
                'total' => $data->total(),
            ],
        ]);
    }

}
