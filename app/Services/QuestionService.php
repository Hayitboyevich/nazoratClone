<?php

namespace App\Services;

use App\DTO\QuestionDto;
use App\Enums\BlockModeEnum;
use App\Enums\CheckListStatusEnum;
use App\Enums\LogType;
use App\Enums\ObjectTypeEnum;
use App\Enums\RegulationStatusEnum;
use App\Enums\UserRoleEnum;
use App\Enums\WorkTypeStatusEnum;
use App\Exceptions\NotFoundException;
use App\Http\Resources\CheckListHistoryListResource;
use App\Http\Resources\CheckListHistoryResource;
use App\Http\Resources\FoundationResource;
use App\Models\ActViolation;
use App\Models\AiControl;
use App\Models\Article;
use App\Models\AuthorRegulation;
use App\Models\Block;
use App\Models\CheckListAnswer;
use App\Models\FoundationBlock;
use App\Models\LabChecklistAnswer;
use App\Models\Monitoring;
use App\Models\Question;
use App\Models\Regulation;
use App\Models\RegulationDemand;
use App\Models\RegulationUser;
use App\Models\RegulationViolation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Violation;
use App\Models\WorkType;
use App\Notifications\InspectorNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuestionService
{

    protected $user;
    public QuestionDto $dto;
    private HistoryService $historyService;

    public function __construct(
        protected Question       $questions,
        protected ArticleService $articleService,

    )
    {
        $this->user = Auth::guard('api')->user();
        $this->historyService = new HistoryService('check_list_histories');
    }

    public function getQuestionList($blockId = null, $type = null, $block_type = null)
    {
        try {
            $block = Block::find($blockId) ?? null;
            $object = null;
            if ($block != null) {
                $object = Article::find($block->article_id) ?? null;
                if ($object != null) {
                    $object = $object->lab_contract_id;
                }
            }


            $query = WorkType::query()->orderBy('id', 'ASC');
            if ($block_type == null)
                $query->where(['type' => 1]);
            elseif ($block_type == 3)
                $query->where(['type' => 3]);
            else
                $query->where('status', true);

            $workTypes = $query->forLaborant()->get();
            $generatedQuestions = [];
            $is_jwt = request()->bearerToken() !== null;
            if ($blockId != null) {
                $answers = CheckListAnswer::where('block_id', $blockId)
                    ->get()
                    ->groupBy(fn($answer) => $answer->work_type_id . '-' . $answer->question_id . '-' . $answer->floor)
                    ->map(fn($group) => $group->sortByDesc('created_at')->first());
            } else {
                $answers = CheckListAnswer::where('block_id', $blockId)
                    ->limit(10)
                    ->get()
                    ->groupBy(fn($answer) => $answer->work_type_id . '-' . $answer->question_id . '-' . $answer->floor)
                    ->map(fn($group) => $group->sortByDesc('created_at')->first());
            }
            if ($is_jwt && in_array((int)Auth::user()->getRoleFromToken(), [UserRoleEnum::LABORANT->value, UserRoleEnum::REG_LAB->value,
                    UserRoleEnum::RES_LAB->value])) {
                $answers = LabChecklistAnswer::query()->where('block_id', $blockId)
                    ->orderBy('created_at', 'ASC')
                    ->get()
                    ->groupBy(fn($answer) => $answer->work_type_id . '-' . $answer->question_id . '-' . $answer->floor);
//                ->map(fn($group) => $group->sortByDesc('created_at'));
            }
            foreach ($workTypes as $workType) {
                if ($is_jwt && in_array((int)Auth::user()->getRoleFromToken(), [UserRoleEnum::LABORANT->value, UserRoleEnum::REG_LAB->value,
                        UserRoleEnum::RES_LAB->value])) {
                    $questions = Question::query()
                        ->when($type, function ($query) use ($type) {
                            $query->where('type', $type);
                        })->when($block, function ($query) use ($block) {
                            if ($block->block_mode_id == BlockModeEnum::TARMOQ) {
                                $query->where('object_type_id', ObjectTypeEnum::LINEAR);
                            } else {
                                $query->where('object_type_id', ObjectTypeEnum::BUILDING);
                            }
                        })
                        ->where('work_type_id', $workType->id)
                        ->forLaborant()
                        ->get();
                } else {
                    $questions = Question::query()
                        ->when($type, function ($query) use ($type) {
                            $query->where('type', $type);
                        })->when($block, function ($query) use ($block) {
                            if ($block->block_mode_id == BlockModeEnum::TARMOQ) {
                                $query->where('object_type_id', ObjectTypeEnum::LINEAR);
                            } else {
                                $query->where('object_type_id', ObjectTypeEnum::BUILDING);
                            }
                        })
                        ->where('work_type_id', $workType->id)
                        ->where('is_only_lab', false)
                        ->get();
                }

                $questionArray = [];
                if ($workType->is_multiple_floor && isset($block) && $block->floor != null) {
                    for ($floor = 1; $floor <= $block->floor; $floor++) {
                        $workTypeStatus = $this->getStatusOfWorkType($this->filterAnswers($answers, $workType->id, floor: $floor));
                        foreach ($questions as $question) {
                            $answerKey = $workType->id . '-' . $question->id . '-' . $floor;

                            $answer = $answers->get($answerKey);
                            if ($is_jwt && in_array((int)Auth::user()->getRoleFromToken(), [UserRoleEnum::LABORANT->value, UserRoleEnum::REG_LAB->value,
                                    UserRoleEnum::RES_LAB->value])) {
                                $questionArray[] = [
                                    'question' => $question->name,
                                    'question_id' => $question->id,
                                    'work_type_status' => $workTypeStatus,
                                    'type' => $question->type,
                                    'is_laboratoriya' => $question->is_laboratoriya,
                                    'floor' => $floor ?? null,
                                    'answers' => $answer,
                                ];
                            } else {
                                $questionArray[] = [
                                    'question' => $question->name,
                                    'question_id' => $question->id,
                                    'type' => $question->type,
                                    'is_laboratoriya' => $question->is_laboratoriya,
                                    'floor' => $floor ?? null,
                                    'work_type_status' => $workTypeStatus,
                                    'active' => true,
                                    'status' => $answer ? $answer->status : 1,
                                    'checklist_id' => $answer ? $answer->id : null,
                                    'inspector_answered' => $answer ? $answer->inspector_answered : null,
                                    'technic_answered' => $answer ? $answer->technic_answered : null,
                                    'author_answered' => $answer ? $answer->author_answered : null,
                                    'inspector_deadline' => $answer ? $answer->inspector_answered_at : null,
                                    'technic_author_deadline' => $answer ? $answer->technic_author_answered_at : null,
                                    'laborant_answered' => $answer ? $answer->laborant_answered : null,
                                    'laborant_answered_at' => $answer ? $answer->laborant_answered_at : null,
                                    'reg_lab_answered' => $answer ? $answer->reg_lab_answered : null,
                                    'reg_lab_answered_at' => $answer ? $answer->reg_lab_answered_at : null,

                                ];
                            }
                        }
                    }
                } else {
                    $workTypeStatus = $this->getStatusOfWorkType($this->filterAnswers($answers, $workType->id));

                    foreach ($questions as $question) {
                        $answerKey = $workType->id . '-' . $question->id . '-';
                        $answer = $answers->get($answerKey);
                        if ($is_jwt && in_array((int)Auth::user()->getRoleFromToken(), [UserRoleEnum::LABORANT->value, UserRoleEnum::REG_LAB->value,
                                UserRoleEnum::RES_LAB->value])) {
                            $questionArray[] = [
                                'question' => $question->name,
                                'question_id' => $question->id,
                                'work_type_status' => $workTypeStatus,
                                'type' => $question->type,
                                'is_laboratoriya' => $question->is_laboratoriya,
                                'floor' => $floor ?? null,
                                'answers' => $answer,
                            ];
                        } else {
                            $questionArray[] = [
                                'question' => $question->name,
                                'question_id' => $question->id,
                                'is_laboratoriya' => $question->is_laboratoriya,
                                'type' => $question->type,
                                'floor' => null,
                                'status' => $answer ? $answer->status : 1,
                                'work_type_status' => $workTypeStatus,
                                'active' => true,
                                'checklist_id' => $answer ? $answer->id : null,
                                'inspector_answered' => $answer ? $answer->inspector_answered : null,
                                'technic_answered' => $answer ? $answer->technic_answered : null,
                                'author_answered' => $answer ? $answer->author_answered : null,
                                'inspector_deadline' => $answer ? $answer->inspector_answered_at : null,
                                'technic_author_deadline' => $answer ? $answer->technic_author_answered_at : null,
                                'laborant_answered' => $answer ? $answer->laborant_answered : null,
                                'laborant_answered_at' => $answer ? $answer->laborant_answered_at : null,
                                'reg_lab_answered' => $answer ? $answer->reg_lab_answered : null,
                                'reg_lab_answered_at' => $answer ? $answer->reg_lab_answered_at : null,

                            ];
                        }
                    }
                }

                if (!empty($questionArray))
                    $generatedQuestions[] = [
                        "work_type_id" => $workType->id,
                        "work_type_type" => $workType->type,
                        "name" => $workType->name,
                        "questions" => $questionArray,
                        "has_lab_contract" => (bool)$object
                    ];
            }

            return $generatedQuestions;
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    private function getStatusOfWorkType($answers): WorkTypeStatusEnum
    {
        $workTypeStatus = WorkTypeStatusEnum::NOT_STARTED;
        $is_jwt = request()->bearerToken() !== null;
        $countConfirmedQuestions = 0;
        if ($answers->count() > 0) {
            foreach ($answers as $item) {
                if ($is_jwt && in_array((int)Auth::user()->getRoleFromToken(), [UserRoleEnum::LABORANT->value, UserRoleEnum::REG_LAB->value,
                        UserRoleEnum::RES_LAB->value])) {
                    foreach ($item as $info) {
                        if (!empty($info->status) and ($info->status == CheckListStatusEnum::CONFIRMED))
                            $countConfirmedQuestions++;
                    }
                }
                if (!empty($item->status) and ($item->status == CheckListStatusEnum::CONFIRMED))
                    $countConfirmedQuestions++;
            }
            if ($countConfirmedQuestions == $answers->count())
                $workTypeStatus = WorkTypeStatusEnum::CONFIRMED;
            else
                $workTypeStatus = WorkTypeStatusEnum::IN_PROCESS;
        }

        return $workTypeStatus;
    }

    private function filterAnswers($answers, $workTypeId, $floor = null, $questionId = null)
    {
        return $answers->filter(function ($value, $key) use ($workTypeId, $floor, $questionId) {
            $parts = explode('-', $key);

            if ($parts[0] != $workTypeId) {
                return false;
            }

            if (!is_null($questionId) && isset($parts[1]) && $parts[1] != $questionId) {
                return false;
            }

            if (!is_null($floor) && isset($parts[2]) && $parts[2] != $floor) {
                return false;
            }

            return true;
        });
    }

    public function createRegulation($data)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $roleId = (int)$user->getRoleFromToken();
            $object = Article::find($data['object_id']);
            $controlId = $data['control_id'] ?? null;


            $monitoring = $this->createMonitoring($data, $object, $roleId);
            $this->saveObjectHistory($user, $roleId, $monitoring->id, $object);
            if ($monitoring) {
                $path = 'regulations/data.json';
                $data[$monitoring->id] = [
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'date' => Carbon::now()->format('Y-m-d H:i:s'),
                    'data' => $data,
                ];

                $all = Storage::exists($path)
                    ? json_decode(Storage::get($path), true)
                    : [];
                $all[] = $data;
                Storage::put($path, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            if (!empty($data['foundation'])) {
                $allRoleViolations = $this->handleChecklists(
                    $data['foundation'],
                    $object,
                    $data['block_id'],
                    $roleId,
                    false,
                    $monitoring->id,
                );
                $this->createRegulations($allRoleViolations, $object, $monitoring->id, $roleId);
                $this->foundationCreate($object, $data['block_id'], $user, $roleId, $monitoring->id);
            }


            if (!empty($data['public'])) {
                $this->createPublicChecklist($data['public'], $object, $roleId, $monitoring, $controlId);
            }

            if (!empty($data['positive'])) {
                $this->handleChecklists($data['positive'], $object, $data['block_id'], $roleId, true, null);
            }
            if (!empty($data['negative'])) {
                $allRoleViolations = $this->handleChecklists(
                    $data['negative'],
                    $object,
                    $data['block_id'],
                    $roleId,
                    false,
                    null
                );
                if ($roleId == UserRoleEnum::MUALLIF->value) {
                    $this->createAuthorRegulation($data['negative'], $object, $roleId, $data['block_id']);

                }
                $this->createRegulations($allRoleViolations, $object, $monitoring->id, $roleId);

            }
            if (isset($data['block_id'])) $this->changeBlockStatus($data['block_id'], $object);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

    }

    public function foundationCreate($object, $blockId, $user, $roleId, $monitoringId)
    {
        $block = Block::query()->find($blockId);

        $block->update(['foundation' => false]);

        FoundationBlock::query()->create([
            'object_id' => $object->id,
            'block_id' => $blockId,
            'monitoring_id' => $monitoringId,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'status' => 2
        ]);
    }

    public function saveObjectHistory($user, $roleId, $monitoringId, $object)
    {
        $history = new HistoryService('article_histories');

        $history = $history->createHistory(
            guId: $object->id,
            status: $object->object_status_id->value,
            type: LogType::ARTICLE_MONITORING,
            date: null,
            comment: $request->comment ?? '',
            additionalInfo: [
                'user_id' => $user->id,
                'role_id' => $roleId,
                'monitoring_id' => $monitoringId
            ]
        );
    }

    private function createAuthorRegulation($checklists, $object, $roleId, $blockId)
    {
        foreach ($checklists as $index => $checklistData) {
            $checklist = $this->getOrCreateChecklist($checklistData, $object, $blockId, null);
            $this->updateChecklistStatus($checklist, $checklistData, $roleId, false, $object);
            $this->saveAuthorRegulation($checklist, $checklistData, $object, $roleId, $blockId);

            $meta = ['user_answered' => 2];

            $this->historyService->createHistory(
                guId: $checklist->id,
                status: $checklist->status->value,
                type: LogType::TASK_HISTORY,
                date: null,
                comment: $checklistData['comment'] ?? "",
                additionalInfo: $meta
            );

            $checklist->update([
                'technic_author_answered_at' => null,
                'inspector_answered_at' => null,
            ]);

        }
    }

    private function saveAuthorRegulation($checklist, $checklistData, $object, $roleId, $blockId)
    {
        foreach ($checklistData['violations'] as $item) {
            $images = $this->saveAuthorImages($item['images']);
            $authorRegulation = AuthorRegulation::query()->create([
                'object_id' => $object->id,
                'block_id' => $blockId,
                'author_id' => Auth::id(),
                'author_role_id' => $roleId,
                'user_id' => $object->users()->wherePivot('role_id', UserRoleEnum::ICHKI->value)->pluck('users.id')->first(),
                'role_id' => UserRoleEnum::ICHKI->value,
                'bases_id' => $item['basis_id'] ?? null,
                'work_type_id' => $checklistData['work_type_id'],
                'author_images' => json_encode($images),
                'author_comment' => $item['comment'],
                'deadline' => Carbon::now()->addDays($checklistData['deadline']),
                'checklist_answer_id' => $checklist->id,
            ]);
        }
    }

    private function saveAuthorImages($images)
    {
        $meta = [];
        foreach ($images as $image) {
            $path = $image->store('images/author-regulation', 'public');
            $meta[] = $path;
        }
        return $meta;
    }

    private function createPublicChecklist($data, $object, $roleId, $monitoring, $controlId = null)
    {
        if (!empty($data['images'])) {
            foreach ($data['images'] as $image) {
                $imagePath = $image->store('monitoring', 'public');
                $monitoring->images()->create([
                    'url' => $imagePath,
                ]);
            }
        }

        $this->constantChecklist($data, $monitoring);

        if (!empty($data['positive'])) {
            $this->handleChecklists($data['positive'], $object, null, $roleId, true, $monitoring->id);
        }
        if (!empty($data['negative'])) {
            $allRoleViolations = $this->handleChecklists($data['negative'], $object, null, $roleId, false, $monitoring->id);
            $this->createRegulations($allRoleViolations, $object, $monitoring->id, $roleId, $controlId);
        }
    }

    private function constantChecklist($data, $monitoring)
    {
        $meta = [];
        if (isset($data['positive'])) {
            foreach ($data['positive'] as $positive) {
                $meta [$positive['question_id']] = $positive['status'];
            }
        }
        if (isset($data['negative'])) {
            foreach ($data['negative'] as $negative) {
                $meta [$negative['question_id']] = $negative['status'];
            }
        }

        if (!empty($meta)) {
            $monitoring->update([
                'constant_checklist' => json_encode($meta),
            ]);
        }

    }

    public function createMonitoring($data, $object, $roleId)
    {
        return Monitoring::create([
            'object_id' => $object->id,
            'number' => 123,
            'regulation_type_id' => 1,
            'work_in_progress' => $data['work_in_progress'] ?? null,
            'block_id' => $data['block_id'] ?? null,
            'created_by' => Auth::id(),
            'created_by_role' => $roleId,
        ]);

    }

    private function handleChecklists($checklists, $object, $blockId, $roleId, $isPositive, $monitoringID)
    {
        $allRoleViolations = [];
        foreach ($checklists as $index => $checklistData) {
            $checklist = $this->getOrCreateChecklist($checklistData, $object, $blockId, $monitoringID);

            $this->updateChecklistStatus($checklist, $checklistData, $roleId, $isPositive, $object);
            if ($isPositive) {
                $answeredField = $this->getAnsweredFieldByRole($roleId);
                $meta = ['user_answered' => $checklist->$answeredField];

                $this->historyService->createHistory(
                    guId: $checklist->id,
                    status: $checklist->status->value,
                    type: LogType::TASK_HISTORY,
                    date: null,
                    comment: $checklistData['comment'] ?? "",
                    additionalInfo: $meta
                );
                if ($roleId == UserRoleEnum::MUALLIF->value) {
                    if ($checklist->technic_answered == 1) {
                        $checklist->update([
                            'inspector_answered_at' => now()->addDays(5)->setTime(23, 59, 59),
                            'technic_author_answered_at' => null
                        ]);
                    }
                }

                if ($roleId == UserRoleEnum::TEXNIK->value) {
                    if ($checklist->author_answered == 1) {
                        $checklist->update([
                            'inspector_answered_at' => now()->addDays(5)->setTime(23, 59, 59),
                            'technic_author_answered_at' => null
                        ]);
                    }
                }

                if ($roleId == UserRoleEnum::INSPECTOR->value) {
                    $checklist->update([
                        'inspector_answered_at' => null,
                        'technic_author_answered_at' => null,
                    ]);
                }
                $this->sendNotification($checklist, $object, $blockId);
            } else {
                $allRoleViolations[$index] = $this->handleViolations($checklistData, $checklist, $roleId);
            }
        }
        return $allRoleViolations;
    }

    private function changeBlockStatus($blockId, $object)
    {
        $workTypes = $this->getQuestionList($blockId);
        $block = Block::query()->find($blockId);
        $count = 0;
        foreach ($workTypes as $workType) {
            if ($workType['questions'][0]['work_type_status'] == WorkTypeStatusEnum::CONFIRMED) {
                $count += 1;
            }
        }
        if ($count == count($workTypes)) {
            $this->articleService->sendApartment($object);

            $block->update([
                'status' => false
            ]);
        }

    }

    private function getOrCreateChecklist($checklistData, $object, $blockId, $monitoringID)
    {
        return isset($checklistData['checklist_id'])
            ? CheckListAnswer::find($checklistData['checklist_id'])
            : CheckListAnswer::create([
                'block_id' => $blockId,
                'status' => $checklistData['status'] ?? CheckListStatusEnum::NOT_FILLED->value,
                'work_type_id' => $checklistData['work_type_id'],
                'question_id' => $checklistData['question_id'],
                'monitoring_id' => $monitoringID,
                'floor' => $checklistData['floor'] ?? null,
                'object_id' => $object->id,
                'object_type_id' => $object->object_type_id
            ]);
    }

    private function updateChecklistStatus($checklist, $checklistData, $roleId, $isPositive, $object)
    {
        $answeredField = $this->getAnsweredFieldByRole($roleId);
        $statusField = $isPositive
            ? $this->getPositiveStatusField($checklist, $roleId, $checklistData)
            : ($checklist->status == CheckListStatusEnum::NOT_FILLED ? 1 : CheckListStatusEnum::RAISED->value);
        if ($statusField === 'inspector_answered' && $isPositive === false) {
            $updateData = [
                $answeredField => 1,
                'status' => $statusField ?? $checklist->status,
                'technic_answered' => null,
                'author_answered' => null,
            ];

        } elseif ($statusField === 'technic_answered' && $isPositive === false) {
            $updateData = [
                $answeredField => 2,
                'status' => $statusField ?? $checklist->status,
                'inspector_answered' => null,
                'author_answered' => null,
            ];

        } elseif ($statusField === 'author_answered' && $isPositive === false) {
            $updateData = [
                $answeredField => 2,
                'status' => $statusField ?? $checklist->status,
                'inspector_answered' => null,
                'technic_answered' => null,
            ];

        } else {
            $updateData = [
                $answeredField => $isPositive ? 1 : 2,
                'status' => $statusField ?? $checklist->status,
            ];
        }
        $checklist->update($updateData);


    }

    private function getAnsweredFieldByRole($roleId)
    {
        return match ($roleId) {
            UserRoleEnum::INSPECTOR->value => 'inspector_answered',
            UserRoleEnum::TEXNIK->value => 'technic_answered',
            UserRoleEnum::MUALLIF->value => 'author_answered',
            UserRoleEnum::REG_LAB->value => 'reg_lab_answered',
            UserRoleEnum::LABORANT->value => 'laborant_answered',
            default => throw new \UnhandledMatchError("Unhandled roleId: {$roleId}"),

        };
    }

    private function getPositiveStatusField($checklist, $roleId, $checklistData)
    {
        return ($checklistData['status'] != CheckListStatusEnum::NOT_FILLED->value)
            ? match ($roleId) {
                UserRoleEnum::INSPECTOR->value => CheckListStatusEnum::CONFIRMED->value,
                UserRoleEnum::TEXNIK->value => ($checklist->author_answered == 1) ? CheckListStatusEnum::SECOND->value : null,
                UserRoleEnum::MUALLIF->value => ($checklist->technic_answered == 1) ? CheckListStatusEnum::SECOND->value : null,
                default => null
            }
            : null;
    }

    private function handleViolations($checklistData, $checklist, $roleId)
    {
        $roleViolations = [];
        $question = Question::findOrFail($checklistData['question_id']);
        $answeredField = $this->getAnsweredFieldByRole($roleId);
        $violationIds = [];
        foreach ($checklistData['violations'] as $value) {

            $violation = $this->createViolation($value, $question, $checklist);
            $violationIds[] = $violation->id;

            $this->storeViolationImages($violation, $value['images'] ?? []);

            foreach ($value['roles'] as $role) {
                $roleViolations[$role]['violation_ids'][] = $violation->id;
                $roleViolations[$role]['deadline'] = $checklistData['deadline'];
                $roleViolations[$role]['checklist_id'] = $checklist->id;
                $roleViolations[$role]['question_id'] = $checklistData['question_id'];
            }
        }
        $meta = [
            'violations' => $violationIds,
            'user_answered' => $checklist->$answeredField,
        ];

        $checklist->update([
            'inspector_answered_at' => null,
            'technic_author_answered_at' => null,
        ]);

        $this->historyService->createHistory(
            guId: $checklist->id,
            status: $checklist->status->value,
            type: LogType::TASK_HISTORY,
            date: null,
            additionalInfo: $meta,
        );

        return $roleViolations;
    }

    private function createViolation($violationData, $question, $checklist)
    {
        return Violation::create([
            'question_id' => $question->id ?? null,
            'title' => $question->name,
            'description' => $violationData['description'],
            'comment' => $violationData['comment'],
            'bases_id' => $violationData['basis_id'] ?? null,
            'checklist_id' => $checklist->id,
        ]);
    }

    private function storeViolationImages($violation, $images)
    {
        foreach ($images as $image) {
            $path = $image->store('images/violation', 'public');
            $violation->images()->create(['url' => $path]);
        }
    }

    private function createRegulations($allRoleViolations, $object, $monitoringID, $createdByRole, $controlId = null)
    {
        foreach ($allRoleViolations as $roles) {
            foreach ($roles as $roleId => $role) {
                $regulation = $this->createRegulationEntry($object, $monitoringID, $role, $roleId, $createdByRole, $controlId);
                $this->createRegulationUser($regulation);
                $this->sendSms($regulation, $object->task_id);
                $this->linkViolationsToRegulation($regulation, $role['violation_ids']);
                if ($controlId != null) {
                    AiControl::query()->find($controlId)->update(['send_operator' => null, 'status' => 3]);
                }
            }
        }
    }


    private function createRegulationEntry($object, $monitoringID, $role, $roleId, $createdByRole, $controlId=null)
    {
        return DB::transaction(function () use ($object, $monitoringID, $role, $roleId, $createdByRole, $controlId) {
            $lastNumber = DB::table('regulations')
                ->whereRaw("regulation_number ~ '^\\d+$'")
                ->orderByRaw("CAST(regulation_number AS BIGINT) DESC")
                ->lockForUpdate()
                ->value('regulation_number');

            $newNumber = $lastNumber ? ((int)$lastNumber + 1) : 299999;

            $userId = $object->users()
                ->wherePivot('role_id', $roleId)
                ->pluck('users.id')
                ->first();

            return Regulation::create([
                'object_id' => $object->id,
                'deadline' => Carbon::now()->addDays($role['deadline'])->endOfDay(),
                'checklist_id' => $role['checklist_id'],
                'question_id' => $role['question_id'],
                'regulation_status_id' => RegulationStatusEnum::PROVIDE_REMEDY,
                'regulation_number' => $newNumber,
                'regulation_type_id' => 1,
                'created_by_role_id' => $createdByRole,
                'created_by_user_id' => Auth::id(),
                'user_id' => $userId,
                'monitoring_id' => $monitoringID,
                'role_id' => $roleId,
                'control_id' => $controlId,
            ]);
        });
    }

//    private function createRegulationEntry($object, $monitoringID, $role, $roleId, $createdByRole)
//    {
//        return Regulation::create([
//            'object_id' => $object->id,
//            'deadline' => Carbon::now()->addDays($role['deadline'])->endOfDay(),
//            'checklist_id' => $role['checklist_id'],
//            'question_id' => $role['question_id'],
//            'regulation_status_id' => RegulationStatusEnum::PROVIDE_REMEDY,
//            'regulation_number' => $this->determinateNumber(),
//            'regulation_type_id' => 1,
//            'created_by_role_id' => $createdByRole,
//            'created_by_user_id' => Auth::id(),
//            'user_id' => $object->users()->wherePivot('role_id', $roleId)->pluck('users.id')->first(),
//            'monitoring_id' => $monitoringID,
//            'role_id' => $roleId,
//        ]);
//
//    }
//
//    private function determinateNumber()
//    {
//        $regulation = DB::table('regulations')
//            ->select('regulation_number')
//            ->whereRaw("CAST(REGEXP_REPLACE(regulation_number, '[^0-9]', '', 'g') AS BIGINT) = (
//                    SELECT MAX(CAST(REGEXP_REPLACE(regulation_number, '[^0-9]', '', 'g') AS BIGINT))
//                    FROM regulations
//                    WHERE regulation_number ~ '^\\d+$'
//                )")
//            ->first() ;
//        return $regulation->regulation_number ? (int)$regulation->regulation_number + 1 : 299999;
//    }


    public function createRegulationUser($regulation)
    {
        RegulationUser::create([
            'regulation_id' => $regulation->id,
            'from_user_id' => $regulation->created_by_user_id,
            'from_role_id' => $regulation->created_by_role_id,
            'to_user_id' => $regulation->user_id,
            'to_role_id' => $regulation->role_id,
        ]);
    }

    public function sendSms($regulation, $objectNumber)
    {
        try {
            $user = User::query()->find($regulation->user_id);
            $message = MessageTemplate::regulationCreated($regulation->regulation_number, $objectNumber);
            (new SmsService($user->phone, $message))->sendSms();
        } catch (\Exception $exception) {

        }

    }

    private function linkViolationsToRegulation($regulation, $violationIds)
    {
        foreach ($violationIds as $violationId) {
            RegulationViolation::create([
                'regulation_id' => $regulation->id,
                'violation_id' => $violationId
            ]);
        }
    }


    public function createActViolation($dto)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $roleId = $user->getRoleFromToken();
            $regulation = Regulation::query()->findOrFail($dto->regulationId);
            $object = Article::query()->findOrFail($regulation->object_id);
            $regulation->update([
                'regulation_status_id' => RegulationStatusEnum::CONFIRM_REMEDY,
                'paused_at' => now()
            ]);

            if ($regulation->created_by_role_id == UserRoleEnum::INSPECTOR->value) {
                $this->sendNotificationRegulation($regulation, $object);
            }

            $actViolations = $regulation->actViolations()->where('act_violation_type_id', 1)->get();

            if ($actViolations->isNotEmpty()) {
                foreach ($dto->meta as $item) {
                    $act = ActViolation::query()
                        ->where('regulation_violation_id', $item['violation_id'])
                        ->where('regulation_id', $dto->regulationId)
                        ->where('act_violation_type_id', 1)
                        ->first();

                    $act->update([
                        'act_status_id' => 1,
                        'comment' => $item['comment'],
                        'status' => ActViolation::PROGRESS,
                    ]);

                    $act->images()->delete();
                    $act->documents()->delete();


                    $demands = RegulationDemand::create([
                        'regulation_violation_id' => $dto->regulationId,
                        'user_id' => Auth::id(),
                        'role_id' => $roleId,
                        'act_status_id' => 1,
                        'act_violation_type_id' => 1,
                        'comment' => $item['comment'],
                        'act_violation_id' => $act->id,
                        'status' => ActViolation::PROGRESS
                    ]);

                    if (!empty($item['images'])) {
                        foreach ($item['images'] as $image) {
                            $path = $image->store('images/act-violation', 'public');
                            $act->images()->create(['url' => $path]);
                            $demands->images()->create(['url' => $path]);
                        }
                    }

                    if (!empty($item['files'])) {
                        foreach ($item['files'] as $document) {
                            $path = $document->store('document/act-violation', 'public');
                            $act->documents()->create(['url' => $path]);
                            $demands->documents()->create(['url' => $path]);
                        }
                    }
                }

            } else {
                foreach ($dto->meta as $item) {
                    $act = ActViolation::create([
                        'regulation_violation_id' => $item['violation_id'],
                        'regulation_id' => $dto->regulationId,
                        'user_id' => Auth::id(),
                        'act_status_id' => 1,
                        'comment' => $item['comment'],
                        'role_id' => $roleId,
                        'act_violation_type_id' => 1,
                        'status' => ActViolation::PROGRESS,
                    ]);

                    $demands = RegulationDemand::create([
                        'regulation_violation_id' => $dto->regulationId,
                        'user_id' => Auth::id(),
                        'role_id' => $roleId,
                        'act_status_id' => 1,
                        'act_violation_type_id' => 1,
                        'comment' => $item['comment'],
                        'act_violation_id' => $act->id,
                        'status' => ActViolation::PROGRESS
                    ]);

                    if (!empty($item['images'])) {
                        foreach ($item['images'] as $image) {
                            $path = $image->store('images/act-violation', 'public');
                            $act->images()->create(['url' => $path]);
                            $demands->images()->create(['url' => $path]);
                        }
                    }

                    if (!empty($item['files'])) {
                        foreach ($item['files'] as $document) {
                            $path = $document->store('document/act-violation', 'public');
                            $act->documents()->create(['url' => $path]);
                            $demands->documents()->create(['url' => $path]);
                        }
                    }

                }
            }


            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    private function sendNotification($checklist, $object, $blockId)
    {
        try {
            $block = Block::query()->find($blockId);

            if ($checklist->status == CheckListStatusEnum::SECOND) {
                $inspector = $object->users()->where('role_id', UserRoleEnum::INSPECTOR->value)->first();
                $ichki = $object->users()->where('role_id', UserRoleEnum::ICHKI->value)->first();
                $message = MessageTemplate::attachRegulationInspector($ichki->full_name, $object->task_id, $block->name, 'Ichki nazorat', now());
                $data = [
                    'screen' => 'confirm_work'
                ];
                $inspector->notify(new InspectorNotification(title: 'Ayrim ishlar yakunlandi', message: $message, url: null, additionalInfo: $data));
            }

        } catch (\Exception $exception) {

        }

    }

    private function sendNotificationRegulation($regulation, $object)
    {
        try {
            $inspector = $object->users()->where('role_id', UserRoleEnum::INSPECTOR->value)->first();
            $user = User::query()->find($regulation->user_id);
            $role = Role::query()->find($regulation->role_id);
            $data = [
                'screen' => 'confirm_regulations'
            ];
            $message = MessageTemplate::confirmRegulationInspector($user->full_name, $regulation->object->task_id, $regulation->regulation_number, $regulation->monitoring->block->name, $role->name, now());
            $inspector->notify(new InspectorNotification(title: "Yozma ko'rsatmani tasdiqlash so'raldi", message: $message, url: null, additionalInfo: $data));

        } catch (\Exception $exception) {

        }

    }

}
