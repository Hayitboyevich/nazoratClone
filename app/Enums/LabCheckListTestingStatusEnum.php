<?php

namespace App\Enums;

enum LabCheckListTestingStatusEnum: int
{
    case INITIAL_STATUS = 1;
    case SCHMIDT_SCHEME_MARKS_CREATED = 2;
    case BOTH_SCHEME_CREATED = 5;
    case STATEMENT_FIRST_CHAPTER_CREATED = 6;
    case STATEMENT_SENT_TO_REG_LAB = 7;
    case STATEMENT_ACCEPTED = 8;
    case STATEMENT_REJECTED = 9;
//    case STATUS_RETURNED = 2;
}
