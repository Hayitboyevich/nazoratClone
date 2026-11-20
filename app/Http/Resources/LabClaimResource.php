<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabClaimResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (!empty($this->contract)) {
            $contract = $this->contract;
            if (!empty($contract->complication))
            $complication_price = $contract->complication->total_with_qqs_price?? 0;
            else $complication_price = 0;
            $contract['total_price'] = $contract->con_total_price_with_nds?? 0  + $complication_price;
        }
        return [
            'id' => $this->id,
            'claim_type' => $this->claim_type,
            'type' => $this->type,
            'object_name' => $this->object_name,
            'construction_works' => $this->construction_works,
            'sphere' => $this->sphere,
            'lab_test_category' => $this->labTestCategory,
            'lab_testing_org_id' => $this->lab_testing_org_id,
            'object_type' => $this->objectType,
            'region' => $this->region,
            'district' => $this->district,
            'address' => $this->address,
            'reestr_num' => $this->reestr_num,
            'phone' => $this->phone,
            'cad_num' => $this->cad_num,
//            'status' => $this->status,
            'users' => LabUserResource::collection($this->labClaimUsers),
            'contract' => !empty($this->contract) ? $contract : null,
//            'project_estimate_file_path' => $this->project_estimate_file_path,
            'project_estimate_file_urls' => $this->project_estimate_file_urls,
            'status' => $this->status,
            'created_at' => $this->created_at,
//            'updated_at' => $this->updated_at,
            'answer' => $this->actions->first() ?? null,
            'object_id' => $this->object_id,
            'attached_to_object' => $this->attached_to_object
        ];
    }
}
