<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ommaviy Oferta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-box-sizing: border-box;
            text-decoration: none;
            list-style-type: none;
        }

        /* Inter src*/

        @page {
            size: A4;
        }

        body {
            font-family: "Times New Roman", "DejaVu Sans", sans-serif;
            font-size: 16px;
            line-height: 22px;
            counter-reset: section;
            padding: 50px;
            text-align: justify;
        }

        .page {
            /*box-sizing: border-box !important;*/
            /*position: relative;*/
            /*!*height: 1050px;*!*/
            /*margin: 50px;*/
            /*page-break-after: always;*/
        }

        .center {
            text-align: center;
        }


        .bold {
            font-weight: 700;
        }

        .subtext {
            font-size: 10px;
            margin-top: -6px;
        }

        .margin {
            margin: 20px 0;
        }

        .word-break {
            word-wrap: break-word; /* eski brauzerlar */
            overflow-wrap: break-word; /* zamonaviy brauzerlar */
            word-break: break-word; /* DOMPDF qo‘llab-quvvatlaydi */
        }

        .indent {
            text-indent: 30px;
        }

        .section {
            counter-increment: section;
            margin-top: 20px;
            counter-reset: subsection;
        }

        .section::before {
            content: counter(section) ". ";
            font-weight: bold;
        }

        .subsection {
            counter-increment: subsection;
        }

        .subsection::before {
            content: counter(section) "." counter(subsection) ".";
            font-weight: bold;
        }

        .seal {
            border-radius: 8px;
            border: 3px solid #18b192;
            background-color: #e7f6f3;
            padding: 5px;
        }
        .page-break {
            page-break-before: always; /* har doim yangi varoqdan */
        }
    </style>
</head>

<body>
<div class="center bold">
    Qurilish obyekti ustidan nazorat funksiyalarini amalga oshirish uchun
    <br/>
    {{ $dxa->task_id }}-son ommaviy oferta
</div>
<table style="width: 100%; padding: 40px 0">
    <tr>
        <td>
            <div style="width: 200px">
                <div>{{ $region->name_uz }}</div>

            </div>
        </td>
        <td></td>
        <td>
            <div style="text-align: right;">
                @if($dxa->confirmed_at)
                    @php
                        $confirmedAt = \Carbon\Carbon::parse($dxa->confirmed_at);
                    @endphp
                    {{ $confirmedAt->format('Y') }} - yil «{{ $confirmedAt->format('m') }}
                    » {{ $confirmedAt->format('d') }}
                @endif
            </div>
        </td>
    </tr>
</table>
<div class="indent">
        <span class="bold">
            {{ $inspection?->name }}
        </span>
    nomidan (keyingi o‘rinlarda — Inspeksiya) “Inspeksiya Nizomi” asosida harakat qiluvchi inspeksiya boshlig‘i
    {{ $dxa?->inspection_full_name ?? $inspection?->full_name }}, bir tomondan
    va {{ $dxa->supervisors()->where('role_id', 8)->first()->organization_name }} (keyingi o‘rinlarda —
    Buyurtmachi) @if($dxa->user_type != 'Jismoniy shaxs')
        uning nomidan nizom asosida harakat
        qiluvchi {{ $dxa?->full_name }}
    @endif ikkinchi tomondan, mazkur ofertani quyidagilar to‘g‘risida tuzdik:
</div>
<div class="center bold margin">Ommaviy oferta predmeti</div>
<div class="indent">
    Mazkur ommaviy oferta qurilish-montaj ishlari ustidan davlat nazorati funksiyalarini amalga oshirish hamda
    qonunchilikda belgilangan qurilish–montaj ishlari qiymatining 0,2 foiz miqdoridagi (qo‘shimcha qiymat
    solig‘isiz) ajratma summasini undirish tartibini belgilaydi.
</div>
<div class="center bold margin section">Inspeksiyaning huquq va majburiyatlari</div>
<span class="subsection">
{{ $dxa->district->name_uz }} {{ str_replace('ʻ', '\'', $dxa->location_building) }} manzilidagi {{ $dxa->object_name }} obyekti bo‘yicha obyektlarning toifalari tasniflanishiga qarab, davlat qurilish nazoratini amalga oshirish davriyligining belgilangan muddatida qurilish-montaj ishlari ustidan nazoratni amalga oshiradi.
</span>
<div></div>
<span class="subsection">
Ommaviy oferta rasmiylashtirilgan sanadan boshlab qurilish obyektida shaharsozlik subyektlari shaharsozlik to‘g‘risidagi qonunchilik hujjatlari, shaharsozlik normalari, me’yorlari va qoidalari, standartlarga rioya qilinishini, qurilish sohasida texnik jihatdan tartibga solishga doir normativ hujjatlar talablariga rioya etilishi bo‘yicha davlat nazoratini amalga oshiradi, yo‘l qo‘yilgan kamchiliklarni bartaraf etish to‘g‘risida ijro etilishi majburiy bo‘lgan yozma ko‘rsatmalar beradi va qonunbuzilish holatlari yuzasidan tegishli choralar ko‘radi.
</span>
<div class="center bold margin section">Buyurtmachining huquq va majburiyatlari</div>
<span class="subsection">
O‘zbekiston Respublikasi shaharsozlik to‘g‘risidagi qonunchilik hujjatlari shaharsozlik faoliyati sohasida texnikaviy tartibga solishning normativ hujjatlari talablariga rioya etilishi, tasdiqlangan loyiha-smeta hujjatlariga muvofiq qurilish-montaj ishlarini bajarishning sifati va to‘liqligi yuzasidan nazoratning amalga oshirganligi uchun “Buyurtmachi” tomonidan inspeksiyaga belgilangan miqdorda ajratma to‘lovlarini o‘z vaqtida amalga oshiradi.
</span>
<div class="indent">
    Smeta hisob-kitobi mavjud bo‘lmagan taqdirda ajratmalar summasi ilgari ro‘yxatdan o‘tkazilgan obyektlardagi kabi
    belgilanadi.
    “Inspeksiya”ga qurilish obyektiga to‘siqlarsiz kirish, loyiha va ushbu obyektning boshqa shaharsozlik hujjatlaridan
    foydalanish va nazoratni amalga oshirish imkoniyatini yaratib beradi.
</div>
<div></div>
<span class="subsection">
“Inspeksiya”ning nazorat funksiyalarini yuritish uchun qurilish-montaj ishlari va qo‘llanilayotgan qurilish materiallari, konstruksiyalar va buyumlarning sifati bo‘yicha paydo bo‘ladigan savollarni hal etish uchun mas’ul vakillar - qurilish ishtirokchilarini ya’ni loyihachining mualliflik nazoratchisini, buyurtmachining texnik nazoratchisini hamda pudrat tashkilotining mas’ul xodimlarini ta’minlaydi.
</span>
<div></div>
<span class="subsection">
Buyurtmachi Ommaviy ofertaning barcha talablariga o‘z vaqtida rioya qilish majburiyatini o‘z zimmasiga oladi, shuningdek bajarilgan qurilish-montaj ishlarining loyiha yechimlariga muvofiqligi va sifati uchun to‘liq javobgar bo‘ladi va “Inspeksiya”ning nazorat funksiyasi uchun o‘z vaqtida ajratmani to‘lanishini kafolatlaydi.
</span>
<div class="center bold margin section">To‘lov shartlari</div>
<span class="subsection">
Ommaviy oferta asosida davlat qurilish nazorati amalga oshirilishi shart bo‘lgan  {{ $dxa->object_name }} obyektda qonunchilikda belgilangan qurilish–montaj ishlari qiymatining 0,2 foiz miqdoridagi (qo‘shimcha qiymat solig‘isiz) ajratma summasi ya’ni (ariza bo‘yicha) {{ $dxa->price_supervision_service }} so‘mni tashkil qiladi.
</span>
<div></div>
<span class="subsection">
“Buyurtmachi” tomonidan ajratma to‘lovlari mazkur oferta hududiy inspeksiyada ro‘yxatga olingan kundan boshlab (byudjet tashkilotlari uchun belgilangan  tartibda G‘aznachilikdan ro‘yxatdan o‘tgandan so‘ng), bir oy muddatda to‘lashi shart.
</span>
<div></div>
<div class="indent">
    Buyurtmachi tomonidan ilgari to‘langan summa o‘zaro hisob-kitoblarda inobatga olinadi.
</div>
<span class="subsection">
Smeta qiymati indeksatsiyalanishi munosabati bilan “Buyurtmachi” qo‘shimcha ravishda qurilish-montaj obyektining amaldagi to‘lovlari va hisobot ma’lumotlari o‘rtasidagi farqni to‘laydi.
</span>
<div class="center bold margin section">Tomonlarning javobgarligi</div>
<span class="subsection">
   Tomonlarning mazkur ofertada nazarda tutilmagan javobgarlik choralari O‘zbekiston Respublikasi hududidagi amaldagi qonunchilik hujjatlari me’yorlariga muvofiq qo‘llaniladi.
</span>
<div class="center bold margin section">Fors-major</div>
<span class="subsection">
Fors-major holatlari paydo bo‘lganida va ular o‘rnatilgan tartibda lozim darajada rasmiylashtirilganda tomonlar mazkur oferta bo‘yicha javobgarlikdan ozod etiladilar.
</span>
<div></div>
<span class="subsection">
Yengib bo‘lmas kuch holatlari rasmiy bartaraf etilganidan so‘ng tomonlar oferta bo‘yicha munosabatlarini qayta tiklaydilar.
</span>
<div class="center bold margin section">Korrupsiyaga qarshi kurashish qoidalari</div>
<span class="subsection">
Taraflar oferta tuzishda, ofertaning amal qilish muddatida va ushbu muddat tugaganidan so‘ng, oferta bilan bog‘liq korrupsiyaviy harakatlarni sodir qilmaslikka kelishib oladilar.
</span>
<div></div>
<span class="subsection">
Taraflar ofertaga korrupsiyaga qarshi qo‘shimcha shartlarda belgilangan korrupsiyaning oldini olish choralarini tan oladi va ularga rioya etilishi bo‘yicha hamkorlikni ta’minlaydilar.
</span>
<div></div>
<span class="subsection">
Agar bir tarafga boshqa tarafning korrupsiyaga qarshi qo‘shimcha shartlarda belgilangan majburiyatlarni buzilishiga yo‘l qo‘yganligi ma’lum bo‘lib qolsa, ikkinchi tarafni bu haqda zudlik bilan xabardor qilishi va ushbu tarafdan oqilona muddat ichida tegishli choralar ko‘rilishini va amalga oshirilgan ishlar yuzasidan unga xabardor qilishini talab qilishi shart.
</span>
<div class="center bold margin section">Umumiy qoidalar</div>
<div></div>
<span class="subsection">
Mazkur Ommaviy ofertani ijro etish, o‘zgartirish va bekor qilishda tomonlar o‘rtasida paydo bo‘ladigan kelishmovchiliklar qonunchilik hujjatlarida o‘rnatilgan tartibda ko‘rib chiqiladi “Inspeksiya” da’vo tartibida tegishli sudlarga murojaat qilishi mumkin.
</span>
<div></div>
<span class="subsection">
Yuridik manzil, bank rekvizitlari o‘zgarganda va boshqa o‘zgarishlarda tomonlar bu haqda bir birlarini 10 kun ichida yozma ravishda xabardor qilish majburiyatini o‘z zimmalariga oladilar.
</span>
<div></div>
<span class="subsection">
Ommaviy ofertaning amal qilishi obyektda qurilish ishlari yakunlanguniga qadar muddatgacha belgilanadi va kuchga ega bo‘ladi.
</span>
<div></div>
<span class="subsection">
Mazkur Ommaviy oferta tomonlarning hisob-kitoblari uchun asos bo‘lib hisoblanadi.
</span>
<div class="page-break"></div>
<div class="center bold margin">Tomonlarning yuridik manzillari, bank rekvizitlari va imzolari:</div>
<div></div>
<div style="padding-top: 20px; margin-bottom: 20px; display: block">
    <div style="width: 48%;  float: left; box-sizing: border-box;">
        <table style="font-size: 14px; width: 100%; border: 1px dotted #000; padding: 10px">
            <tr>
                <td colspan="2">
                    <div style="font-weight: 700;">
                        Inspeksiya
                    </div>
                    <span style="font-size: 14px;">
               {{ $inspection->name }}
           </span>
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700; width: 100px">
                    Manzil:
                </td>
                <td>
                    {{ $inspection->address }}
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    Tel:
                </td>
                <td>
                    {{ $inspection->phone }}
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    G’azna h/r:
                </td>
                <td>
                    {{ $rekvizit->stir }}
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    Bank nomi:
                </td>
                <td>
                    Markaziy bank Toshkent shahar hisob-kitob kassa markazi
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    Bank kodi:
                </td>
                <td>
                    00014
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    STIR:
                </td>
                <td>
                    {{ $inspection->stir }}
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    Boshliq:
                </td>
                <td>
                    <div style="display: inline-block;">
                        <div>{{ $dxa?->inspection_full_name ?? $inspection?->full_name }} </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-top: 7px">
                    <div class="seal center">

                        <div style="text-align: center; font-size: 12px">Elektron tizim orqali</div>
                        <div
                            style="
                                 text-align: center;
                                 margin-top: 5px;
                                 margin-bottom: 5px;
                                 font-size: 17px;
                                "
                        >
                            TASDIQLANDI
                        </div>
                        <div style="font-size: 13px" class="center">{{ $dxa?->inspection_full_name ?? $inspection?->full_name  }}</div>
                        <div
                            style="font-size: 12px; "
                        >
                            <span>{{ $dxa->confirmed_at }}</span>
                        </div>
                    </div>

                </td>
            </tr>
        </table>
    </div>
    <div style="width: 48%;  float: right; box-sizing: border-box;">
        <table style="font-size: 14px; width: 100%; border: 1px dotted #000; padding: 10px;">
            <tr>
                <td colspan="2">
                    <div style="font-weight: 700;">
                        Buyurtmachi
                    </div>
                    <span style="font-size: 14px;">
               {{ $dxa->supervisors()->where('role_id', 8)->first()->organization_name }}
           </span>
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    Manzil:
                </td>
                <td>
                    {{ $dxa->district->name_uz }} {{ $dxa->location_building }}
                </td>
            </tr>
            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    Tel:
                </td>
                <td>
                    {{ $dxa->phone }}
                </td>
            </tr>

            <tr style="vertical-align: top;">
                <td style="font-weight: 700;">
                    STIR:
                </td>
                <td>
                    {{ $dxa->application_stir_pinfl }}
                </td>
            </tr>

            <tr>
                <td colspan="2" style="padding-top: 7px">
{{--                    <div class="seal">--}}
{{--                        <div--}}
{{--                            style="--}}
{{--                                 font-size: 12px;--}}
{{--                                "--}}
{{--                        >--}}
{{--                            <span>{{ $dxa->created_at }}</span>--}}
{{--                        </div>--}}
{{--                        <div--}}
{{--                            style="--}}
{{--                                 text-align: center;--}}
{{--                                 margin-top: 5px;--}}
{{--                                 margin-bottom: 5px;--}}
{{--                                 font-size: 17px;--}}
{{--                                "--}}
{{--                        >--}}
{{--                            TASDIQLANDI--}}
{{--                        </div>--}}
{{--                        <div style="font-size: 13px">{{ $dxa->full_name }}</div>--}}
{{--                    </div>--}}
                    <div class="seal center">

                        <div style="text-align: center; font-size: 12px">Elektron tizim orqali</div>
                        <div
                            style="
                                 text-align: center;
                                 margin-top: 5px;
                                 margin-bottom: 5px;
                                 font-size: 17px;
                                "
                        >
                            TASDIQLANDI
                        </div>
                        <div style="font-size: 13px" class="center">{{ $dxa?->full_name  }}</div>
                        <div
                            style="font-size: 12px; "
                        >
                            <span>{{ $dxa->created_at }}</span>
                        </div>
                    </div>

                </td>
            </tr>
        </table>
    <div style="padding-top: 1px; width: 120px; height: 120px; border: 1px solid #000; margin: 10px auto;">
        <img src="data:image/png;base64, {{$qrImage}}" alt="" style="width: 120px; height: 120px;">
    </div>
    </div>



</div>
</body>
</html>
