<?php

namespace App\Services;

use App\Enums\CheckListStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\GeneralJsonException;
use App\Models\CheckListAnswer;
use App\Models\LabClaim;
use App\Models\LabTestCategory;
use App\Repositories\Interfaces\LabRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LabService
{
    private LabRepositoryInterface $labRepository;

    public function __construct(LabRepositoryInterface $labRepository)
    {
        $this->labRepository = $labRepository;
    }

    public function getLabClaim($id)
    {
        $lab_claim = $this->labRepository->getLabClaim(id: $id);
        if (!$lab_claim) {
            return [
                'success' => false,
                'message' => 'Ariza topilmadi!',
                'code' => 404
            ];
        }
        return $this->sendSuccess($lab_claim);
    }

    public function getOrganizations($soato)
    {
        return $this->labRepository->getOrganizations($soato);
    }

    public function createClaim($data, array $path_file, $user, $user_role)
    {
        return $this->labRepository->createClaim(data: $data, path_file: $path_file, user: $user, user_role: $user_role);
    }

    public function getEksInfo($reeestr_num)
    {
        $client = new Client();
        $apiCredentials = config('app.ekspertiza_info.login') . ':' . config('app.ekspertiza_info.password');
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($apiCredentials)
        ];
        $url = config('app.ekspertiza_info.url') . "?id=" . $reeestr_num . "&type=final_id";
        try {
            $response = $client->request('POST', $url, [
                'headers' => $headers
            ])->getBody()->getContents();
            return json_decode($response, true);
        } catch (GuzzleException $exception) {
            return null;
        }
    }

    public function getClaims($filters = null)
    {
        return $this->sendSuccess($this->labRepository->getLabClaims($filters));
    }


    public function claimLabAction($lab_claim, $laborant, $contract_num)
    {
        return match ($lab_claim->status) {
            LabClaim::STATUS_NEW => $this->labRepository->labProcess($lab_claim, [
                'lab_claim_id' => $lab_claim->id,
                'user_id' => $laborant,
                'role_id' => UserRoleEnum::LABORANT->value,
            ], 'Ariza laborantga yuborildi!', LabClaim::STATUS_IN_LAB),
            LabClaim::STATUS_IN_LAB => $this->labRepository->contractAction($lab_claim),
            LabClaim::STATUS_RETURNED => $this->labRepository->editContract($lab_claim),
            LabClaim::STATUS_IN_ACCOUNTANT => $this->labRepository->accountantProcess($lab_claim, 'Ariza yuborildi!', LabClaim::STATUS_IN_INSPEKSIYA, $contract_num),
            LabClaim::STATUS_IN_INSPEKSIYA => $this->labRepository->inspectionDirectorProcess($lab_claim, 'Ariza yuborildi!', LabClaim::STATUS_IN_CUSTOMER),
            default => false
        };
    }

    public function customerContractAction($lab_claim, $contract, $status)
    {
        return $this->labRepository->customerContractAction($lab_claim, $contract, $status);
    }

    public function getLabContract($id)
    {
        $months_uz = [
            'January' => 'yanvar',
            'February' => 'fevral',
            'March' => 'mart',
            'April' => 'aprel',
            'May' => 'may',
            'June' => 'iyun',
            'July' => 'iyul',
            'August' => 'avgust',
            'September' => 'sentyabr',
            'October' => 'oktyabr',
            'November' => 'noyabr',
            'December' => 'dekabr',
        ];

        return $this->labRepository->getLabContract($id, $months_uz);
    }

    public function findClaim($id)
    {
        $lab_claim = $this->labRepository->findClaim(id: $id);
        if (!$lab_claim) {
            return null;
        }
        return $lab_claim;
    }

    public function findContract($id)
    {
        $contract = $this->labRepository->findContract(id: $id);
        if (!$contract) {
            return null;
        }
        return $contract;
    }

    public function rejectClaim($lab_claim, $user, $user_role, $comment)
    {
        $status = match ($lab_claim->status) {
            LabClaim::STATUS_NEW, LabClaim::STATUS_IN_LAB => LabClaim::STATUS_REJECTED,
            LabClaim::STATUS_IN_CUSTOMER => LabClaim::STATUS_CANCELLED,
            LabClaim::STATUS_IN_INSPEKSIYA, LabClaim::STATUS_IN_ACCOUNTANT => LabClaim::STATUS_RETURNED
        };
        return $this->labRepository->rejectClaim($lab_claim, $user, $user_role, $status, $comment);
    }

    public function createTest($parent_id, $name, $description, $amount, $unity)
    {
        if (!$parent_id) {
            $test = $this->labRepository->createParentTest(name: $name, description: $description);
            if (!$test) {
                return $this->serverError();
            }
            return $test;
        }
        $test_child = $this->labRepository->createTestChild(parent_id: $parent_id, name: $name, description: $description, amount: $amount, unity: $unity);
        if (!$test_child) {
            return $this->serverError();
        }
        return $this->sendSuccess($test_child);
    }

    public function getTestCategories($user, $parent_id = null)
    {
        $test = $this->labRepository->getTestCategories(parent_id: $parent_id, user: $user);

        if (!$test->count()) {
            return $this->notFound();
        }
        return $this->sendSuccess($test);
    }

    public function setPrice($price, int $id, $user, $user_role, $old_price_id = null)
    {
        $test = LabTestCategory::query()->find($id);
        if (!$test) {
            return $this->notFound();
        }

        $descendant_ids = $test->getAllDescendantsIds();
        if (!$descendant_ids->isEmpty()) {
            return $this->permissionError();
        }

        if ($old_price_id) $this->labRepository->deleteOldPrice($old_price_id);
        $price = $this->labRepository->setPrice($price, $id, $user, $user_role);
        if (!$price) {
            return $this->serverError();
        }
        return $this->sendSuccess($price);

    }

    public function editTest(int $id, $name, $description, $amount, $unity)
    {
        $test = $this->labRepository->editTest(id: $id, name: $name, description: $description, amount: $amount, unity: $unity);
        if (!$test) return $this->serverError();
        return $this->sendSuccess($test);
    }

    public function deleteTestCategory($id)
    {
        $category = LabTestCategory::query()->find($id);
        if (!$category) {
            return $this->notFound();
        }
        if ($category->used) {
            throw new GeneralJsonException('This test was used!');
        }
        $info = $this->labRepository->deleteTestCategory($id);
        if (!$info) {
            return $this->serverError();
        }
        return $this->sendSuccess([]);
    }

    public function getTestWithPrice(int $id, $user)
    {
        return $this->labRepository->getTestWithPrice(id: $id, user: $user);
    }

    public function attachLaborantToObject($id, $laborant_id)
    {
        $object = $this->labRepository->getObject(id: $id);
        if ($object->lab_contract_id === null) {
            return [
                'success' => false,
                'message' => 'Bu obyekt bo\'yicha shartnoma mavjud emas!',
                'code' => 421
            ];
        }
//        if ($object->laborant_attached) return [
//            'success' => false,
//            'message' => 'Bu obyektda laborant mavjud!',
//            'code' => 421
//        ];

        return ['success' => true, 'data' => $this->labRepository->attachLaborantToObject($object, $laborant_id)];
    }

    public function checkListAnswer($data)
    {
        foreach ($data['regular_checklist'] as $item) {
            try {
                $user = Auth::user();
                DB::transaction(function () use ($data, $item, $user) {
                    $lab_check_list = null;
                    if (!empty($item['checklist_id'])) $lab_check_list = $this->labRepository->getCheckList($item['checklist_id']);
                    $lab_check_list = $this->labRepository->checkListAnswer($data, $item, $user, $lab_check_list);
                    $lab_check_list->refresh();
                    $this->updateCheckList($lab_check_list, $item['user_status']);
                });
            } catch (\Exception $ex) {
                report($ex);
                return null;
            }
        }
        return true;
    }

    public function updateCheckList($check_list, $user_status)
    {
        $user = Auth::user();
        if ((int)$check_list->reg_lab_answered === 2 and (int)$check_list->laborant_answered === 2) {
            $this->labRepository->updateCheckList($check_list, ['status' => CheckListStatusEnum::RAISED]);
        }

        if ((int)$user->getRoleFromToken() === UserRoleEnum::LABORANT->value) {
            if (((int)$check_list->reg_lab_answered === 3 and (int)$check_list->laborant_answered === 1)
                or ((int)$check_list->reg_lab_answered === 3 and (int)$check_list->laborant_answered === 2)) {
                $this->labRepository->updateCheckList($check_list, [
                    'reg_lab_answered' => null,
                    'reg_lab_answered_at' => null
                ]);
            }
        }
    }

    public function getLabUsers($filters = null)
    {
        return $this->labRepository->getLabUsers($filters);
    }

    public function checkListAnswersNotification($user, $role_id)
    {
        $ids = match ((int)$role_id) {
            UserRoleEnum::LABORANT->value => $this->labRepository->getUserObjectsByRole($user->id, $role_id)->pluck('article_id'),
            UserRoleEnum::REG_LAB->value => $this->labRepository->getRegLabObjects($user)->pluck('id'),
        };

        $column = match ((int)$role_id) {
            UserRoleEnum::LABORANT->value => 'laborant_answered',
            UserRoleEnum::REG_LAB->value => 'reg_lab_answered',
        };

        return $this->labRepository->getNotAnsweredChecklists($column, $ids);
    }

    public function getUserAnswerInfo($id)
    {
        return $this->labRepository->getUserAnswerInfo($id);
    }

    public function labCheckListHistories($data)
    {
        $lab_check_lists = $this->labRepository->getLabCheckListAnswer($data);
        return $this->labRepository->getLabChecklistInfo($lab_check_lists);
    }

    public function serverError()
    {
        return [
            'success' => false,
            'message' => 'Serverda xatolik!',
            'code' => 500
        ];
    }

    public function permissionError()
    {
        return [
            'success' => false,
            'message' => 'Sizda xuquq mavjud emas!',
            'code' => 403
        ];
    }

    public function notFound()
    {
        return [
            'success' => false,
            'message' => 'Ma\'lumotlar topilmadi!',
            'code' => 404
        ];
    }

    public function sendSuccess($data)
    {
        return ['success' => true, 'data' => $data];
    }

}
