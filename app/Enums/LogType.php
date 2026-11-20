<?php


namespace App\Enums;

class LogType
{
    const TASK_HISTORY = 1;
    const CLAIM_HISTORY = 2;
    const COMPLIANCE_HISTORY = 19;
    const CLAIM_RESEND = 20;
    const CLAIM_CONSUMER_CHANGE = 23;
    const ARTICLE_HISTORY = 3;
    const ARTICLE_CREATE_HISTORY = 4;
    const ARTICLE_PRICE_HISTORY = 5;
    const ARTICLE_PRICE_DELETE = 6;
    const ARTICLE_INSPECTOR_HISTORY = 7;
    const ARTICLE_ROTATION = 8;
    const ARTICLE_UPDATE_HISTORY = 9;
    const ARTICLE_MONITORING = 10;
    const ARTICLE_PAYMENT_CREATE = 11;
    const ARTICLE_DEADLINE_CHANGE = 12;
    const ARTICLE_LABORATORY_PRICE = 13;
    const ARTICLE_LABORATORY_PAYMENT = 14;
    const LAB_HISTORY = 15;
    const CHANGE_FUNDING_SOURCE = 16;
    const CHANGE_CONSUMER = 18;

    const CHANGE_OBJECT_LOCATION = 17;
    const BLOCK_DELETE = 200;
    const BLOCK_CREATE = 201;

    const PRESENTATION_CREATE = 50;
    const PRESENTATION_UPDATE_TECHNIC = 51;
    const PRESENTATION_UPDATE_INTERNAL = 52;
    const PRESENTATION_UPDATE_INSPECTOR = 53;
    const PRESENTATION_UPDATE_INSPECTION = 54;
    const PRESENTATION_REJECT_INSPECTION = 55;
    const PRESENTATION_REJECT_INSPECTOR = 56;
    const PRESENTATION_REJECT_INTERNAL = 57;
    const PRESENTATION_REJECT_TECHNIC = 58;
    const PRESENTATION_SEND_INSPECTOR = 59;
    const PRESENTATION_UPDATE_EMPLOYEE = 60;

    const DECLARATION_CREATED = 300;
    const DECLARATION_CUSTOMER = 301;
    const DECLARATION_REJECTED = 302;
    const DECLARATION_CONFIRMED = 303;
    const DECLARATION_UPDATED = 304;
    const DECLARATION_CONSUMER = 305;
    const DECLARATION_CONSUMER_CHANGE = 306;
    const DXA_CREATED = 400;
    const SEND_INSPECTOR = 401;
    const SEND_REGISTER = 402;
    const SEND_UPDATE = 403;
    const SEND_INSPECTION = 404;
    const DXA_REJECTED = 405;
    const DXA_ACCEPTED = 406;
    const DXA_CANCELLED = 407;
    const DXA_RESEND = 408;

    const HIDDEN_WORK_CREATE = 420;
    const HIDDEN_WORK_ACCEPT = 421;
    const HIDDEN_WORK_UPDATE = 422;
    const HIDDEN_WORK_REJECT = 423;

    // laboratoriya: metrolog (lab_histories jadvali)
    const LAB_INSTRUMENT_CREATE = 500;
    const LAB_INSTRUMENT_UPDATE = 501;
    const LAB_INSTRUMENT_STATUS = 502;
    const LAB_INSTRUMENT_DELETE = 503;
    const LAB_CERTIFICATE_CREATE = 510;
    const LAB_CERTIFICATE_UPDATE = 511;
    const LAB_CERTIFICATE_STATUS = 512;
    const LAB_CERTIFICATE_DELETE = 513;

    public static function labInstrumentTypes(): array
    {
        return [self::LAB_INSTRUMENT_CREATE, self::LAB_INSTRUMENT_UPDATE, self::LAB_INSTRUMENT_STATUS, self::LAB_INSTRUMENT_DELETE];
    }

    public static function labCertificateTypes(): array
    {
        return [self::LAB_CERTIFICATE_CREATE, self::LAB_CERTIFICATE_UPDATE, self::LAB_CERTIFICATE_STATUS, self::LAB_CERTIFICATE_DELETE];
    }

    public static function getLabLabel($type): string
    {
        return match ((int)$type) {
            self::LAB_INSTRUMENT_CREATE => 'Asbob qo\'shildi',
            self::LAB_INSTRUMENT_UPDATE => 'Asbob tahrirlandi',
            self::LAB_INSTRUMENT_STATUS => 'Asbob holati o\'zgartirildi',
            self::LAB_INSTRUMENT_DELETE => 'Asbob o\'chirildi',
            self::LAB_CERTIFICATE_CREATE => 'Sertifikat qo\'shildi',
            self::LAB_CERTIFICATE_UPDATE => 'Sertifikat tahrirlandi',
            self::LAB_CERTIFICATE_STATUS => 'Sertifikat holati o\'zgartirildi',
            self::LAB_CERTIFICATE_DELETE => 'Sertifikat o\'chirildi',
            default => 'Noma’lum tur',
        };
    }






    public static function getLabel($type): string
    {
        switch ($type) {
            case self::ARTICLE_HISTORY:
                return 'Status o\'zgartirildi';
            case self::ARTICLE_CREATE_HISTORY:
                return 'Obyekt yaratildi';
            case self::ARTICLE_PRICE_HISTORY:
                return 'Obyekt to\'lov miqdori o\'zgartirildi';
            case self::ARTICLE_PRICE_DELETE:
                return 'Obyektda to\'lov miqdori o\'chirildi';
            case self::ARTICLE_ROTATION:
                return 'Obyektda inspektor rotatsiya qilindi';
            case self::ARTICLE_UPDATE_HISTORY:
                return 'Obyekt qayta rasmiylashtirildi';
            case self::ARTICLE_INSPECTOR_HISTORY:
                return 'Obyektda inspektor o\'zgardi';
            case self::ARTICLE_MONITORING:
                return 'Obyektda monitoring o\'tkazildi';
            case self::CHANGE_OBJECT_LOCATION:
                return 'Obyektda lokatsiya o\'zgartirildi';
            case self::ARTICLE_DEADLINE_CHANGE:
                return 'Obyekt muddati o\'zgartirildi';
            case self::ARTICLE_PAYMENT_CREATE:
                return 'To\'lov miqdori amalga oshirildi';
            case self::ARTICLE_LABORATORY_PRICE:
                return 'Laboratoriya to\'lov miqdori kiritildi';
            case self::ARTICLE_LABORATORY_PAYMENT:
                return 'Laboratoriya to\'lovi amalga oshirildi';
            case self::CHANGE_FUNDING_SOURCE:
                return 'Moliyalashtirish manbai o\'zgartirildi';
            case self::BLOCK_DELETE:
                return 'Obyektdan blok  o\'chirildi';
            case self::BLOCK_CREATE:
                return 'Obyektga yangi blok  qo\'shildi';
            case self::CHANGE_CONSUMER:
                return 'Obyektda foydalanuvchi o\'zgartirildi';
            default:
                return 'Noma’lum tur';
        }
    }

    public static function getPresentationLabel($type): string
    {
        switch ($type) {
            case self::PRESENTATION_CREATE:
                return 'Taqdimnoma yaratildi';
            case self::PRESENTATION_UPDATE_TECHNIC:
                return 'Taqdimnoma texnik nazorat tomonidan tasdiqlandi';
            case self::PRESENTATION_UPDATE_INTERNAL:
                return 'Taqdimnoma ichki nazorat tomonidan tasdiqlandi';
            case self::PRESENTATION_UPDATE_INSPECTOR:
                return 'Taqdimnoma inspektsiya xodimiga yuborildi';
            case self::PRESENTATION_UPDATE_INSPECTION:
                return 'Yuborildi';
            case self::PRESENTATION_REJECT_TECHNIC:
                return 'Taqdimnoma texnik nazorat tomonidan rad qilindi';
            case self::PRESENTATION_REJECT_INTERNAL:
                return 'Taqdimnoma ichki nazorat tomonidan rad qilindi';
            case self::PRESENTATION_REJECT_INSPECTOR:
                return 'Taqdimnoma inspektor tomonidan rad qilindi';
            case self::PRESENTATION_SEND_INSPECTOR:
                return 'Taqdimnoma inspektorga qaytarildi';
            case self::PRESENTATION_UPDATE_EMPLOYEE:
                return 'Taqdimnoma inspektsiya boshlig\'iga  yuborildi';

            case self::PRESENTATION_REJECT_INSPECTION:
                return 'Rad qilindi';
            default:
                return 'Noma’lum tur';
        }
    }

    public static function getDeclarationLabel($type): string
    {
        switch ($type) {
            case self::DECLARATION_CREATED:
                return 'Deklaratsiya yaratildi';
            case self::DECLARATION_UPDATED:
                return 'Deklaratsiya tahrirlandi ';
            case self::DECLARATION_REJECTED:
                return 'Deklaratsiya rad qilindi';
            case self::DECLARATION_CONFIRMED:
                return 'Deklaratsiya qabul qilindi';
            case self::DECLARATION_CUSTOMER:
                return 'Deklaratsiya tasdiqlandi';
            case self::DECLARATION_CONSUMER:
                return 'Foydalanuvchi qoshildi';
            case self::DECLARATION_CONSUMER_CHANGE:
                return 'Foydalanuvchi o\'zgartirildi';
            default:
                return 'Noma’lum tur';
        }
    }

    public static function getHiddenWorkLabel($type): string
    {
        switch ($type) {
            case self::HIDDEN_WORK_CREATE:
                return 'Yopiq ishlar dalolatnomasi yaratildi';
            case self::HIDDEN_WORK_UPDATE:
                return 'Yopiq ishlar dalolatnomasi tahrirlandi ';
            case self::HIDDEN_WORK_REJECT:
                return 'Yopiq ishlar dalolatnomasi rad qilindi';
            case self::HIDDEN_WORK_ACCEPT:
                return 'Yopiq ishlar dalolatnomasi tasdiqlandi';

            default:
                return 'Noma’lum tur';
        }
    }

    public static function getDxaLabel($type): string
    {
        switch ($type) {
            case self::DXA_CREATED:
                return 'Ariza yaratildi';
            case self::SEND_INSPECTOR:
                return 'Ariza inspektorga yuborildi';
            case self::SEND_REGISTER:
                return 'Ariza registratorga qaytarildi';
            case self::SEND_INSPECTION:
                return 'Ariza inspeksiya boshlig\'iga  yuborildi';
            case self::DXA_REJECTED:
                return 'Ariza rad qilindi';
            case self::DXA_CANCELLED:
                return 'Ariza bekor qilindi';
            case self::SEND_UPDATE:
                return 'Ariza taxrirlashga yuborildi';
            case self::DXA_ACCEPTED:
                return 'Ariza qabul qilindi';
            case self::DXA_RESEND:
                return 'Ariza taxrirlandi';
            default:
                return 'Noma’lum tur';
        }
    }

}
