<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeclarationListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $customerConfirmed = Carbon::parse($this->customer_confirmed_at);
        $deadline          = Carbon::parse($this->object->deadline);
        $objectCreated     = Carbon::parse($this->object->created_at);
        return [
            'id' => $this->id,
            'status' => $this->status,
            'fact_type' => $this->factType ? [
                'id' => $this->factType->id,
                'name' => $this->factType->name,
            ] : null,
            'fact_number' => $this->fact_number,
            'fact_date' => $this->fact_date,
            'construction_price' => $this->construction_price,
            'instrument_price' => $this->instrument_price,
            'fond_construction_price' => $this->fond_construction_price,
            'fond_instrument_price' => $this->fond_instrument_price,
            'note' => $this->note,
            'capacity' => $this->capacity,
            'unit' => $this->unit ? [
                'id' => $this->unit->id,
                'name' => $this->unit->name_uz
            ] : null,
            'general_project_info' => $this->general_project_info,
            'general_project_queue' => $this->general_project_queue,
            'process_project_info' => $this->process_project_info,
            'process_project_queue' => $this->process_project_queue,
            'product_production' => $this->product_production,
            'solution_desc' => $this->solution_desc,
            'labor_protection' => $this->labor_protection,
            'done_work' => $this->done_work,
            'done_note' => $this->done_note,
            'created_at' => $this->created_at,
            'garant_period' => $this->garant_period,
            'vendor_comment' => $this->vendor_comment,
            'vendor_full_name' => $this->vendor_full_name,
            'vendor_confirmed_at' => $this->vendor_confirmed_at,
            'blocks' => $this->blocks ? DeclarationBlockResource::collection($this->blocks) : null,
            'in_norm' => $objectCreated->diffInDays($deadline),
            'in_real' => $customerConfirmed->diffInDays($objectCreated),
            'start_date' => Carbon::parse($objectCreated)->format('d-m-Y'),
            'end_date' => Carbon::parse($customerConfirmed)->format('d-m-Y'),
        ];
    }
}
