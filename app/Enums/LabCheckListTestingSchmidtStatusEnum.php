<?php

namespace App\Enums;

enum LabCheckListTestingSchmidtStatusEnum: int
{
    case INITIAL_STATUS = 1;
    case MAIN_INFO_CREATED = 2;
    case MAIN_INFO_QUESTIONS_CREATED = 3;
    case READY_TO_STATEMENT = 4;
}
