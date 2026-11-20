<?php

namespace App\Repositories\Interfaces;

interface ConcreteWorkInterface
{
    public function all();

    public function filters($query, $filters);

    public function create($data);

    public function findById($id);
}
