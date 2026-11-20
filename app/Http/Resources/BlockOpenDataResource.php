<?php

namespace App\Http\Resources;

use App\Models\Role;
use App\Models\User;
use App\Services\MyGovService;
use App\Services\QuestionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockOpenDataResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $questionService = app(QuestionService::class);
        $works = $questionService->getQuestionList(
            blockId: $this->id,
            block_type: $this->block_mode_id
        );

        $workTypes = collect($works)
            ->reject(fn($item) => in_array($item['work_type_id'], [14]))
            ->flatMap(function ($item) {
                return collect($item['questions'])
                    ->groupBy('floor')
                    ->flatMap(function ($questions, $floor) use ($item) {
                        $name = $floor ? "$floor - qavat {$item['name']}" : $item['name'];
                        return $questions->contains(fn($q) => in_array($q['work_type_status']->value, [1, 2, 3]))
                            ? [[
                                'work_type_id' => $item['work_type_id'],
                                'name' => $name,
                                'status' => $questions->first()['work_type_status']->value
                            ]]
                            : [];
                    });
            })
            ->values()
            ->toArray();


        return [
            'id' => $this->id,
            'name' => $this->name,
            'mode' => BlockModeResource::make($this->mode),
            'type' => BlockTypeResource::make($this->type),
            'block_number' => $this->block_number,
            'floor' => $this->floor,
            'construction_area' => $this->construction_area,
            'count_apartments' => $this->count_apartments,
            'height' => $this->height,
            'length' => $this->length,
            'status' => $this->status,
            'accepted' => $this->accepted,
            'appearance_type' => AppearanceTypeResource::make($this->appearanceType),
            'selected_work_type' => $this->selected_work_type,
            'end_date' => $this->end_date,
            'task_id' => $this->task_id,
            'work_types' => $workTypes,
            'regulations' => $this->monitorings
                ? $this->monitorings->flatMap(function ($monitoring) {
                    return collect($monitoring->regulations)->map(function ($regulation) {
                        $regulationUser = $regulation->regulationUser;

                        $fromUser = $regulationUser ? User::find($regulationUser->from_user_id) : null;
                        $fromRole = $regulationUser ? Role::find($regulationUser->from_role_id) : null;
                        $responsibleUser = $regulationUser ? User::find($regulationUser->to_user_id) : null;
                        $responsibleRole = $regulationUser ? Role::find($regulationUser->to_role_id) : null;

                        return [
                            'regulation_id' => $regulation->id,
                            'regulation_number' => $regulation->regulation_number,
                            'regulation_status' => RegulationStatusResource::make($regulation->regulationStatus),
                            'from_user' => [
                                'role' => $fromRole ? RoleResource::make($fromRole) : null,
                                'phone' => $fromUser?->phone,
                                'fish' => $fromUser ? "{$fromUser->surname} {$fromUser->name} {$fromUser->middle_name}" : null,
                            ],
                            'responsible_user' => [
                                'role' => $responsibleRole ? RoleResource::make($responsibleRole) : null,
                                'phone' => $responsibleUser?->phone,
                                'fish' => $responsibleUser ? "{$responsibleUser->surname} {$responsibleUser->name} {$responsibleUser->middle_name}" : null,
                            ],
                            'violation_count' => $regulation->violations()->count(),
                            'created_at' => $regulation->created_at,
                            'files' => ImageResource::collection(
                                collect($regulation->regulationViolations ?? [])
                                    ->pluck('violation.images')
                                    ->flatten(1)
                                    ->filter()
                            ),

                        ];
                    });
                })
                : null,

        ];
    }
}
