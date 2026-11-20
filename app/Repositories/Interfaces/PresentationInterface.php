<?php

namespace App\Repositories\Interfaces;

interface PresentationInterface
{
    public function all($filters, $user, $roleId);

    public function create($data);

    public function change($id, $user, $roleId);
    public function reject($id, $user, $roleId);

    public function update($id, $data);

    public function findById($id);
    public function search($query, $filters);
}
