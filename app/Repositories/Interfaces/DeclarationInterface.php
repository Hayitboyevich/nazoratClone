<?php

namespace App\Repositories\Interfaces;

interface DeclarationInterface
{
    public function all();

    public function create(?array $data);

    public function findById(int $id);

    public function update($id, array $data);

    public function change($user, $roleId, $id, $status);

    public function concCreate(?array $data);

    public function getConc($user, $roleId,  $filters);
}
