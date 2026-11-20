<?php

namespace App\Repositories;

use App\Enums\UserRoleEnum;
use App\Helpers\PresentationStatus;
use App\Models\Article;
use App\Models\Presentation;
use App\Models\Question;
use App\Repositories\Interfaces\PresentationInterface;

class PresentationRepository implements PresentationInterface
{
    public function __construct(protected Presentation $model)
    {
    }

    public function findById($id)
    {
        return $this->model->query()->find($id);
    }

    public function all($filters, $user, $roleId)
    {
        $query = $this->model->query();
        switch ($roleId) {
            case UserRoleEnum::ICHKI->value:
                return  $query->whereHas('object.users', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->where('role_id', UserRoleEnum::ICHKI->value);
                });
            case UserRoleEnum::TEXNIK->value:
                 return  $query->whereHas('object.users', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->where('role_id', UserRoleEnum::TEXNIK->value);
                });
            case UserRoleEnum::INSPECTOR->value:
                return  $query->whereHas('object.users', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->where('role_id', UserRoleEnum::INSPECTOR->value);
                });
            case UserRoleEnum::INSPEKSIYA->value:
            case UserRoleEnum::INSPEKSIYA_XODIMI->value:
            case UserRoleEnum::HUDUDIY_KUZATUVCHI->value:
            case UserRoleEnum::QURILISH_MONTAJ->value:
            case UserRoleEnum::KOMPLAYNS->value:
               return $query->whereHas('object', function($q) use ($user) {
                    $q->where('region_id', $user->region_id);
                });
            case UserRoleEnum::RESPUBLIKA_KUZATUVCHI->value:
            case UserRoleEnum::RESPUBLIKA_KOMPLAYNS->value:
                return $query;
            default:
                return $query->whereRaw('1 = 0');
        }
    }

    public function search($query, $filters)
    {
        return $query
            ->when(isset($filters['status']), function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            })
            ->when(isset($filters['task_id']), function ($q) use ($filters) {
                $q->where('number', $filters['task_id']);
            })
            ->when(isset($filters['region_id']), function ($q) use ($filters) {
                $q->whereHas('object', function($q) use ($filters) {
                    $q->where('region_id', $filters['region_id']);
                });
            })
            ->when(isset($filters['district_id']), function ($q) use ($filters) {
                $q->whereHas('object', function($q) use ($filters) {
                    $q->where('district_id', $filters['district_id']);
                });
            })
            ->when(isset($filters['funding_source']), function ($q) use ($filters) {
                $q->whereHas('object', function($q) use ($filters) {
                    $q->where('funding_source_id', $filters['funding_source']);
                });
            })
            ->when(isset($filters['object_type']), function ($q) use ($filters) {
                $q->whereHas('object', function($q) use ($filters) {
                    $q->where('object_type_id', $filters['object_type']);
                });
            })
            ->when(isset($filters['start_date']) || isset($filters['end_date']), function ($query) use ($filters) {
                $startDate = isset($filters['start_date']) ? $filters['start_date'] . ' 00:00:00' : null;
                $endDate = isset($filters['end_date']) ? $filters['end_date'] . ' 23:59:59' : null;

                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                } elseif ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                } elseif ($endDate) {
                    $query->where('created_at', '<=', $endDate);
                }
            })
            ->when(isset($filters['object_type']), function ($q) use ($filters) {
                $q->whereHas('object', function($q) use ($filters) {
                    $q->where('object_type_id', $filters['object_type']);
                });
            });
    }

    public function create($data)
    {
        return $this->model->query()->create($data);
    }

    public function change($id, $user, $roleId)
    {
        try {
            $model = $this->findById($id);
            $updates = [];
            switch ($roleId) {
                case UserRoleEnum::ICHKI->value:
                    $updates['internal_answer'] = 1;
                    $updates['internal_id'] = $user->id;
                    if ($model->technic_answer) {
                        if ($model->technic_answer == 1){
                            $updates['status'] = PresentationStatus::IN_EMPLOYEE;
                        }else{
                            $updates['status'] = PresentationStatus::IN_INSPECTOR;
                        }
                    }
                    break;

                case UserRoleEnum::TEXNIK->value:
                    $updates['technic_answer'] = 1;
                    $updates['technic_id'] = $user->id;
                    if ($model->internal_answer) {
                        if ($model->internal_answer == 1){
                            $updates['status'] = PresentationStatus::IN_EMPLOYEE;
                        }else{
                            $updates['status'] = PresentationStatus::IN_INSPECTOR;
                        }
                    }
                    break;

                case UserRoleEnum::INSPECTOR->value:
                    $updates['inspector_id'] = $user->id;
                    if ($model->internal_answer && $model->technic_answer)  {
                        $updates['status'] = PresentationStatus::IN_EMPLOYEE;
                    }
                    break;

                case UserRoleEnum::INSPEKSIYA_XODIMI->value:
                    $updates['status'] = PresentationStatus::IN_CMR;
                    break;

                case UserRoleEnum::INSPEKSIYA->value:
                    $updates['inspection_id'] = $user->id;
                    $updates['inspection_answer'] = 1;
                    $updates['status'] = PresentationStatus::SENDED;
                    break;
            }

            if (!empty($updates)) {
                $model->update($updates);
            }
            return $model;

        } catch (\Exception $exception) {
            throw $exception;
        }

    }

    public function reject($id, $user, $roleId)
    {
        try {
            $model = $this->findById($id);
            $updates = [];
            switch ($roleId) {
                case UserRoleEnum::ICHKI->value:
                    $updates['internal_answer'] = 2;
                    $updates['internal_id'] = $user->id;
                    if ($model->technic_answer) {
                        $updates['status'] = PresentationStatus::IN_INSPECTOR;
                    }
                    break;

                case UserRoleEnum::TEXNIK->value:
                    $updates['technic_answer'] = 2;
                    $updates['technic_id'] = $user->id;
                    if ($model->internal_answer) {
                        $updates['status'] = PresentationStatus::IN_INSPECTOR;
                    }
                    break;

                case UserRoleEnum::INSPECTOR->value:
                    $updates['inspector_id'] = $user->id;
                    if ($model->internal_answer && $model->technic_answer)  {
                        $updates['status'] = PresentationStatus::CANCELLED;
                    }
                    break;

                case UserRoleEnum::INSPEKSIYA->value:
                    $updates['inspection_id'] = $user->id;
                    $updates['inspection_answer'] = 2;
                    $updates['status'] = PresentationStatus::CANCELLED;
                    break;
            }

            if (!empty($updates)) {
                $model->update($updates);
            }
            return $model;
        }catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function update($id, $data)
    {
        $model = $this->findById($id);
        $model->update($data);
        return $model;
    }


}
