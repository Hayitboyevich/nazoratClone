<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeclarationConclusionResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user ?
                [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'surname' => $this->user->surname,
                ] : null,
            'role' => $this->role ?
                [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                ] : null,
            'order_number' => $this->order_number,
            'order_date' => $this->order_date,
            'contract_number' => $this->contract_number,
            'contract_date' => $this->contract_date,
            'comment' => $this->comment,
        ];
    }
}
