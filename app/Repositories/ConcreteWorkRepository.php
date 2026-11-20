<?php

namespace App\Repositories;

use App\Models\ConcreteWork;
use App\Repositories\Interfaces\ConcreteWorkInterface;

class ConcreteWorkRepository implements ConcreteWorkInterface
{
    public function __construct(public ConcreteWork $model)
    {
    }

    public function all()
    {
       return $this->model;
    }

    public function create($data)
    {
        return $this->model->query()->create($data);
    }

    public function filters($query, $filters)
    {
        return $query
            ->where('object_id', $filters['object_id'])
            ->when($filters['block_id'] ?? null, function ($query, $filters) {
                $query->where('block_id', $filters['block_id']);
            });
    }

    public function findById($id)
    {
        return $this->model->query()->find($id);
    }

}
