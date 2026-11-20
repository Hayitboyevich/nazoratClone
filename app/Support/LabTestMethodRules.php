<?php

namespace App\Support;

/**
 * Sinov turiga (lab_methods.code) xos input_data qoidalari. Sinov endpointlari barcha turlar uchun umumiy:
 * bu yerda qoidasi yo'q tur uchun input_data tekshirilmaydi - yangi sinov turi backend kodisiz ishlaydi,
 * tuzilishi aniq bo'lgach shu yerga bitta metod qo'shiladi.
 * Hisob-kitob frontendda bajariladi - bu yerda faqat tuzilish (majburiy kalitlar, turlar) tekshiriladi.
 */
class LabTestMethodRules
{
    /** @return array{0: array, 1: array} [qoidalar, xabarlar] */
    public static function for(?string $code, array $input): array
    {
        return match ($code) {
            'gisht' => self::gisht($input),
            default => [[], []],
        };
    }

    /**
     * G'isht (OʻzMSt 705:2025): bitta bayonnomada bir nechta partiya, har birida o'z turi, markasi va sinovlari.
     * quantity - takroriy bo'lmagan partiyalar soni.
     */
    private static function gisht(array $input): array
    {
        $parts = collect($input['input_data']['parts'] ?? [])->filter(fn($p) => is_array($p));
        $own = $parts->filter(fn($p) => empty($p['retestOf']))->count();

        return [[
            'input_data.parts' => 'required|array|min:1',
            'input_data.parts.*.no' => 'required|integer|min:1',
            'input_data.parts.*.type' => 'required|string|max:50',
            'input_data.parts.*.marka' => 'required|numeric',
            // t1..t10: partiya bo'yicha bayonnomaga chiqadigan sinovlar
            'input_data.parts.*.tests' => 'required|array',
            // o'lchov jadvallari: geo, wat, den, comp, bend, hol
            'input_data.parts.*.data' => 'nullable|array',
            'input_data.parts.*.retestOf' => 'nullable|integer',
            'quantity' => 'in:' . max(1, $own),
        ], [
            'input_data.parts.required' => 'Kamida bitta partiya kiritilishi shart',
            'input_data.parts.*.no.required' => 'Partiya raqami kiritilishi shart',
            'input_data.parts.*.type.required' => 'Partiyaning mahsulot turi tanlanishi shart',
            'input_data.parts.*.marka.required' => 'Partiyaning markasi tanlanishi shart',
            'input_data.parts.*.tests.required' => 'Partiya uchun sinovlar tanlanishi shart',
            'quantity.in' => "Miqdor partiyalar soniga teng bo'lishi kerak (takroriy sinov partiyalari sanalmaydi): $own",
        ]];
    }
}
