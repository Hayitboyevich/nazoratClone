<?php

namespace App\Repositories\Interfaces;

interface QualityControlInterface
{
    public function all($roleId);

    public function create($data);

    public function findById($id);

    public function update($id, $data);

    public function filter($query, $filters);
}
