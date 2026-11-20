<?php

namespace App\Repositories\Interfaces;

interface ClaimGovRepositoryInterface
{

    public function all($user, $roleId);

    public function filters($query, $roleId, $filters);

    public function findById($id);

    public function update($id, $data);

    public function createMonitoring($claimGov, $organizations);

    public function createOrganizationReview($claimId, $objectId, $monitoringId, $organizationId, $deadline, $sphereId);

    public function create($data, $expiryDate);

    public function organizationConclusion($id);

    public function updateConclusion($data, $user,  $id, $status, $pkcs7);

    public function updateResponseStatus($taskId, $status);

    public function findByTaskId($taskId);

}
