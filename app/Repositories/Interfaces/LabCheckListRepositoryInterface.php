<?php

namespace App\Repositories\Interfaces;

use App\Models\LabCheckListTestingQuestion;

interface LabCheckListRepositoryInterface
{
    public function getLabCheckListTestingTypes();

    public function getQuestionMarks(array $filter);

    public function getMarkIndicators(array $filter);

    public function saveMainInfo(array $data);

    public function getTestingCondition($id);

    public function saveStatementMark(array $mark);

    public function getBlockTests(array $filter);

    public function getNotReadyStatusConditionsByFirstChosenId($id);

    public function getMainInfoById($main_info_id);

    public function saveQuestion(array $data);

    public function saveQuestionMarks(LabCheckListTestingQuestion $saved_question, array $question_data, $type);

    public function saveMarkIndicator(array $indicator);

    public function saveLabCheckListStatement(array $data);

    public function getLabCheckListStatement($id);
    public function getStatementsByFilters(array $filters);
    public function getFirstChosenTestTypeById($id);

    public function saveFirstChosenTestType(array $data);

    public function getFirstChosenTestType(array $filter);

    public function saveQuestionConditions(array $data);

    public function getConditions($id);

    public function getConditionsByChosenId($id);

}
