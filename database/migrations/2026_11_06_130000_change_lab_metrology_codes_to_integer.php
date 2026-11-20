<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asbob turi, o'lchov birligi va sertifikat hujjat turi matndan enum id (smallint) ga o'tkaziladi.
 * Mavjud yozuvlar ham o'giriladi: LabInstrumentTypeEnum, LabInstrumentUnitEnum, LabCertificateDocTypeEnum.
 */
return new class extends Migration
{
    private const TYPES = [
        'bal' => 1, 'rul' => 2, 'tape' => 3, 'cal' => 4, 'depth' => 5, 'ind' => 6,
        'mic' => 7, 'press' => 8, 'ext' => 9, 'thg' => 10, 'stw' => 11,
    ];

    private const UNITS = [
        'g' => 1, 'kg' => 2, 'mm' => 3, 'm' => 4, 'N' => 5, 'kN' => 6, '°C' => 7, '%' => 8, 's' => 9,
    ];

    private const DOC_TYPES = ['calibration' => 1, 'verification' => 2];

    public function up(): void
    {
        $this->toInteger('lab_instruments', 'type', self::TYPES);
        $this->toInteger('lab_instruments', 'unit', self::UNITS);
        $this->toInteger('lab_instrument_certificates', 'doc_type', self::DOC_TYPES);
    }

    public function down(): void
    {
        $this->toString('lab_instruments', 'type', self::TYPES, 20);
        $this->toString('lab_instruments', 'unit', self::UNITS, 10);
        $this->toString('lab_instrument_certificates', 'doc_type', self::DOC_TYPES, 20);
    }

    private function toInteger(string $table, string $column, array $map): void
    {
        $cases = collect($map)->map(fn($id, $code) => 'WHEN ' . DB::getPdo()->quote($code) . " THEN $id")->implode(' ');
        DB::statement("ALTER TABLE $table ALTER COLUMN $column TYPE smallint USING (CASE $column $cases END)");
    }

    private function toString(string $table, string $column, array $map, int $length): void
    {
        $cases = collect($map)->map(fn($id, $code) => "WHEN $id THEN " . DB::getPdo()->quote($code))->implode(' ');
        DB::statement("ALTER TABLE $table ALTER COLUMN $column TYPE varchar($length) USING (CASE $column $cases END)");
    }
};
