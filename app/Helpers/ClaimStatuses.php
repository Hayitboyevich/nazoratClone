<?php

namespace App\Helpers;

class ClaimStatuses
{
    const RESPONSE_NEW = 0;
    const RESPONSE_WATCHED = 2;
    const RESPONSE_PROCESSING = 33;
    const RESPONSE_ERRORED = 5;

    const TASK_STATUS_ACCEPTANCE = 1; // Jarayonda
    const TASK_STATUS_REJECTED = 15; //Inspeksiya Rad etilgan
    const TASK_STATUS_CONFIRMED = 20; //Ruxsat berilgan
    const TASK_STATUS_ANOTHER = 2; //Tasdiqlashga tasdiqlanmagan
    const TASK_STATUS_CANCELLED = 10; //Bekor qilingan
    const TASK_STATUS_ATTACH_OBJECT = 3;
    const TASK_STATUS_SENT_ORGANIZATION = 4;
    const RESEND = 30;
    const TASK_STATUS_INSPECTOR = 5;
    const TASK_STATUS_SENT_ANOTHER_ORG = 11;
    const TASK_STATUS_OPERATOR = 12;
    const TASK_STATUS_DIRECTOR = 13;
    const TASK_STATUS_ORGANIZATION_REJECTED = 9;
    const PAY_PROGRESS = 25;
    const TASK_STATUS_UNAVAILABLE = 99;

    public static function getLabel($status): string
    {
        switch ($status) {
            case self::TASK_STATUS_ACCEPTANCE:
                return 'Yangi';
            case self::TASK_STATUS_REJECTED:
                return 'Inspeksiya rad qildi ';
            case self::TASK_STATUS_CONFIRMED:
                return 'Ruxsat berilgan';
            case self::TASK_STATUS_CANCELLED:
                return 'Bekor qilingan';
            case self::TASK_STATUS_ATTACH_OBJECT:
                return 'Obyekt biriktirish';
            case self::TASK_STATUS_SENT_ORGANIZATION:
                return 'Tashkilotlarga yuborildi';
            case self::TASK_STATUS_ORGANIZATION_REJECTED:
                return 'Tashkilot rad etdi';
            case self::TASK_STATUS_OPERATOR:
                return 'Operatorda';
            case self::RESEND:
                return 'Taxrirlashga qaytarilgan';
            case self::TASK_STATUS_DIRECTOR:
                return 'Boshliq ko\'rmoqda';
            case self::PAY_PROGRESS:
                return 'To\'lov kutilmoqda';
            case self::TASK_STATUS_SENT_ANOTHER_ORG;
                return 'Boshqarmaga yuborilgan';
            case self::TASK_STATUS_INSPECTOR:
                return 'Inspektorda';
            default:
                return 'Noma’lum tur';
        }
    }
}
