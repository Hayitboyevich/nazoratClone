<?php
$operatorBlocks = $claim->monitoring->operator_field;

$type1 = collect($operatorBlocks)
    ->first(fn ($item) => ($item['type'] ?? null) == 1);
$type11 = collect($operatorBlocks)
    ->first(fn ($item) => ($item['type'] ?? null) == 11);

$type12 = collect($operatorBlocks)
    ->first(fn ($item) => ($item['type'] ?? null) == 12);
$type13 = collect($operatorBlocks)
    ->first(fn ($item) => ($item['type'] ?? null) == 13);
$type14 = collect($operatorBlocks)
    ->first(fn ($item) => ($item['type'] ?? null) == 14);

?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obyekt haqida ma'lumotlar</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 11px; margin: 20px; color: #000;">

<p style="text-align: center; font-weight: bold; margin: 8px 0;">1. Obyekt haqida ma'lumotlar</p>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
    <tr>
        <td style="border: 1px solid #000; padding: 4px 6px; width: 28%;">Yer uchastkasining kadastr raqami</td>
        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $claim->building_cadastral }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #000; padding: 4px 6px;">Obyekt nomi</td>
        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $claim->object->name }}</td>
    </tr>
</table>




<!-- Section 1.1 -->
@if(isset($type1))

<p style="text-align: center; font-weight: bold; margin: 8px 0;">1.1. Manzil haqida ma'lumotlar</p>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
    <tr>
        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; width: 22%; text-align: center;">Bino va inshoonlar kadastr raqami</td>
        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; width: 22%; text-align: center;">Bino va inshoonlar nomi</td>
        <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Bino va inshoonlar manzili</td>
    </tr>
    <tr>
        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['cadaster'] }}</td>
        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['name'] }}</td>
        <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $claim->building_address }}</td>
    </tr>
</table>

@endif


<!-- Section 2 -->

@if(isset($type11))
    <p style="text-align: center; font-weight: bold; margin: 8px 0;">2. Yer uchastkasi ma'lumotlari</p>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px;">Umumiy Yer maydoni, (kv.m)</td>
            <td style="border: 1px solid #000; padding: 4px 6px; width: 15%; text-align: center;">{{ $type11['block1_total_area'] }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px;">Umumiy qurilish osti maydoni, (kv.m)</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type11['block1_area'] }}</td>
        </tr>
    </table>
@endif

@if(isset($type1))
    <p style="text-align: center; font-weight: bold; margin: 8px 0;">3. Bino va inshootlar ma'lumotlari</p>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 18%;" rowspan="2">Bino va inshoonlar kadastr raqami</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 18%;" rowspan="2">Bino va inshoonlar nomi</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 14%;" rowspan="2">Qurilish holati</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 8%;" rowspan="2">Qavatlar soni</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="4">Maydoni (kv.m.)</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Qurilish osti maydoni</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Umumiy maydoni</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Umumiy foydali maydoni</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">shundan yashash maydoni</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['cadaster'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['name'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">Topshirishga tayyor</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['building_number'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['total_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['total_use_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['living_area'] }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;" colspan="4">Jami</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['total_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['total_use_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type1['living_area'] }}</td>
        </tr>
    </table>
@endif
<!-- Section 3 -->


<!-- Section 4 -->
@if(isset($type12))
    <p style="text-align: center; font-weight: bold; margin: 8px 0;">4. Ko'p kvartirali uydagi kvartiralar tavsifi</p>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 16%;" rowspan="2">Bino va inshoonlar kadastr raqami</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 16%;" rowspan="2">Bino va inshoonlar nomi</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 10%;" rowspan="2">Umumiy foydali maydoni (kv.m.)</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 10%;" rowspan="2">Yashash maydoni (kv.m.)</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 10%;" rowspan="2">Jami kvartiralar soni</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="6">shundan</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">1 xonali</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">2 xonali</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">3 xonali</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">4 xonali</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">5 xonali</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">6 va undan ko'p</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['cadastral_number'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_name'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_total_use_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_living_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['total_room_count'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_1'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_2'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_3'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_4'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_5'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_6_plus'] }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;" colspan="2">Jami</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_total_use_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_living_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['total_room_count'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_1'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_2'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_3'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_4'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_5'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type12['apartment_room_6_plus'] }}</td>
        </tr>
    </table>
@endif


<!-- Section 5 -->
@if(isset($type13))
    <p style="text-align: center; font-weight: bold; margin: 8px 0;">5. Noturar joylar tavsifi</p>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 18%;" rowspan="2">Bino va inshoonlar kadastr raqami</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 18%;" rowspan="2">Bino va inshoonlar nomi</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 9%;" rowspan="2">Qavatlar soni</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 9%;" rowspan="2">Joylashgan qavati</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="3">Maydoni (kv.m.)</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle;" rowspan="2">xonalar soni</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Qurilish osti maydoni</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Umumiy maydoni</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;">Umumiy foydali maydoni</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['cadastral_number'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_name'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_floor_count'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_current_floor'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_total_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_use_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_room_count'] }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;" colspan="4">Jami</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_total_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_use_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type13['unliving_room_count'] }}</td>
        </tr>
    </table>
@endif


<!-- Section 6 -->
@if(isset($type14))
    <p style="text-align: center; font-weight: bold; margin: 8px 0;">6. Umumiy mol-mulklar tavsifi</p>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 14%;" rowspan="3">Bino va inshoonlar kadastr raqami</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 12%;" rowspan="3">Bino va inshoonlar nomi</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center; vertical-align: middle; width: 9%;" rowspan="3">Jami umumiy foydali maydoni (kv.m.)</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="9">shundan</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="4">Kirish yo'laklari (podyezdlar)</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="2">Yerto'lalar</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="2">Cherdaklar</td>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; background-color: #d9d9d9; text-align: center;" colspan="2">Boshqa umumiy mol-mulklar</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">yo'laklar soni</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">xonalar soni</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">maydoni (kv.m.)</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">liftlar soni</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">xonalar soni</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">maydoni (kv.m.)</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">xonalar soni</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">maydoni (kv.m.)</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">xonalar soni</td>
            <td style="border: 1px solid #000; padding: 4px 3px; font-weight: bold; background-color: #d9d9d9; text-align: center;">maydoni (kv.m.)</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['cadastral_number'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['common_name'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['common_total_use_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['common_entrance_number'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['common_total_room_number'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['common_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['elevator_count'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['underground_room_count'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['underground_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['cherdak_room'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['cherdak_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['extra_room'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['extra_area'] }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 4px 6px; font-weight: bold; text-align: center;" colspan="3">Jami</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['common_entrance_number'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['common_total_room_number'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['common_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['elevator_count'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['underground_room_count'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['underground_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['cherdak_room'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['cherdak_area'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['extra_room'] }}</td>
            <td style="border: 1px solid #000; padding: 4px 6px; text-align: center;">{{ $type14['extra_area'] }}</td>
        </tr>
    </table>
@endif


</body>
</html>
