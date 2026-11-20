<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Oldin kiritilgan asboblarni sinov turlariga boshlang'ich bog'lash: asbob turi (lab_instruments.type) bo'yicha.
 * Faqat hali hech qaysi sinov turiga bog'lanmagan asboblarga tegadi; sinov turlari kodi (lab_methods.code) bo'yicha
 * olinadi - bazada yo'q kodlar tashlab ketiladi. Keyin metrolog har bir asbobning ro'yxatini o'zi to'g'rilaydi.
 */
return new class extends Migration
{
    private const ALL = ['grunt', 'sheben', 'gps', 'kub', 'kern', 'oniks', 'armatura', 'gisht', 'metall'];

    /** asbob turi => sinov turlari kodlari */
    private const MAP = [
        1 => ['grunt', 'sheben', 'gps', 'armatura', 'gisht'],   // Tarozi
        2 => ['kub', 'kern', 'armatura', 'gisht', 'metall'],    // Metall chizg'ich
        3 => ['armatura', 'metall'],                            // Ruletka
        4 => ['kub', 'kern', 'armatura', 'gisht', 'metall'],    // Shtangensirkul
        5 => ['kern', 'metall'],                                // Shtangenglubinomer
        6 => ['armatura', 'metall'],                            // Indikator
        7 => ['armatura', 'metall'],                            // Mikrometr
        8 => ['kub', 'kern', 'armatura', 'gisht', 'metall'],    // Press (sinov mashinasi)
        9 => ['armatura', 'metall'],                            // Ekstenzometr
        10 => self::ALL,                                        // Termogigrometr - sinov sharoiti
        11 => ['grunt', 'sheben', 'gps', 'armatura'],           // Sekundomer
        12 => ['grunt', 'sheben', 'gps', 'gisht'],              // Quritish pechi
        13 => self::ALL,                                        // Termometr - sinov sharoiti
        14 => ['armatura', 'metall'],                           // Burchak o'lchagich
    ];

    public function up(): void
    {
        $methods = DB::table('lab_methods')->whereNull('deleted_at')->pluck('id', 'code');
        if ($methods->isEmpty()) return;

        $instruments = DB::table('lab_instruments')
            ->whereNull('deleted_at')
            ->whereNotIn('id', DB::table('lab_instrument_methods')->select('lab_instrument_id'))
            ->get(['id', 'type']);

        $rows = [];
        foreach ($instruments as $instrument) {
            foreach (self::MAP[(int)$instrument->type] ?? [] as $code) {
                if (isset($methods[$code])) {
                    $rows[] = ['lab_instrument_id' => $instrument->id, 'lab_method_id' => $methods[$code]];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('lab_instrument_methods')->insert($chunk);
        }
    }

    public function down(): void
    {
        // bog'lanishlar keyin metrolog tomonidan o'zgartirilgan bo'lishi mumkin - avtomatik qaytarilmaydi
    }
};
