<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserInfoResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'middle_name' => $this->middle_name,
            'company_name' => $this->company_name,
            'company_inn' => $this->company_inn,
            'pin' => $this->pin,
            'start_date' => $this->start_date,
            'position' => $this->position,
            'specialist_name' => $this->specialist_name,
            'certificate_number' => $this->certificate_number,
        ];
    }
}
