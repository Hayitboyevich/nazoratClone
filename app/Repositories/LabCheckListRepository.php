<?php

namespace App\Repositories;

use App\Enums\LabCheckListTestingOnixtStatusEnum;
use App\Enums\LabCheckListTestingSchmidtStatusEnum;
use App\Enums\LabCheckListTestingStatusEnum;
use App\Exceptions\GeneralJsonException;
use App\Models\LabCheckListChosenTestingType;
use App\Models\LabCheckListTestingStatementMark;
use App\Models\LabCheckListTestingCondition;
use App\Models\LabCheckListTestingMainInfo;
use App\Models\LabCheckListTestingQuestion;
use App\Models\LabCheckListTestingQuestionMark;
use App\Models\LabCheckListTestingStatement;
use App\Models\LabCheckListTestingType;
use App\Models\Question;
use App\Repositories\Interfaces\LabCheckListRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class LabCheckListRepository implements LabCheckListRepositoryInterface
{

    private function checkCreatedTest(array $data)
    {
        return LabCheckListTestingMainInfo::query()
            ->where(['article_id' => $data['article_id'], 'block_id' => $data['block_id'],
                'work_type_id' => $data['work_type_id'], 'lab_check_list_testing_type_id' => $data['lab_check_list_testing_type_id'],
                'question_id' => $data['question_id']])
            ->count();
    }

    public function getLabCheckListTestingTypes()
    {
        try {
            return LabCheckListTestingType::query()->orderBy('id')->get();
        } catch (\Exception $e) {
            throw new ModelNotFoundException(message: $e->getMessage());
        }
    }

    public function saveMainInfo(array $data)
    {
//        if ($this->checkCreatedTest($data) > 0) {
//            throw new GeneralJsonException(message: 'Test was created before for this type!');
//        }
        return DB::transaction(fn() => LabCheckListTestingMainInfo::query()->create($data));
    }

    public function getBlockTests(array $filter)
    {
        return LabCheckListTestingMainInfo::query()
            ->when(!empty($filter['main_info_id']), fn($query) => $query->where('id', $filter['main_info_id']))
            ->when(
                ($filter['status'] ?? null) === LabCheckListTestingStatusEnum::BOTH_SCHEME_CREATED->value,
                fn($q) => $q->whereIn('id', $filter['main_info_ids'] ?? [])
            )
            ->when(!empty($filter['article_id']), fn($query) => $query->where('article_id', $filter['article_id']))
            ->when(!empty($filter['block_id']), fn($query) => $query->where('block_id', $filter['block_id']))
            ->when(!empty($filter['work_type_id']), fn($query) => $query->where('work_type_id', $filter['work_type_id']))
            ->when(!empty($filter['question_id']), fn($query) => $query->where('question_id', $filter['question_id']))
            ->when(!empty($filter['check_condition_status']) and $filter['check_condition_status'] === 'true',
                fn($query) => $query->whereNotIn('status', [LabCheckListTestingStatusEnum::STATEMENT_SENT_TO_REG_LAB->value,
                    LabCheckListTestingStatusEnum::STATEMENT_ACCEPTED->value, LabCheckListTestingStatusEnum::STATEMENT_REJECTED->value]))
            ->with('testingQuestion.marks')
            ->get();
    }

    public function getMainInfoById($main_info_id)
    {
        return LabCheckListTestingMainInfo::query()->findOrFail($main_info_id);

    }

    public function getQuestionMarks(array $filter)
    {
        return LabCheckListTestingQuestion::query()
            ->where(['lab_check_list_testing_main_info_id' => $filter['main_info_id']])
            ->with('marks')
            ->get();
    }

    public function getMarkIndicators(array $filter)
    {
        return LabCheckListTestingQuestionMark::query()->where('lab_check_list_testing_question_id' , $filter['lab_check_list_testing_question_id'])
            ->with('marksChild')
            ->get();
    }

    public function saveQuestion(array $data)
    {
        unset($data['con_marks']);
        return LabCheckListTestingQuestion::query()->create($data);
    }

    public function saveQuestionMarks(LabCheckListTestingQuestion $saved_question, array $question_data, $type)
    {
        if (!empty($question_data['con_marks'])) {
            $marks = array_map(function ($item) use ($type) {
                return [
                    'mark' => $item['mark'],
                    'mark_number' => $item['mark_number'],
                    'type' => $type,
                    'mark_id' => $item['mark_id'] ?? null,
                ];
            }, $question_data['con_marks']);

            $saved_question->marks()->createMany($marks);
        }
    }

    public function saveMarkIndicator(array $indicator)
    {
        return LabCheckListTestingQuestionMark::query()->create($indicator);
    }

    public function saveLabCheckListStatement(array $data)
    {
        return LabCheckListTestingStatement::query()->create($data);
    }

    public function getLabCheckListStatement($id)
    {
        return LabCheckListTestingStatement::query()->find($id);
    }

    public function getStatementsByFilters(array $filters)
    {
        if (empty($filters['article_id']) || empty($filters['block_id']) ||
            empty($filters['work_type_id']) || empty($filters['floor'])
        ) return null;
        return LabCheckListChosenTestingType::query()
            ->where('article_id', $filters['article_id'])
            ->where('block_id', $filters['block_id'])
            ->where('work_type_id', $filters['work_type_id'])
            ->where('floor', $filters['floor'])
            ->whereIn('actions_status', [LabCheckListTestingStatusEnum::STATEMENT_ACCEPTED->value, LabCheckListTestingStatusEnum::STATEMENT_REJECTED->value])
            ->with(['statements:id,lab_check_list_chosen_testing_type_id,document_number',
                'conditions' => function ($q) {
                    $q->where('onix_status', 3)
                        ->where('schmidt_status', 4)
                        ->with('mainInfos:id,lab_check_list_testing_condition_id,lab_check_list_testing_type_id');
                }
            ])
            ->get()
            ->flatMap(function ($first_chosen) {
                return $first_chosen->statements->map(function ($statement) use ($first_chosen) {
                    return [
                        'id' => $statement->id,
                        'document_number' => $statement->document_number,
                        'lab_check_list_chosen_testing_type_id' => $statement->lab_check_list_chosen_testing_type_id,
                        'testing_schemes_id' => $first_chosen->conditions
                            ->flatMap(function ($condition) {
                                return $condition->mainInfos->map(function ($item) {
                                    return ['id' => $item->id, 'lab_check_list_testing_condition_id' => $item->lab_check_list_testing_condition_id, 'lab_check_list_testing_type_id' => $item->lab_check_list_testing_type_id];
                                });
                            })
                    ];
                });
            });
    }

    public function saveFirstChosenTestType(array $data)
    {
        $this->sanitize(data: $data, key: 'questions');
        $this->sanitize(data: $data, key: 'question_id');
        return LabCheckListChosenTestingType::query()->create($data);
    }

    public function getFirstChosenTestType(array $filter)
    {
        return LabCheckListChosenTestingType::query()
            ->when(isset($filter['floor']), fn($q) => $q->where('floor', $filter['floor']))
            ->when(isset($filter['article_id']), fn($q) => $q->where('article_id', $filter['article_id']))
            ->when(isset($filter['block_id']), fn($q) => $q->where('block_id', $filter['block_id']))
            ->when(isset($filter['work_type_id']), fn($q) => $q->where('work_type_id', $filter['work_type_id']))
            ->when(!isset($filter['question_id']), function ($q) use ($filter) {
                $q->with('conditions', 'statement');
            })
            ->when(isset($filter['question_id']), function ($q) use ($filter) {
                $q->with(['condition' => function ($query) use ($filter) {
                    $query->when(!empty($filter['question_id']), fn($q) => $q->where('question_id', $filter['question_id']));
                }]);
            })
            ->whereNotIn('actions_status', [LabCheckListTestingStatusEnum::STATEMENT_ACCEPTED->value, LabCheckListTestingStatusEnum::STATEMENT_REJECTED->value])
            ->first();
    }

    public function getFirstChosenTestTypeById($id)
    {
        return LabCheckListChosenTestingType::query()->findOrFail($id);
    }

    public function createWorkTypeCondition(array $data)
    {
        unset($data['question_type'], $data['floor'], $data['article_id'], $data['block_id'], $data['work_type_id']);
        return LabCheckListTestingCondition::query()->create($data);
    }

    public function getTestingCondition($id)
    {
        return LabCheckListTestingCondition::query()->find($id);
    }

    public function getNotReadyStatusConditionsByFirstChosenId($id)
    {
        return LabCheckListTestingCondition::query()
            ->where('lab_check_list_chosen_testing_type_id', $id)
            ->whereNot(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('onix_status', 1)
                        ->where('schmidt_status', 1);
                })
                    ->orWhere(function ($q2) {
                        $q2->where('onix_status', 3)
                            ->where('schmidt_status', 4);
                    });
            })
            ->get();


    }

    public function saveStatementMark(array $mark)
    {
        return LabCheckListTestingStatementMark::query()->create($mark);
    }

    private function sanitize(array &$data, $key): void
    {
        unset($data[$key]);
    }

    public function saveQuestionConditions(array $data)
    {
        return LabCheckListTestingCondition::query()->create($data);
    }

    public function getConditions($id)
    {
        return LabCheckListTestingCondition::query()->where(['lab_check_list_testing_type_id' => $id, 'status' => LabCheckListTestingStatusEnum::BOTH_SCHEME_CREATED->value])->get();
    }

    public function getConditionsByChosenId($id)
    {
        return LabCheckListTestingCondition::query()->where('lab_check_list_chosen_testing_type_id', $id)
            ->where('status', LabCheckListTestingStatusEnum::BOTH_SCHEME_CREATED->value);
    }

}
