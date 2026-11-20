<?php

namespace App\Services;

use App\Enums\LabCheckListTestingOnixtStatusEnum;
use App\Enums\LabCheckListTestingSchmidtStatusEnum;
use App\Enums\LabCheckListTestingStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\GeneralJsonException;
use App\Models\ArticleUser;
use App\Models\Inspection;
use App\Models\LabChecklistAnswer;
use App\Models\LabCheckListTestingCondition;
use App\Models\LabCheckListTestingQuestion;
use App\Models\LabCheckListTestingQuestionMark;
use App\Models\LabCheckListTestingStatementMark;
use App\Repositories\Interfaces\LabCheckListRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LabCheckListService
{

    protected LabCheckListRepositoryInterface $labCheckListRepository;

    public function __construct(LabCheckListRepositoryInterface $labCheckListRepository)
    {
        $this->labCheckListRepository = $labCheckListRepository;
    }

    //////////////////////Get Functions////////////////////////

    public function getLabCheckListTestingTypes()
    {
        return $this->labCheckListRepository->getLabCheckListTestingTypes();
    }

    public function getQuestionMarks(array $filter)
    {
        try {
            return match ($filter['type']) {
                '1' => $this->labCheckListRepository->getQuestionMarks(filter: $filter),
                '2' => $this->labCheckListRepository->getMarkIndicators(filter: $filter)
            };
        } catch (\Exception $e) {
            throw new ModelNotFoundException(message: $e->getMessage() . "Try again later!", code: 404);
        }
    }

    ///////////////////////////////////////////////////////////

    public function saveMainInfo($data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $testing_conditions = $this->labCheckListRepository->getTestingCondition($data['lab_check_list_testing_condition_id']);
                $first_chosen_type = $this->labCheckListRepository->getFirstChosenTestTypeById($testing_conditions->lab_check_list_chosen_testing_type_id);

                if (!is_null($testing_conditions->onix_main_info_id) and !is_null($testing_conditions->schmidt_main_info_id))
                    throw new GeneralJsonException(message: 'You can not create!', code: 403);

                if ($testing_conditions->status == LabCheckListTestingStatusEnum::INITIAL_STATUS->value
                    and ($testing_conditions->onix_status == LabCheckListTestingOnixtStatusEnum::INITIAL_STATUS->value
                        or $testing_conditions->schmidt_status == LabCheckListTestingSchmidtStatusEnum::INITIAL_STATUS->value)) {
                    $status = null;
                    $scheme_status_column = null;
                    $scheme_id_column = null;

                    if ($data['lab_check_list_testing_type_id'] == LabCheckListTestingCondition::SCHMIDT) {
                        $status = LabCheckListTestingSchmidtStatusEnum::MAIN_INFO_CREATED->value;
                        $scheme_status_column = 'schmidt_status';
                        $scheme_id_column = 'schmidt_main_info_id';
                    }

                    if ($data['lab_check_list_testing_type_id'] == LabCheckListTestingCondition::ONIX) {
                        $status = LabCheckListTestingOnixtStatusEnum::MAIN_INFO_CREATED->value;
                        $scheme_status_column = 'onix_status';
                        $scheme_id_column = 'onix_main_info_id';
                    }

                    if (($data['lab_check_list_testing_type_id'] == LabCheckListTestingCondition::ONIX and $testing_conditions->onix_status == LabCheckListTestingOnixtStatusEnum::INITIAL_STATUS->value)
                        or ($data['lab_check_list_testing_type_id'] == LabCheckListTestingCondition::SCHMIDT and $testing_conditions->schmidt_status == LabCheckListTestingSchmidtStatusEnum::INITIAL_STATUS->value)) {
                        $main_info = $this->labCheckListRepository->saveMainInfo($data);
                        $testing_conditions->update([
                            $scheme_id_column => $main_info->id,
                            $scheme_status_column => $status
                        ]);
                        $first_chosen_type->update(['actions_status' => LabCheckListTestingStatusEnum::INITIAL_STATUS->value]);
                    } else {
                        throw new GeneralJsonException(message: 'You can not create!', code: 403);
                    }
                }
                return $main_info;
            });
        } catch (\Exception $e) {
            throw new GeneralJsonException(message: $e->getMessage(), code: 422);
        }
    }

    public function getBlockTests(array $filter)
    {
        try {
            $tests = $this->labCheckListRepository->getBlockTests(filter: $filter);
            if (!empty($filter['main_info_id'])) return $tests->first();
            else return $tests;
        } catch (\Exception $e) {
            throw new ModelNotFoundException(message: $e->getMessage(), code: 404);
        }
    }

    public function saveConInfo($data)
    {
        $testing_conditions = $this->checkCreatingCon($data);
        if ($testing_conditions) {
            try {
                return DB::transaction(function () use ($data, $testing_conditions) {
                    $type = $data['type'];
                    if (!empty($data['main_info_id'])) {
                        $main_info_id = $data['main_info_id'];
                    } else {
//                        $question_mark = LabCheckListTestingQuestionMark::query()->where('id', $data['mark_id'])->first();
                        $get_question = LabCheckListTestingQuestion::query()->where('id', $data['lab_check_list_testing_question_id'])->first();
                        $main_info_id = $get_question->lab_check_list_testing_main_info_id;
                    }
                    $main_info = $this->labCheckListRepository->getBlockTests(['main_info_id' => $main_info_id]);
                    $first_chosen_type = $this->labCheckListRepository->getFirstChosenTestTypeById($testing_conditions->lab_check_list_chosen_testing_type_id);
                    $column_testing_status = $main_info[0]->lab_check_list_testing_type_id == 2 ? 'onix_status' : 'schmidt_status';
                    if ($data['type'] == 1) {
                        $status = $main_info[0]->lab_check_list_testing_type_id == 2 ? LabCheckListTestingOnixtStatusEnum::READY_TO_STATEMENT->value : LabCheckListTestingSchmidtStatusEnum::MAIN_INFO_QUESTIONS_CREATED->value;
                        foreach ($data['questions'] as $question) {
                            $question['lab_check_list_testing_main_info_id'] = $main_info_id;
                            $saved_question = $this->labCheckListRepository->saveQuestion($question);
                            $this->labCheckListRepository->saveQuestionMarks(saved_question: $saved_question, question_data: $question, type: $type);
                        }
                        $testing_conditions->update([$column_testing_status => $status]);
                        if ($column_testing_status == 'onix_status' and $testing_conditions->schmidt_status == LabCheckListTestingSchmidtStatusEnum::READY_TO_STATEMENT->value) {
                            $first_chosen_type->update(['actions_status' => LabCheckListTestingStatusEnum::BOTH_SCHEME_CREATED->value]);
                        } else $first_chosen_type->update(['actions_status' => LabCheckListTestingStatusEnum::INITIAL_STATUS->value]);
                    }
                    if ($type == 2) {
                        foreach ($data['mark_indicators'] as $indicator) {
                            $indicator['type'] = $type;
                            $this->labCheckListRepository->saveMarkIndicator($indicator);
                        }
                        $status = LabCheckListTestingSchmidtStatusEnum::READY_TO_STATEMENT->value;
                        $testing_conditions->update([$column_testing_status => $status]);
                        $get_question->update(['status' => LabCheckListTestingStatusEnum::SCHMIDT_SCHEME_MARKS_CREATED->value]);

                        $not_ready_conditons = $this->labCheckListRepository->getNotReadyStatusConditionsByFirstChosenId($first_chosen_type->id);

                        $not_ready_conditons->count() === 0 ?
                            $first_chosen_type->update(['actions_status' => LabCheckListTestingStatusEnum::BOTH_SCHEME_CREATED->value]) : $first_chosen_type->update(['actions_status' => LabCheckListTestingStatusEnum::INITIAL_STATUS->value]);
                    }
                });
            } catch (\Exception $e) {
                throw new GeneralJsonException(
                    message: $e->getMessage(),
                    code: 422
                );
            }
        }
        throw new GeneralJsonException(message: 'You can not create!', code: 403);
    }

    private function checkCreatingCon(array $data)
    {
        if ($data['type'] == 2) {
//            $question_mark = LabCheckListTestingQuestionMark::query()->where('id', $data['mark_id'])->first();
            $question = LabCheckListTestingQuestion::query()->where('id',  $data['lab_check_list_testing_question_id'])->first();
            $data['main_info_id'] = $question->lab_check_list_testing_main_info_id;
        }
        $main_info = $this->labCheckListRepository->getBlockTests($data);
        $testing_type = $main_info[0]->lab_check_list_testing_type_id;
        $lab_check_list_testing_condition = $this->labCheckListRepository->getTestingCondition($main_info[0]->lab_check_list_testing_condition_id);
        if (($testing_type == LabCheckListTestingCondition::ONIX and $lab_check_list_testing_condition->onix_status == LabCheckListTestingOnixtStatusEnum::MAIN_INFO_CREATED->value) or
            ($testing_type == LabCheckListTestingCondition::SCHMIDT and $lab_check_list_testing_condition->schmidt_status == LabCheckListTestingSchmidtStatusEnum::MAIN_INFO_CREATED->value) or
            ($testing_type == LabCheckListTestingCondition::SCHMIDT and $lab_check_list_testing_condition->schmidt_status == LabCheckListTestingSchmidtStatusEnum::MAIN_INFO_QUESTIONS_CREATED->value) or
            ($testing_type == LabCheckListTestingCondition::ONIX and $lab_check_list_testing_condition->onix_status == LabCheckListTestingOnixtStatusEnum::READY_TO_STATEMENT->value) or
            ($testing_type == LabCheckListTestingCondition::SCHMIDT and $lab_check_list_testing_condition->schmidt_status == LabCheckListTestingSchmidtStatusEnum::READY_TO_STATEMENT->value))
            return $lab_check_list_testing_condition;
        return false;
    }

    public function saveLabCheckListStatement(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $statement = $this->labCheckListRepository->saveLabCheckListStatement($data);
                $first_chosen_type = $this->labCheckListRepository->getFirstChosenTestTypeById($data['lab_check_list_chosen_testing_type_id']);
                $first_chosen_type->update(['actions_status' => LabCheckListTestingStatusEnum::STATEMENT_FIRST_CHAPTER_CREATED->value]);
//                $condition = $this->labCheckListRepository->getConditionsByChosenId($data['lab_check_list_chosen_testing_type_id']);
//                $condition->update(['status' => LabCheckListTestingStatusEnum::STATEMENT_FIRST_STEP_CREATED->value]);
                return $statement;
            });
        } catch (\Exception $e) {
            throw new GeneralJsonException(message: $e->getMessage(), code: 422);
        }
    }

    public function saveFirstChosenTestType(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $testing_type = $this->labCheckListRepository->saveFirstChosenTestType($data);
                foreach ($data['questions'] as $question) {
                    $question['lab_check_list_chosen_testing_type_id'] = $testing_type->id;
                    $this->labCheckListRepository->createWorkTypeCondition($question);
                }
                return $testing_type->load(['conditions' => function ($query) use ($data) {
                    $query->where('question_id', $data['question_id']);
                }]);
            });
        } catch (\Exception $e) {
            throw new GeneralJsonException(message: $e->getMessage(), code: 500);
        }
    }

    public function saveStatementMark(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $saved_data = [
                    'lab_check_list_statement_id' => $data['lab_check_list_statement_id'],
                    'statement_marks' => []
                ];
                foreach ($data['statement_marks'] as $mark) {
                    $saved_mark = $this->labCheckListRepository->saveStatementMark([
                        ...$mark,
                        'lab_check_list_statement_id' => $data['lab_check_list_statement_id'],
                        'concrete_norm' => $data['concrete_norm'],
                    ]);
                    $saved_data['statement_marks'][] = $saved_mark;
                }
                $first_chosen_type = $this->labCheckListRepository->getFirstChosenTestTypeById($data['lab_check_list_chosen_testing_type_id']);
                $negative_statement_marks = LabCheckListTestingStatementMark::query()
                    ->where('lab_check_list_statement_id', $data['lab_check_list_statement_id'])
                    ->where('decision', false)
                    ->count();
                $user = Auth::user();
                $first_chosen_type
                    ->update(['actions_status' => LabCheckListTestingStatusEnum::STATEMENT_SENT_TO_REG_LAB->value,
                        'statement_decision' => !($negative_statement_marks > 0),
                        "laborant_answered" => $negative_statement_marks > 0 ? 2 : 1,
                        "laborant_answered_at" => Carbon::now(),
                        "lab_id" => $user->id
                    ]);

                return $saved_data;
            });
        } catch (\Exception $e) {
            throw new GeneralJsonException(message: $e->getMessage(), code: 500, previous: $e);
        }
    }

    public function getFirstChosenTestType(array $filter)
    {
        try {
            return $this->labCheckListRepository->getFirstChosenTestType(filter: $filter);
        } catch (\Exception $e) {
            throw new ModelNotFoundException(message: $e->getMessage(), code: 404);
        }
    }

    public function saveQuestionConditions(array $data)
    {
        return DB::transaction(fn() => $this->labCheckListRepository->saveQuestionConditions($data));
    }

    public function getStatement($id)
    {
        return $this->labCheckListRepository->getLabCheckListStatement($id);
    }

    public function getSchmidtReadyTests(array $filter)
    {
        $statement = $this->labCheckListRepository->getLabCheckListStatement($filter['statement_id']);
        $first_chosen_type = $this->labCheckListRepository->getFirstChosenTestTypeById($statement->lab_check_list_chosen_testing_type_id);
        $conditions = $first_chosen_type->load([
            'conditions' => function ($query) {
                $query->where('onix_status', LabCheckListTestingOnixtStatusEnum::READY_TO_STATEMENT->value);
                $query->where('schmidt_status', LabCheckListTestingSchmidtStatusEnum::READY_TO_STATEMENT->value);
            },
            'conditions.mainInfos' => function ($query) {
                $query->where('lab_check_list_testing_type_id', 1)->orderBy('id', 'asc');
            },
            'conditions.mainInfos.testingQuestion.marks.markAverage'
        ]);
        $average_marks = $conditions->conditions
            ->flatMap(fn($condition) => $condition->mainInfos
                ->flatMap(fn($mainInfo) => $mainInfo->testingQuestion
//                    ->flatMap(fn($question) => $question->marks
//                        ->flatMap(fn($mark) => $mark->marksAverage
//                        )
//                    )
                )
            )
            ->filter(fn($value) => !empty($value))
            ->values();

        return [
            'average_marks' => $average_marks
        ];
    }

    public function getStatementsByFilters(array $filters)
    {
        return $this->labCheckListRepository->getStatementsByFilters(filters: $filters);
    }

    public function getSchemePdf($main_info_id)
    {
        $main_info = $this->labCheckListRepository->getMainInfoById($main_info_id);
        $object = $main_info->load('belongedObject')->belongedObject;
        $region_inspection = Inspection::query()->where('region_id', $object->region_id)->first();
        $work_type_question = $main_info->load('workTypeQuestion')->workTypeQuestion;
        $user = ArticleUser::query()->where('article_id', $object->id)
            ->where('role_id', UserRoleEnum::LABORANT->value)
            ->with('user')
            ->first()->user;
        $customer= ArticleUser::query()->where('article_id', $object->id)
            ->where('role_id', UserRoleEnum::BUYURTMACHI->value)
            ->with('user')
            ->first()->user;
        $questions = $main_info->load(['testingQuestion' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }, 'testingQuestion.marks' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }, 'testingQuestion.marks.marksChild' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])->testingQuestion;
        if ($main_info->lab_check_list_testing_type_id == LabCheckListTestingCondition::ONIX) {
            $name = "onix-" . $main_info->id;
            $pdf = Pdf::loadView('lab-pdf.onix', compact('object', 'customer', 'region_inspection', 'questions', 'main_info', 'work_type_question', 'user'));
        } else {
            $name = "schmidt-" . $main_info->id;
            $pdf = Pdf::loadView('lab-pdf.schmidt', compact('region_inspection','customer', 'object', 'questions', 'main_info'));
        }
        return $pdf->stream("$name.pdf");
    }

}
