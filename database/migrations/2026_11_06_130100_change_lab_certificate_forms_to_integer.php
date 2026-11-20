<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sertifikatning value_form va deviation_meaning ustunlari matndan enum id (smallint) ga o'tkaziladi.
 * Mavjud yozuvlar ham o'giriladi: LabCertificateValueFormEnum, LabCertificateDeviationMeaningEnum.
 */
return new class extends Migration
{
    private const VALUE_FORMS = ['abs' => 1, 'pct' => 2];

    private const DEVIATION_MEANINGS = ['corr' => 1, 'dev' => 2];

    public function up(): void
    {
        $this->toInteger('value_form', self::VALUE_FORMS, 1);
        $this->toInteger('deviation_meaning', self::DEVIATION_MEANINGS, 1);
    }

    public function down(): void
    {
        $this->toString('value_form', self::VALUE_FORMS, 'abs');
        $this->toString('deviation_meaning', self::DEVIATION_MEANINGS, 'corr');
    }

    private function toInteger(string $column, array $map, int $default): void
    {
        $cases = collect($map)->map(fn($id, $code) => 'WHEN ' . DB::getPdo()->quote($code) . " THEN $id")->implode(' ');
        DB::statement("ALTER TABLE lab_instrument_certificates ALTER COLUMN $column DROP DEFAULT");
        DB::statement("ALTER TABLE lab_instrument_certificates ALTER COLUMN $column TYPE smallint USING (CASE $column $cases END)");
        DB::statement("ALTER TABLE lab_instrument_certificates ALTER COLUMN $column SET DEFAULT $default");
    }

    private function toString(string $column, array $map, string $default): void
    {
        $cases = collect($map)->map(fn($id, $code) => "WHEN $id THEN " . DB::getPdo()->quote($code))->implode(' ');
        DB::statement("ALTER TABLE lab_instrument_certificates ALTER COLUMN $column DROP DEFAULT");
        DB::statement("ALTER TABLE lab_instrument_certificates ALTER COLUMN $column TYPE varchar(5) USING (CASE $column $cases END)");
        DB::statement("ALTER TABLE lab_instrument_certificates ALTER COLUMN $column SET DEFAULT " . DB::getPdo()->quote($default));
    }
};
