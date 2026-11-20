<?php

namespace App\Repositories;

use App\Models\QualityControl;
use App\Repositories\Interfaces\QualityControlInterface;

class QualityControlRepository implements QualityControlInterface
{
    public function __construct(protected QualityControl $model)
    {
    }

    public function all($roleId)
    {
        return $this->model;
    }

    public function filter($query, $filters)
    {
        return $query
            ->when(isset($filters['block_id']), function ($query) use ($filters) {
                 $query->where('block_id', $filters['block_id']);
            })
            ->where('object_id', $filters['object_id']);
    }

    public function create($data)
    {
        return $this->model->query()->create($data);
    }

    public function findById($id)
    {
        return $this->model->query()->findOrFail($id);
    }

    public function update($id, $data)
    {
        $control = $this->model->query()->findOrFail($id);
        $control->update($data);
        return $control;

    }
}
