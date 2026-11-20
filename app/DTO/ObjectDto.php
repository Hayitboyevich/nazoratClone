<?php

namespace App\DTO;

class ObjectDto
{
    public ?int $responseId;
    public ?int $fundingSourceId;
    public ?int $objectSectorId;

    public  ?string $pkcs7;

    public ?string $uuid;
    public ?string $guid;
    public ?string $table;
    public function setResponseId(?int $responseId): self
    {
        $this->responseId = $responseId;
        return $this;
    }

    public function setPkcs7(?string $pkcs7): self
    {
        $this->pkcs7 = $pkcs7;
        return $this;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function setGuid(?string $guid): self
    {
        $this->guid = $guid;
        return $this;
    }

    public function setTableName(?string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function setFundingSourceId(?int $fundingSourceId): self
    {
        $this->fundingSourceId = $fundingSourceId;
        return $this;
    }

    public function setObjectSectorId(?int $objectSectorId): self
    {
        $this->objectSectorId = $objectSectorId;
        return $this;
    }
}
