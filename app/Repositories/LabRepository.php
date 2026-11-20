<?php

namespace App\Repositories;

use App\Enums\LogType;
use App\Enums\UserRoleEnum;
use App\Exceptions\GeneralJsonException;
use App\Exceptions\UnauthenticatedException;
use App\Models\Article;
use App\Models\ArticleUser;
use App\Models\District;
use App\Models\Inspection;
use App\Models\LabBlock;
use App\Models\LabChecklistAnswer;
use App\Models\LabCheckListInfo;
use App\Models\LabClaim;
use App\Models\LabClaimUser;
use App\Models\LabComplication;
use App\Models\LabContract;
use App\Models\LabContractInfo;
use App\Models\LabRekvizit;
use App\Models\LabTestCategory;
use App\Models\LabTestingOrg;
use App\Models\LabTestPrice;
use App\Models\LabUserActionHistory;
use App\Models\Region;
use App\Models\Rekvizit;
use App\Models\User;
use App\Repositories\Interfaces\LabRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LabRepository implements LabRepositoryInterface
{
    public function createClaim($data, $path_file, $user, $user_role)
    {
        DB::beginTransaction();
        try {
            $user_type = preg_match('/^\d{14}$/', $user->pinfl) ? 'J' : 'Y';
            $region_id = Region::query()->where('soato', $data['region_id'])->first()->id;
            $district_id = District::query()->where('soato', $data['district_id'])->first()->id;
            $lab_claim = LabClaim::query()->create(
                [
                    'object_name' => $data['object_name'],
                    'construction_works' => $data['construction_works'],
                    'sphere_id' => $data['sphere_id'],
                    'lab_test_category_id' => $data['lab_test_category_id'],
                    'lab_testing_org_id' => $data['lab_testing_org_id'],
                    'object_type_id' => $data['object_type_id'],
                    'region_id' => $region_id,
                    'region_soato' => $data['region_id'],
                    'district_id' => $district_id,
                    'district_soato' => $data['district_id'],
                    'address' => $data['address'],
                    'reestr_num' => !empty($data['reestr_num']) ? $data['reestr_num'] : null,
                    'phone' => !empty($data['phone']) ? $data['phone'] : null,
                    'cad_num' => !empty($data['cad_num']) ? $data['cad_num'] : null,
                    'project_estimate_file_path' => $path_file,
                    'claim_type' => $user_type,
                    'director_full_name' => !empty($data['director_full_name']) ? $data['director_full_name'] : null,
                ]
            );

            LabClaimUser::query()->create([
                'user_id' => $user->id,
                'role_id' => $user_role,
                'lab_claim_id' => $lab_claim->id
            ]);

            LabTestCategory::query()->where('id', $data['lab_test_category_id'])->update([
                'used' => true
            ]);

            if (!$this->saveUsersAction(user: Auth::user(), type: LabClaim::LAB_CLAIM_CREATED, lab_claim: $lab_claim, status: LabClaim::STATUS_NEW)) throw new GeneralJsonException('Something went wrong while saving user action!', 500);

            DB::commit();
            return $lab_claim;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getLabClaim($id)
    {
        $lab = LabClaim::query()->with(['region:id,name_uz', 'district:id,name_uz', 'labTestCategory:id,name_uz',
            'sphere:id,name_uz,name_ru',
            'objectType:id,name,description', 'labClaimUsers.user', 'labClaimUsers.role',
            'actions' => function ($q) {
                $q->where('action_status', LabClaim::STATUS_REJECTED);
            }
        ])
            ->where('id', $id)
            ->first();
        if ($lab and $lab->type === 2) {
            $lab->load(['contract.complication', 'contract.blocks.blockType', 'contract.blocks.contractInfo.testCategory.parents',
                'contract.blocks.contractInfo.workType', 'contract.blocks.contractInfo.question']);
        } else if ($lab and $lab->type === 1) {
            $lab->load('contract.contractInfo.testCategory.parents');
        }
        return !$lab ? $lab : $lab->makeHidden(['region_id', 'district_id', 'created_at', 'updated_at', 'deleted_at', 'lab_test_category_id']);
    }

    public function getLabClaims($filters)
    {
        return LabClaim::query()
            ->when(!empty($filters['region_id']), fn($q) => $q->where('region_id', $filters['region_id']))
            ->when(!empty($filters['district_id']), fn($q) => $q->where('district_id', $filters['district_id']))
            ->when(!empty($filters['lab_claim_id']), fn($q) => $q->where('id', $filters['lab_claim_id']))
            ->when(!empty($filters['test_type']), fn($q) => $q->where('lab_test_category_id', $filters['test_type']))
            ->when(!empty($filters['customer_name']), fn($q) => $q->where('object_name', 'ILIKE', "%{$filters['customer_name']}%"))
            ->when(!empty($filters['lab_id']), function ($q) use ($filters) {
                $q->whereHas('labClaimUsers', function ($q) use ($filters) {
                    $q->where(['user_id' => $filters['lab_id'], 'role_id' => UserRoleEnum::LABORANT->value]);
                });
            })
            ->orderBy('created_at', 'DESC');
    }

    /**
     * @throws Exception
     */
    public function labProcess($lab_claim, array $data, string $message, $status)
    {
        $user = Auth::user();
        if ((int)$user->getRoleFromToken() !== UserRoleEnum::REG_LAB->value) throw new UnauthenticatedException('User doesn\'t have permission!', 403);
        try {
            DB::transaction(function () use ($lab_claim, $data, $status, $user) {
                LabClaimUser::query()->create($data);
                $lab_claim->update(['status' => $status]);
                if (!$this->saveUsersAction($user, LabClaim::LAB_ACTION, $lab_claim, $status)) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
            });
            return $lab_claim->refresh();
        } catch (Exception $ex) {
            report($ex);
            return false;
        }
    }

    /**
     * @throws Exception
     */
    public function accountantProcess($lab_claim, $message, $status, $contract_num)
    {
        $user = Auth::user();
        if ((int)$user->getRoleFromToken() !== UserRoleEnum::BUXGALTER->value) throw new UnauthenticatedException('User doesn\'t have permission!', 403);
        DB::transaction(function () use ($lab_claim, $message, $status, $user, $contract_num) {
            $lab_claim->update(['status' => $status]);
            $contract = LabContract::query()->where(['lab_claim_id' => $lab_claim->id])
                ->update(['contract_number' => $contract_num, 'status' => LabClaim::STATUS_IN_INSPEKSIYA]);
            if (!$this->saveUsersAction($user, LabClaim::LAB_ACTION, $lab_claim, $status)) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
        });
        return $lab_claim->refresh();
    }

    /**
     * @throws Exception
     */
    public function inspectionDirectorProcess($lab_claim, string $message, $status)
    {
        $user = Auth::user();
        if ((int)$user->getRoleFromToken() !== UserRoleEnum::INSPEKSIYA->value) throw new UnauthenticatedException('User doesn\'t have permission!', 403);
        try {
            DB::transaction(function () use ($lab_claim, $message, $status, $user) {
                LabContract::query()->where(['lab_claim_id' => $lab_claim->id])->update([
                    'inspeksiya_confirmed_at' => Carbon::now(),
                    'inspection_director_id' => $user->id,
                ]);
                $lab_claim->update(['status' => $status]);
                if (!$this->saveUsersAction($user, LabClaim::LAB_ACTION, $lab_claim, $status)) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
            });
            return $lab_claim->refresh();
        } catch (Exception $ex) {
            report($ex);
            return false;
        }
    }

    public function contractAction($lab_claim)
    {
        if ((int)Auth::user()->getRoleFromToken() !== UserRoleEnum::LABORANT->value) throw new UnauthenticatedException('User doesn\'t have permission!', 403);
        $contract_infos = (array)request()->input('contract_info', []);
        $complication = (array)request()->input('complication', []);
        $con_total_price = request()->input('con_total_price');
        $con_total_nds = request()->input('con_total_nds');
        $con_total_price_with_nds = request()->input('con_total_price_with_nds');
            try {
                DB::transaction(function () use ($lab_claim, $contract_infos, $con_total_price, $con_total_nds, $complication, $con_total_price_with_nds) {
                    $status = $lab_claim->manually_created ? LabClaim::STATUS_IN_LAB_CONTRACT_ACTION : LabClaim::STATUS_IN_ACCOUNTANT;
                    $lab_con = LabContract::query()->create([
                        'lab_claim_id' => $lab_claim->id,
                        'con_total_price' => $con_total_price ?? null,
                        'con_total_nds' => $con_total_nds ?? null,
                        'con_total_price_with_nds' => $con_total_price_with_nds ?? null,
                        'status' => $status
                    ]);
                    if (!empty($complication)){
                        LabComplication::query()->create([
                            'lab_claim_id' => $lab_claim->id,
                            'lab_contract_id' => $lab_con->id,
                            'lab_test_category_id' => $lab_claim->lab_test_category_id,
                            'complication_category_id' => $complication['complication_category_id'],
                            'building_size' => $complication['building_size'],
                            'test_type_price' => $complication['test_type_price'],
                            'coefficient' => $complication['coefficient'] ?? null,
                            'price' => $complication['price'],
                            'kv_coefficient' => $complication['kv_coefficient'] ?? null,
                            'extra_coefficient' => $complication['extra_coefficient'] ?? null,
                            'total_price' => $complication['total_price'],
                            'qqs_price' => $complication['qqs_price'],
                            'total_with_qqs_price' => $complication['total_with_qqs_price'],
                        ]);
                    }

                    if (!empty($contract_infos)){
                        foreach ($contract_infos as $contract_info) {
                            $block = LabBlock::query()->create([
                                "block_name" => $contract_info['block_name'],
                                "floor" => $contract_info['floor'],
                                "lab_contract_id" => $lab_con->id,
                                "block_mode_id" => $contract_info['block_type_id'],
                            ]);
                            foreach ($contract_info['contract_info'] as $contract_info_item) {
                                $contract_info_item['lab_block_id'] = $block->id;
                                $contract_info_item['lab_contract_id'] = $lab_con->id;
                                LabContractInfo::query()->create($contract_info_item);
                            }
                        }
                    }

                    $lab_claim->update([
                        'status' => $status
                    ]);
                    if (!$this->saveUsersAction(Auth::user(), LabClaim::LAB_ACTION, $lab_claim, $status)) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
                });
                return $lab_claim->refresh();

            } catch (Exception $e) {
                report($e);
                return null;
            }
    }

    public function findClaim(int $id): ?LabClaim
    {
        return LabClaim::find($id);
    }

    public function rejectClaim($lab_claim, $user, $user_role, $status, $comment)
    {
        try {
            DB::transaction(function () use ($lab_claim, $user, $user_role, $status) {
                $lab_claim->update(['status' => $status]);
            });
            if ($status === LabClaim::STATUS_RETURNED) {
                LabContract::query()->where('lab_claim_id', $lab_claim->id)->update([
                    'status' => $status
                ]);
            }
            if (!$this->saveUsersAction($user, LabClaim::LAB_ACTION, $lab_claim, $status, $comment)) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
            return $lab_claim->refresh();
        } catch (Exception $e) {
            report($e);
            return null;
        }
    }

    public function customerContractAction($lab_claim, $contract, $status)
    {
        try {
            return DB::transaction(function () use ($lab_claim, $contract, $status) {
                $lab_claim->update([
                    'status' => $status
                ]);
                $contract->update(['status' => $status, 'customer_confirmed_at' => Carbon::now()]);
                if (!$this->saveUsersAction(Auth::user(), LabClaim::LAB_ACTION, $lab_claim, $status)) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
                $contract->refresh();
                return $lab_claim->refresh();
            });
        } catch (Exception $ex) {
            report($ex);
            return null;
        }
    }

    public function findContract($id)
    {
        return LabContract::query()->where('lab_claim_id', $id)->first();
    }

    /**
     * @throws Exception
     */
    public function createParentTest($name, $description)
    {
        try {
            return DB::transaction(function () use ($name, $description) {
                $lab_test_category = LabTestCategory::query()->create([
                    'name_uz' => $name,
                    'name_ru' => $name,
                    'description_uz' => $description,
                    'description_ru' => $description
                ]);
                return $lab_test_category;
            });
        } catch (Exception $ex) {
            report($ex);
            return null;
        }
    }

    public function createTestChild($parent_id, $name, $description, $amount, $unity)
    {
        try {
            return DB::transaction(function () use ($parent_id, $name, $description, $amount, $unity) {
                $lab_test_category = LabTestCategory::query()->create([
                    'name_uz' => $name,
                    'name_ru' => $name,
                    'description_uz' => $description,
                    'description_ru' => $description,
                    'parent_id' => $parent_id,
                    'amount' => $amount,
                    'unity' => $unity
                ]);
                return $lab_test_category->refresh();
            });
        } catch (Exception $ex) {
            report($ex);
            return null;
        }
    }

    public function getTestCategories($parent_id, $user)
    {
        return LabTestCategory::tree($parent_id, $user->region_id);
    }

    public function setPrice($price, int $id, $user, $user_role)
    {
        try {
            return DB::transaction(function () use ($price, $id, $user, $user_role) {
                $price = LabTestPrice::query()->create([
                    'test_id' => $id,
                    'price' => $price,
                    'region_id' => $user->region_id
                ]);
                return $price;
            });
        } catch (Exception $ex) {
            report($ex);
            return null;
        }
    }

    public function deleteOldPrice($old_price_id)
    {
        LabTestPrice::query()->find($old_price_id)->delete();
    }

    public function editTest($id, $name, $description, $amount, $unity)
    {
        try {
            return DB::transaction(function () use ($id, $name, $description, $amount, $unity) {
                $lab_test_category = LabTestCategory::query()->find($id);
                $lab_test_category->update([
                    'name_uz' => $name,
                    'name_ru' => $name,
                    'description_uz' => $description,
                    'description_ru' => $description,
                    'amount' => $amount,
                    'unity' => $unity
                ]);
                return $lab_test_category->refresh();
            });
        } catch (Exception $ex) {
            report($ex);
            return null;
        }
    }

    public function getTestWithPrice(int $id, $user)
    {
        return LabTestCategory::query()
            ->where('parent_id', $id)
            ->join('lab_test_prices as ltp', function ($join) use ($user) {
                $join->on('ltp.test_id', '=', 'lab_test_categories.id')
                    ->where('ltp.region_id', '=', $user->region_id)
                    ->whereNull('ltp.deleted_at');
            })
            ->select([
                'lab_test_categories.id as id',
                'lab_test_categories.parent_id as parent_id',
                'lab_test_categories.name_uz as name_uz',
                'lab_test_categories.description_uz as description_uz',
                'lab_test_categories.description_ru as description_ru',
                'lab_test_categories.amount as amount',
                'lab_test_categories.unity as unity',
                'ltp.id as price_id',
                'ltp.price as price',
                'ltp.region_id as region_id'
            ])
            ->get();
    }

    public function editContract($lab_claim)
    {
        $contract_infos = (array)request()->input('contract_info', []);
        $con_total_price = request()->input('con_total_price');
        $con_total_nds = request()->input('con_total_nds');
        $con_total_price_with_nds = request()->input('con_total_price_with_nds');
        $contract = LabContract::query()->where('lab_claim_id', $lab_claim->id)->first();
        if (!$contract) {
            throw new GeneralJsonException('Contract not found', 404);
        }
        try {
            DB::transaction(function () use ($lab_claim, $contract, $contract_infos, $con_total_price, $con_total_nds, $con_total_price_with_nds) {
                $contract->update([
                    'con_total_price' => $con_total_price,
                    'con_total_nds' => $con_total_nds,
                    'con_total_price_with_nds' => $con_total_price_with_nds,
                    'status' => LabClaim::STATUS_IN_ACCOUNTANT
                ]);
                if (!empty($contract_infos)) {
                    foreach ($contract_infos as $contract_info) {
                        if (!empty($contract_info['id'])){
                            LabBlock::query()->updateOrCreate(['id' => $contract_info['id']], [
                                'block_name' => $contract_info['block_name'],
                                'block_mode_id' => $contract_info['block_type_id'],
                                'floor' => $contract_info['floor'],
                            ]);
                            if (!empty($contract_info['contract_info'])) {
                                foreach ($contract_info['contract_info'] as $contract_info_item) {
                                    LabContractInfo::query()->updateOrCreate(['id' => $contract_info_item['id']], [
                                        'lab_test_category_id' => $contract_info['lab_test_category_id'],
                                        'amount' => $contract_info['amount'],
                                        'price' => $contract_info['price'],
                                        'total_price' => $contract_info['total_price'],
                                        'nds' => $contract_info['nds'],
                                        'total_price_with_nds' => $contract_info['total_price_with_nds'],
                                        'work_type_id' => $contract_info_item['work_type_id']
                                    ]);
                                }
                            }
                        }
                    }
                }
//                foreach ($contract_infos as $contract_info) {
//                    if (!empty($contract_info['id']))
//                        LabContractInfo::query()
//                            ->whereKey($contract_info['id'])
//                            ->update([
//                                'lab_test_category_id' => $contract_info['lab_test_category_id'],
//                                'amount' => $contract_info['amount'],
//                                'price' => $contract_info['price'],
//                                'total_price' => $contract_info['total_price'],
//                                'nds' => $contract_info['nds'],
//                                'total_price_with_nds' => $contract_info['total_price_with_nds']
//                            ]);
//                    else LabContractInfo::query()->create([
//                        'lab_contract_id' => $contract->id,
//                        'lab_test_category_id' => $contract_info['lab_test_category_id'],
//                        'amount' => $contract_info['amount'],
//                        'price' => $contract_info['price'],
//                        'total_price' => $contract_info['total_price'],
//                        'nds' => $contract_info['nds'],
//                        'total_price_with_nds' => $contract_info['total_price_with_nds']
//                    ]);

//                }
                $lab_claim->update([
                    'status' => LabClaim::STATUS_IN_ACCOUNTANT
                ]);
                if (!$this->saveUsersAction(Auth::user(), $lab_claim, LabClaim::STATUS_IN_ACCOUNTANT)) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
            });
            return $lab_claim->refresh();

        } catch (Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * @throws GeneralJsonException
     */
    public function getLabContract($id, $months_uz)
    {
        $lab_claim = LabClaim::query()->with([
            'region',
            'district',
            'labClaimUsers' => function ($q) {
                $q->where('role_id', UserRoleEnum::BUYURTMACHI->value)
                    ->with(['user.region', 'user.district']);
            }
        ])
            ->findOrFail($id);
        Carbon::setLocale('uz');
        if ($lab_claim->type === 1) {
            $contract = LabContract::query()->where('lab_claim_id', $lab_claim->id)
                ->whereIn('status', [LabClaim::STATUS_COMPLETED,  LabClaim::STATUS_IN_CUSTOMER, LabClaim::STATUS_IN_INSPEKSIYA, LabClaim::STATUS_IN_ACCOUNTANT, LabClaim::STATUS_IN_LAB_CONTRACT_ACTION])
                ->with(['contractInfo.testCategory.parent',
                    'contractInfo.testCategory.price'])->first();
        } else {
            $contract = LabContract::query()->where('lab_claim_id', $lab_claim->id)
                ->whereIn('status', [LabClaim::STATUS_COMPLETED,  LabClaim::STATUS_IN_CUSTOMER, LabClaim::STATUS_IN_INSPEKSIYA, LabClaim::STATUS_IN_ACCOUNTANT, LabClaim::STATUS_IN_LAB_CONTRACT_ACTION])
                ->with(['contractInfo.testCategory.parent',
                    'contractInfo.testCategory.price', 'contractInfo.workType', 'contractInfo.question', 'contractInfo.labBlock'])->first();
        }

        $contractInfo = $contract?->contractInfo()
            ->with('labBlock')
            ->get()
            ->groupBy(fn ($item) => $item?->labBlock?->block_name);

        $details = $contract?->contractInfo;

        if (!$contract) throw new GeneralJsonException('Lab contract not found!', 404);
        try {
            $inspeksiya_confirmed_at_par = $contract->inspeksiya_confirmed_at ? Carbon::parse($contract->inspeksiya_confirmed_at) : null;
            $customer_confirmed_at_par = $contract->customer_confirmed_at ? Carbon::parse($contract->customer_confirmed_at) : null;
            $month_name_ins = $inspeksiya_confirmed_at_par ? $months_uz[$inspeksiya_confirmed_at_par->format('F')] : null;
            $month_name_cus = $customer_confirmed_at_par ? $months_uz[$customer_confirmed_at_par->format('F')] : null;
            $inspeksiya_confirmed_at = $inspeksiya_confirmed_at_par ? $inspeksiya_confirmed_at_par->format('Y') . ' yil ' . $inspeksiya_confirmed_at_par->format('d') . ' ' . $month_name_ins : '';
            $customer_confirmed_at = $customer_confirmed_at_par ? $customer_confirmed_at_par->format('Y') . ' yil ' . $customer_confirmed_at_par->format('d') . ' ' . $month_name_cus : '';
            if (!$lab_claim->manually_created) {
                $inspection_director = User::withTrashed()->find($contract->inspection_director_id);
            } else {
//                $inspection_director = User::withTrashed()->find($lab_claim->inspection_director_id);
                $inspection_director = Inspection::query()->where('region_id', $lab_claim->region_id)->first();
            }
            $inspection_region = LabTestingOrg::query()->find($lab_claim->lab_testing_org_id);
            $region = Region::query()->find($inspection_region->region_id);
            $inspeksiya = Inspection::query()->where(['region_id' => $inspection_region->region_id])->first();
            $rekvizit = LabRekvizit::query()->where('district_id', $lab_claim->district_id)->first();
            $contract_total_price = $contract->con_total_price_with_nds ?? 0;
            $complication_total_price = $contract?->complication->total_with_qqs_price ?? 0;
            $contract_full_price = $contract_total_price + $complication_total_price;
            $pdf = Pdf::loadView('lab', compact('lab_claim', 'contract', 'customer_confirmed_at',
                'inspeksiya_confirmed_at', 'inspeksiya', 'details', 'region', 'rekvizit', 'inspection_director', 'contractInfo', 'contract_full_price'));
            $pdfOutput = $pdf->stream();
            return base64_encode($pdfOutput);
        } catch (Exception $exception) {
            report($exception);
            throw new GeneralJsonException('Something went wrong while saving user action!', 500);
        }
    }

    public function deleteTestCategory($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                LabTestCategory::query()->find($id)->delete();
                return true;
            });
        } catch (Exception $ex) {
            report($ex);
            return null;
        }
    }

    public function attachLaborantToObject($object, $laborant_id)
    {
        try {
            $art_user = ArticleUser::query()->where(['article_id' => $object->id, 'role_id' => UserRoleEnum::LABORANT->value])->first();
            DB::transaction(function () use ($object, $laborant_id, $art_user) {
                if ($art_user) {
                    $art_user->update([
                        'user_id' => $laborant_id
                    ]);
                    if (!$this->saveUsersAction(Auth::user(), type: "Article {$art_user->article_id} laborant " . $art_user->user_id . "to - " . $laborant_id, comment: "Laborant changed")) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
                } else {
                    ArticleUser::query()->updateOrCreate([
                        'article_id' => $object->id,
                        'user_id' => $laborant_id,
                        'role_id' => UserRoleEnum::LABORANT->value
                    ]);
                    $object->update([
                        'laborant_attached' => true
                    ]);
                    if (!$this->saveUsersAction(Auth::user(), type: "Article {$object->id} laborant " . $laborant_id, comment: "Laborant attached")) throw new GeneralJsonException('Something went wrong while saving user action!', 500);
                }
            });
            return $object->refresh();
        } catch (Exception $ex) {
            report($ex);
            return null;
        }
    }

    public function getObject($id)
    {
        return Article::query()->find($id);
    }

    public function checkListAnswer($data, $item, $user, $check_list = null)
    {
        if (empty($item['checklist_id'])) {
            $check_list = $this->createCheckListAnswer($data, $item);
        }
        $check_list = $this->getCheckList($check_list->id);
        match ((int)$user->getRoleFromToken()) {
            UserRoleEnum::LABORANT->value => $check_list->update([
                'laborant_answered' => $item['user_status'],
                'laborant_answered_at' => Carbon::now()
            ]),
            UserRoleEnum::REG_LAB->value => $check_list->update([
                'reg_lab_answered' => $item['user_status'],
                'reg_lab_answered_at' => Carbon::now()
            ])
        };
        $paths = [];
        if (!empty($item['files'])) {
            foreach ($item['files'] as $file) {
                $dateDir = now()->format('Y-m-d');
                $paths[] = $file->store("lab-check-list-answer/$dateDir", 'public');
            }
        }

        $history = $this->createHistory($user, $check_list, $item);

        if (!$this->saveUsersAction(Auth::user(), type: "Checklist action: ID - " . $check_list->id, status: $item['user_status'])) throw new GeneralJsonException('Something went wrong while saving user action!', 500);

        LabCheckListInfo::query()->create([
            'block_id' => $data['block_id'],
            'object_id' => $data['object_id'],
            'checklist_id' => $check_list->id,
            'history_id' => $history,
            'description' => !empty($item['description']) ? $item['description'] : null,
            'type' => $item['type'] ?? null,
            'files' => $paths,
            'role_id' => (int)$user->getRoleFromToken(),
            'user_id' => $user->id
        ]);

        return $check_list;
    }

    private function createCheckListAnswer($data, $item)
    {
        $object = Article::query()->findOrFail($data['object_id']);
        return DB::transaction(function () use ($object, $data, $item) {
            $checklist = LabChecklistAnswer::query()->create([
                'block_id' => $data['block_id'],
                'object_id' => $data['object_id'],
                'work_type_id' => $item['work_type_id'],
                'question_id' => $item['question_id'],
                'object_type_id' => $object->object_type_id,
                'floor' => $item["floor"],
                'status' => 1
            ]);
            return $checklist;
        });


    }

    public function createHistory($user, $check_list, $item)
    {
        $content = [
            'user' => $user->id ?? "",
            'role' => $user->getRoleFromToken(),
            'date' => now(),
            'status' => $check_list->status,
            'comment' => !empty($item['description']) ? $item['description'] : null,
            'additionalInfo' => ['user_answered' => $item['user_status'], 'answered' => '']
        ];
        return DB::table('check_list_histories')->insertGetId([
            'gu_id' => $check_list->id,
            'content' => json_encode($content),
            'type' => LogType::LAB_HISTORY,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }

    public function getOrganizations($soato)
    {
        $lab_org = LabTestingOrg::
//        where(!empty($soato['soato']) ? 'soato' : 'region_id', !empty($soato['soato']) ? $soato['soato'] : $soato['region_id'])
            when(!empty($soato['soato']), fn($q) => $q->where('soato', $soato['soato']))
            ->when(!empty($soato['region_id']), fn($q) => $q->where('soato', $soato['region_id']))
            ->with(['region:id,name_uz', 'district:id,name_uz'])
            ->orderBy('id')
            ->get();
        return $lab_org->makeHidden(['region_id', 'district_id', 'created_at', 'updated_at', 'deleted_at']);
    }

    public function getCheckList($id)
    {
        return LabChecklistAnswer::query()->find($id);
    }

    public function getUserAnswerInfo($id)
    {
        return LabCheckListInfo::query()->where('history_id', $id)->first();
    }

    public function updateCheckList(LabChecklistAnswer $check_list, $data)
    {
        return $check_list->update($data);
    }

    public function getLabUsers($filters = null)
    {
        return User::query()
            ->where('region_id', Auth::user()->region_id)
            ->whereHas('roles', function ($query) {
                $query->where('role_id', UserRoleEnum::LABORANT->value);
            })
            ->when(!empty($filters['name']), fn($q) => $q->where('name', 'ILIKE', '%' . $filters['name'] . '%'))
            ->with(['roles' => function ($query) {
                $query->where('role_id', UserRoleEnum::LABORANT->value);
            }, 'region'])->get();
    }

    public function saveUsersAction($user, $type, $lab_claim = null, $status = null, $comment = null)
    {
//        try {
//            DB::transaction(function () use ($user, $lab_claim, $status, $type, $comment) {
        LabUserActionHistory::query()->create([
            'full_name' => "$user->surname  $user->name $user->middle_name",
            'user_id' => $user->id,
            'lab_claim_id' => !empty($lab_claim) ? $lab_claim->id : $lab_claim,
            'role_id' => (int)$user->getRoleFromToken(),
            'action_status' => !empty((int)$status) ? (int)$status : null,
            'comment' => $comment ?? '',
            'type' => $type
        ]);
//            });
        return true;
//        } catch (Exception $ex) {
//            report($ex);
//            return false;
//        }
    }

    public function getUserObjectsByRole($user_id, $role_id)
    {
        return ArticleUser::query()->where(['user_id' => $user_id, 'role_id' => $role_id])->get();
    }

    public function getRegLabObjects(User $user)
    {
        return Article::query()
            ->whereNotNull('lab_contract_id')
            ->where('region_id', $user->region_id)
            ->get();
    }

    public function getNotAnsweredChecklists($column, $ids)
    {
        return LabChecklistAnswer::query()
            ->withAll()
            ->whereIn('object_id', $ids)
            ->where($column, null)->get();
    }

    public function getLabCheckListAnswer($data)
    {
        return LabChecklistAnswer::query()->where($data)->get();
    }

    public function getLabChecklistInfo($lab_check_lists)
    {
        return LabCheckListInfo::query()
            ->whereIn('checklist_id', $lab_check_lists->pluck('id'))
            ->with(['user:id,surname,name,middle_name', 'role:id,name'])
            ->orderBy('created_at')
            ->get();
    }
}
