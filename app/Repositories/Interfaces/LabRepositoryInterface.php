<?php

namespace App\Repositories\Interfaces;

use App\Models\LabChecklistAnswer;
use App\Models\User;

interface LabRepositoryInterface
{
    public function createClaim(array $data, array $path_file, $user, $user_role);

    public function getLabClaim($id);

    public function getLabClaims($filters);

    public function getLabUsers($filters);

    public function labProcess($lab_claim, array $data, string $message, $status);

    public function accountantProcess($lab_claim, string $message, $status, $contract_num);

    public function inspectionDirectorProcess($lab_claim, string $message, $status);

    public function contractAction($lab_claim);

    public function editContract($lab_claim);

    public function findClaim(int $id);

    public function rejectClaim($lab_claim, $user, $user_role, $status, $comment);

    public function customerContractAction($lab_claim, $contract, $status);

    public function getLabContract($id, array $months_uz);

    public function findContract(int $id);

    public function createParentTest($name, $description);

    public function createTestChild($parent_id, $name, $description, $amount, $unity);

    public function getTestCategories($parent_id, $user);

    public function setPrice($price, int $id, $user, $user_role);

    public function deleteOldPrice($old_price_id);

    public function editTest($id, $name, $description, $amount, $unity);

    public function deleteTestCategory($id);

    public function getTestWithPrice(int $id, $user);

    public function attachLaborantToObject($object, $laborant_id);

    public function getObject($id);

    public function checkListAnswer($data, $item, $user, $check_list);

    public function getCheckList($id);

    public function getUserAnswerInfo($id);

    public function getOrganizations($soato);

    public function updateCheckList(LabChecklistAnswer $check_list, array $data);

    public function getUserObjectsByRole($user_id,$role_id);

    public function getNotAnsweredChecklists($column, $ids);

    public function getLabCheckListAnswer($data);

    public function getLabChecklistInfo($lab_check_lists);

    public function getRegLabObjects(User $user);

}
