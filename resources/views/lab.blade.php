<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shartnoma</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-box-sizing: border-box;
            text-decoration: none;
            list-style-type: none;
        }

        @page {
            size: A4;
        }

        body {
            font-family: "Times New Roman", "DejaVu Sans", sans-serif;
            font-size: 14px;
            line-height: 18px;
            counter-reset: section;
            padding: 50px;
        }

        .page-break {
            page-break-before: always; /* har doim yangi varoqdan */
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

        .indent {
            text-indent: 30px;
        }

        .section {
            counter-increment: section;
            margin-top: 16px;
            counter-reset: subsection;
        }

        .section::before {
            content: counter(section, upper-roman) ". ";
            font-weight: bold;
        }

        .subsection {
            counter-increment: subsection;
            counter-reset: subsubsection;
            margin-left: 20px;
        }

        .subsection::before {
            content: counter(section) "." counter(subsection) ". ";
            font-weight: bold;
        }

        .collapsed-table {
            border-collapse: collapse;
            width: 100%;
        }

        .collapsed-table td,
        .collapsed-table th {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }

        .seal {
            border-radius: 8px;
            padding: 5px;
        }

        .confirm-seal {
            border: 3px solid #18b192;
            background-color: #e7f6f3;
        }

        .canceled-seal {
            border: 3px solid #cd1323;
            background-color: rgba(239, 167, 167, 0.58);
        }


        .header {
            text-align: right;
            font-size: 10px;
            margin-bottom: 15px;
            font-style: italic;
        }

        .title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            background-color: #f2f2f2;
            padding: 4px;
        }

        table.new-table {
            font-size: 8px;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table.new-table,
        table.new-table th,
        table.new-table td {
            border: 1px solid #000;
        }

        table.new-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
            padding: 2px;
        }

        table.new-table td {
            padding: 2px;
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .total-box {
            margin-top: 10px;
            text-align: right;
            font-weight: bold;
            font-size: 11px;
        }

        .formula-box {
            margin-top: 15px;
            padding: 10px;
            border: 1px dashed #666;
            background-color: #fafafa;
        }

        .footer-page {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>
@if(in_array($lab_claim->lab_test_category_id, [2,120]))
    <body>
    <div class="center bold">
        Bino va inshootlarni instrumental texnik tekshiruvdan oʻtkazish toʻgʻrisida
        <br/>
        SHARTNOMA № @if($lab_claim->type == 1)
            {{$contract->contract_number}}
        @else
            {{ $contract->id }}
        @endif
    </div>
    <table style="width: 100%; padding-top: 40px">
        <tr class="bold">
            <td>
                {{$customer_confirmed_at}}
            </td>
            <td></td>
            <td style="text-align: right;">
                {{$lab_claim?->district?->name_uz}}
            </td>
        </tr>
    </table>
    <div class="indent">
        {{$region?->name_uz}} hududiy Qurilish va uy-joy kommunal xoʻjaligi sohasida nazorat qilish inspeksiyasi (keyingi
        oʻrinlarda "Ijrochi" deb yuritiladi) nomidan Oʻzbekiston Respublikasi Vazirlar Mahkamasining 2024-yil
        17-apreldagi 214-sonli qaroriga asosan tasdiqlangan Oʻzbekiston Respublikasi Qurilish va uy-joy kommunal
        xoʻjaligi vazirligi huzuridagi {{$region?->name_uz}} hududiy Qurilish va uy-joy kommunal xoʻjaligi
        sohasida nazorat
        qilish inspeksiyasi ustavi asosida faoliyat yurituvchi inspeksiya boshligʻi <span
            class="bold">
            @if($lab_claim->manually_created)
                {{ $inspection_director->full_name }}
            @else
                {{ $inspection_director->surname }} {{ $inspection_director->name }} {{ $inspection_director->middle_name }}
            @endif
            </span>
        tomondan,
        @if($lab_claim->claim_type === 'Y')
            <span class="bold">{{$lab_claim->labClaimUsers->first()?->user->organization_name}}
            </span> rahbari nomidan
            <span class="bold">
                {{!empty($lab_claim->labClaimUsers->first()->user->director_full_name)? $lab_claim->labClaimUsers->first()->user->director_full_name : $contract->director_full_name}}
            </span>
            (keyingi
            oʻrinlarda “Buyurtmachi” deb
            yuritiladi)
        @else
            <span class="bold">
                {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 0, 1, 'UTF-8'))
                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}}
                {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'))
                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 1, null, 'UTF-8'))}}
                {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'))
                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 1, null, 'UTF-8'))}}
            </span>
            (keyingi oʻrinlarda “Buyurtmachi” deb
            yuritiladi)
        @endif
        ,ikkinchi
        tomondan mazkur shartnomani quyidagilar haqida tuzdilar.
    </div>


    <div class="indent">
        Mazkur ommaviy oferta qurilish-montaj ishlari ustidan davlat nazorati funksiyalarini amalga oshirish hamda
        qonunchilikda belgilangan qurilish–montaj ishlari qiymatining 0,2 foiz miqdoridagi (qo‘shimcha qiymat
        solig‘isiz) ajratma summasini undirish tartibini belgilaydi.
    </div>


    <div class="center bold margin section">Shartnoma mazmuni:</div>
    <span class="subsection">
        Mazkur Shartnoma Oʻzbekiston Respublikasi Vazirlar Mahkamasining 2020-yil 20-avgustdagi 496-sonli Qarori va
        Oʻzbekiston Respublikasi Adliya vazirligidan 28.03.2022-yilda 3356-raqam bilan roʻyxatdan oʻtgan Oʻzbekiston
        Respublikasi Qurilish vazirining 10.03.2022-yildagi “Qurilishi sohasida hududiy nazorat inspeksiyalari tomonidan
        oʻtkaziladigan bino va inshootlarning instrumental texnik tekshiruv ishlarini baholashning yagona hisoblash
        tartibi toʻgʻrisidagi nizomni tasdiqlash haqida”gi 38-sonli buyruqlariga amal qilgan holda “Ijrochi”
        “Buyurtmachi”ning yozma murojaatnomasiga asosan uning texnik holatini baholash ishlarini amalga oshiradi va
        <b>{{$lab_claim?->region?->name_uz}}, {{$lab_claim?->district?->name_uz}}, {{$lab_claim?->address}} hududida joylashgan "{{$lab_claim->object_name}}"</b>
        binosini instrumental texnik holatini tekshirish ishlari xizmatini amaldagi qonun-qoidalar va meʼyoriy hujjatlar
        asosida oʻtkazib instrumental texnik tekshiruv xulosasi (keyingi oʻrinlarda xulosa) berilishini oʻz zimmasiga
        oladi.
    </span>
    <div></div>
    <div class="center bold margin section">Shartnoma bahosi:</div>
    <span class="subsection">
        Shartnoma boʻyicha Bino va inshootlarni instrumental texnik tekshiruvdan oʻtkazishning umumiy summasi (QQS
        bilan) <span class="bold">{{$contract_full_price}}</span> soʻmni tashkil
        etadi, obyektni joyiga chiqib oʻrganish natijasida, instrumental texnik tekshiruv narxlarida oʻzgarishlar
        boʻlgan hollarda, taraflarning qoʻshimcha kelishuviga koʻra shartnomaning aniq summasi belgilanadi. Obyektni
        joyiga chiqib oʻrganish talab qilinmaydigan holatlarda, ushbu bandda qayd etilgan summa shartnomaning aniq
        summasi hisoblanadi.
    </span>
    <div></div>
    <span class="subsection">
        Shartnoma summasining 50 foizidan kam boʻlmagan miqdordagi toʻlov shartnoma imzolangan kundan kamda 5 bank kuni
        ichida “Buyurtmachi” tomonidan amalga oshirilganidan soʻng, Bino va inshootlarni instrumental texnik tekshiruv
        ishlari boshlanadi.
    </span>
    <div></div>
    <div class="page-break"></div>
    <div class="center bold margin section">Tomonlarning huquq va majburiyatlari:</div>
    <span class="subsection">
        Obyektda texnik tekshiruv ishlari yakunlanib, instrumental texnik tekshiruv narxlari aniqlangandan soʻng,
        Ijrochi tomonidan taqdim etilgan hisob-faktura asosida Buyurtmachi Ijrochining hisob raqamiga belgilangan
        toʻlovni toʻliq oʻtkazib beradi.
    </span>
    <div></div>
    <span class="subsection">
        “Ijrochi” tomonidan bajariladigan ishlarni mavjud meʼyoriy hujjatlar talablari asosida sifatli bajarib, ish
        natijalari toʻgʻrisidagi hujjatni (yigʻma jild), “Buyurtmachi” tomonidan “Ijrochi”ga ish bajarish uchun barcha
        yetarli maʼlumotlar taqdim etilgandan soʻng va hisob raqamiga 2.2 bandida koʻrsatilgan mablagʻ tushirilganidan
        soʻng 30 kun muddat ichida “Buyurtmachi”ga taqdim etadi.
    </span>
    <div></div>
    <span class="subsection">
        “Buyurtmachi” bino va inshootlarni texnik holatini tekshirish va sinov ishlarini olib borish uchun amaldagi
        meʼyoriy xujjatlarda talab etilgan barcha xujjat va maʼlumotlarni shartnoma tuzilgan kundan boshlab 3bank kuni
        ichida “Ijrochi”ga taqdim etadi.
    </span>
    <div></div>
    <span class="subsection">
        “Buyurtmachi” qurilish obyektiga tekshiruv maydonlariga kirish imkoni, agar bino qavatining balandligi yuqori
        boʻlsa, yuqorida turgan konstruksiyani oʻrganish uchun yetarli darajada imkoniyat yaratish va (agarda binoda
        pardozlash ishlari boʻlgan taqdirda) konstruksiyaning ochilishini taʼminlaydi, majburiyatlar bajarilmagan
        taqdirda, “Ijrochi” ushbu shartnomaning 3.2 bandini bajarish uchun javobgar boʻlmaydi;
    </span>
    <div></div>
    <span class="subsection">
        “Buyurtmachi” “Ijrochi”ga taqdim etilgan maʼlumotlarning toʻliqligi va haqqoniyligi uchun javobgardir.
    </span>
    <div></div>
    <span class="subsection">
        “Ijrochi” tomonidan bino va inshootlarni texnik holatini tekshirish va sinov ishlari belgilangan muddatda
        yakunlanib, “Buyurtmachi” tomonidan qabul qilingan hisob-faktura asosida 3bank kuni ichida toʻlovlar toʻliq
        amalga oshirilgandan soʻng tegishli xulosalar tasdiqlanib taqdim qilinadi.
    </span>
    <div></div>
    <span class="subsection">
        “Ijrochi” shartnoma asosida oʻziga yuklatilgan barcha vazifalarni toʻliq va oʻz vaqtida bajarishga masʼul
        hisoblanadi.
    </span>
    <div></div>
    <div class="center bold margin section">Bajariladigan xizmat narxi va hisob-kitob tartibi:</div>
    <span class="subsection">
        Bajarilgan mazkur ishlar narxini “Buyurtmachi” “Ijrochi”ga texnik tekshiruv ishlarini bajarish narxlari asosida
        berilgan xisob-faktura asosida toʻlovlarni amalga oshiradi. Kelishilgan shartnomaviy narx shartnomaning 2.1
        bandidagi summani tashkil etadi.
    </span>
    <div></div>
    <span class="subsection">
        Toʻlovni amalga oshirish tartibi:
    </span>
    <div class="indent">
        - shartnoma imzolanganidan soʻng shartnoma umumiy qiymatining oldindan 50 foizi miqdoridagi summani (QQS bilan)
        Buyurtmachi Ijrochining hisob raqamiga shartnoma imzolangan kundan kamda 5 bank kuni ichida oʻtkazib beradi.
        Buyurtmachi shartnomada koʻrsatilgan summaning 100 foizi miqdorini oldindan toʻlab berish huquqiga ega.
    </div>
    <div class="indent">
        - Ijrochi tomonidan xulosa tayyorlangandan soʻng Buyurtmachini axborot kommunikatsiya tizimi orqali
        (hisob-faktura) xabardor qiladi. Buyurtmachi oʻz oʻrnida bajarilgan ishlar summasining qolgan qismini toʻliq
        Ijrochining hisob raqamiga toʻlab beradi va xulosani qabul qilib oladi.
    </div>
    <div class="center bold margin section">Taraflarning javobgarligi</div>
    <span class="subsection">
        Ijrochi Buyurtmachiga mazkur shartnoma boʻyicha ishlar bajarilmagan yoki xizmatlar koʻrsatilmagan hollarda har
        bir kechiktirilgan kun uchun majburiyatlarni bajarmagan qismining 0,4 foiz miqdorida, ammo bajarilmagan ishlar
        yoki koʻrsatilmagan xizmatlar qismi bahosining 50 foizidan ortiq boʻlmagan miqdorda penya toʻlaydi.
    </span>
    <div></div>
    <span class="subsection">
        Buyurtmachi mazkur shartnoma boʻyicha bajarilgan ish uchun toʻlovlarni shartnomaning 4-bobida belgilangan tartib
        va muddatda amalga oshirmagan taqdirda, Ijrochiga qarzdorlik summasini va toʻlov kechiktirilgan har bir kun
        uchun kechiktirilgan toʻlov summasining 0,4 foizi miqdorida, ammo kechiktirilgan toʻlov summasining 50 foizidan
        ortiq boʻlmagan miqdorda penya toʻlaydi.
    </span>
    <div></div>
    <span class="subsection">
        Mazkur shartnomada koʻzda tutilmagan taraflarning javobgarligi masalasi Oʻzbekiston Respublikasi hududida amalda
        boʻlgan qonun hujjatlariga muvofiq hal qilinadi.
    </span>
    <div></div>
    <span class="subsection">
        Jarima toʼlash taraflarning ular oldida turgan majburiyatlarni bajarishdan yoki shartlarning buzilishini
        bartaraf etishdan ozod qilmaydi.
    </span>
    <div></div>
    <span class="subsection">
        Ushbu shartnoma boʻyicha belgilangan xizmatni asossiz ravishda rad etganlik uchun Buyurtmachi Ijrochiga rad
        etilgan xizmat qiymatining 15 foizi miqdorida jarima toʻlaydi.
    </span>
    <div></div>
    <span class="subsection">
        Ijrochi Buyurtmachining hisob raqamidan pul mablagʻini asossiz ravishda akseptsiz hisobdan chiqarganlik uchun
        Buyurtmachiga asossiz ravishda akseptsiz hisobidan chiqarilgan summaning 15 foizi miqdorida jarima toʻlaydi.
    </span>
    <div class="center bold margin section">Fors – Major xolatlari</div>
    <span class="subsection">
        Taraflar oʻzlariga bogʻliq boʻlmagan favqulodda, yengib boʻlmas kuch (fors-major holatlari) taʼsirida
        oʻzlarining shartnomaviy majburiyatlarini bajarmaganliklari yoki yetarli darajada bajarmaganliklari uchun
        javobgarlikdan ozod qilinadilar. Bu holatda majburiyatlarini bajarish muddatlari fors-major holatining davom
        etish muddatiga mutanosib tarzda uzaytiriladi. Fors-major holati 3 oydan ortiq muddat davom etadigan boʻlsa
        shartnoma taraflardan birining tashabbusi bilan bekor qilinishi mumkin.
    </span>
    <div></div>
    <span class="subsection">
        Fors-major holati yuz berganligi vakolatli davlat organlarining maʼlumotnomasi bilan tasdiqlanadi.
    </span>
    <div class="center bold margin section"> Shartnomani oʼzgartirish va bekor qilish haqida</div>
    <div></div>
    <span class="subsection">
        Boshqa barcha holatlarda Taraflarning oʻzaro kelishuvi bilan va bir tarafning tashabbusi bilan sud tartibida
        Oʻzbekiston Respublikasining amaldagi qonun hujjatlariga asosan oʻzgartirilishi va bekor qilinishi mumkin.
    </span>
    <div class="center bold margin section">Nizolarni hal qilish tartibi</div>
    <span class="subsection">
        Mazkur shartnomani bajarishda yuzaga keladigan nizolar va kelishmovchiliklar taraflar oʻrtasida muzokaralar
        yoʻli bilan hal qilinadi.
    </span>
    <div></div>
    <span class="subsection">
        Taraflar nizoni muzokara yoʻli bilan bartaraf qilishmagan taqdirda, Oʻzbekiston Respublikasining amaldagi
        qonunchiligida nazarda tutilgan tartibda {{$lab_claim?->region->name_uz}} tumanlararo iqtisodiy sudi orqali hal etiladi.
    </span>
    <div class="center bold margin section">
        Alohida shartlar, Korrupsion holatlarni bartaraf etish
    </div>
    <span class="subsection">
        Shartnoma yuzasidan ishlarni amalga oshirish jarayonida taraflar tomonidan manfaatlar toʻqnashuvini yuzaga
        keltirishga (taʼmagirlik, asossiz ravishda muddatlarni choʻzib yuborish, normativ xujjatlarga rioya etmaslik,
        sansolarlikka yoʻl qoʻyish) yoʻl qoʻymasliklari shart.
    </span>
    <div></div>
    <span class="subsection">
        Taraflarning biri tomonidan korrupsion harakatlarga daʼvat qilishlik ikkinchi taraf zimmasiga darhol mazkur
        holat yuzasidan tashkilot rahbariga yozma shaklda xabardor qilish majburiyatini yuklaydi. (1-ilova)
    </span>
    <div class="center bold margin section">Konfidensiallik</div>
    <span class="subsection">
        Ushbu shartnomani amal qilish davrida taraflar oʻzlariga maʼlum boʻlgan mahsulot sifati, sinov natijalari va
        texnik tavsiflari haqidagi maʼlumotlarning maxfiyligini saqlash majburiyatini oladilar. Qurilish mahsulotlarini
        sinovdan oʻtkazish boʻyicha ishlarni amalga oshiruvchi shaxslar sinov jarayonida olingan maʼlumotlar, sinov
        natijalari va tomonlarning shartnoma majburiyatlari toʻgʻrisida maxfiylikni saqlash majburiyatini oladi, aholi
        hayoti va sogʻligʻi xavfsizligi bilan bogʻliq hollar bundan mustasno.
    </span>
    <div></div>
    <span class="subsection">
        Shartnoma boʻyicha bir taraf tomonidan boshqa tarafga taqdim etilgan barcha hujjatlar va maʼlumotlar faqat ushbu
        shartnomada nazarda tutilgan maqsadlarda foydalaniladi va har bir taraf olingan maʼlumotlarni konfidensialligini
        saqlaydi. Maʼlumotlar ikkinchi tarafning yozma roziligisiz ushbu shartnomada koʻzda tutilmagan maqsadlarda
        uchinchi shaxslarga oshkor etilmasligi shart. Ushbu shart mazkur Shartnoma bekor qilingandan keyin 3 yil
        davomida amal qiladi. Maʼlumotlarni oshkor qilgan taraf ikkinchi tarafga yetkazilgan zararni toʻliq qoplaydi va
        maʼlumotlarni oshkor boʻlishi natijasida yuzaga kelgan oqibatlar uchun javobgar boʻladi.
    </span>
    <div class="center bold margin section">Yakuniy qoidalar</div>
    <span class="subsection">
        Mazkur shartnoma imzolangan kundan (budjet tashkilotlari uchun belgilangan tartibda gʻaznachilikdan roʻyxatdan
        oʻtgandan soʻng) kuchga kiradi.
    </span>
    <div></div>
    <span class="subsection">
        Mazkur shartnomaga kiritiladigan har qanday oʻzgartirish va qoʻshimchalar, ular faqat yozma shaklda tuzilib,
        taraflarning bunga vakolatli vakillari tomonidan oʻzaro kelishuviga asosan haqiqiy hisoblanadi. Mazkur
        shartnomaga ilovalar uning ajralmas qismi boʻlib hisoblanadi.
    </span>
    <div></div>
    <span class="subsection">
        Ijrochi tomonidan ushbu shartnomani 2.2 bandini ijrosi taʼminlangandan soʻng inspeksiya tomonidan qurilish
        ishlarini boshlash uchun roʻyxatga olinishiga ruxsat beradi.
    </span>
    <div></div>
    <span class="subsection">
        Mazkur shartnoma ikki nusxada tuzildi. Ikkala nusxa aynan bir xil yuridik kuchga ega. Tomonlarning har birida
        mazkur shartnomaning bir nusxasi saqlanadi.
    </span>
    <div></div>
    <span class="subsection">
        Yuridik manzil, bank rekvizitlari oʻzgarganda va boshqa oʻzgarishlar boʻlganda, tomonlar bu haqda bir birlarini
        10 kun ichida darhol yozma ravishda xabardor qilish majburiyatlarini oʻz zimmalariga oladilar.
    </span>
    <div class="page-break"></div>
    <div class="center bold margin section">Tomonlarning yuridik manzillari, bank rekvizitlari va imzolari:</div>
    <div style="padding-top: 20px; margin-bottom: 20px; display: block">
        <div style="width: 48%;  float: left; box-sizing: border-box; padding-right: 8px">
            <table style="width: 100%; border: 1px dotted #000; font-size: 12px; line-height: 15px">
                <caption>"Ijrochi"</caption>
                <tr>
                    <td colspan="2">
                        <div style="font-weight: 700;">
                            Nomi:
                        </div>
                        <span>
                        <span class="bold">{{$inspeksiya->name}}</span>
                    </span>
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Manzil:
                    </td>
                    <td>
                        <span class="bold">{{$inspeksiya->address}}</span>,
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        G'azna H/r:
                    </td>
                    <td>
                        <span class="bold">{{$rekvizit->personal_account}}</span>
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Bank nomi:
                    </td>
                    <td>
                        <span class="bold">Markaziy bank Toshkent
                        shahar hisob-kitob kassa
                        markazi</span>
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
                        <span class="bold">{{$inspeksiya->stir}}</span>
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Tel:
                    </td>
                    <td>
                        <span class="bold">{{$inspeksiya->phone}}</span>
                    </td>
                </tr>
                <tr style="vertical-align: bottom;">
                    <td style="font-weight: 700;">
                        Inspeksiya boshligʻi:
                    </td>
                    <td>
                        <span class="bold">
                            @if($lab_claim->manually_created)
                                {{ $inspection_director->full_name }}
                            @else
                                {{ $inspection_director->surname }} {{ $inspection_director->name }} {{ $inspection_director->middle_name }}
                            @endif
                            </span>
                    </td>
                </tr>
                @if(!$lab_claim->manually_created)
                    <tr>
                        <td colspan="2">
                            <div style="border: 1px solid #8e8686; border-radius: 10px; padding: 4px; margin-top: 6px">
                                <div
                                    style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                                    <img
                                        src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                        width="14" height="14" alt="check"
                                        style="margin-right: 6px"
                                    />
                                    Elekrton tizim orqali tasdiqlandi
                                </div>
                                <div style="color: #918f8f">{{ $contract?->inspeksiya_confirmed_at}}</div>
                            </div>
                        </td>
                    </tr>
                @endif
            </table>
        </div>
        <div style="width: 48%;  float: right; box-sizing: border-box;">
            <table style="width: 100%; border: 1px dotted #000; font-size: 12px; line-height: 15px">
                <caption>“Buyurtmachi”</caption>
                <tr>
                    <td colspan="2">
                        <div style="font-weight: 700;">
                            Nomi:
                        </div>
                        <span style="font-size: 14px;">
                            @if($lab_claim->claim_type === 'Y')
                                {{$lab_claim->labClaimUsers->first()?->user->organization_name}}
                            @else
                                {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'))
                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 1, null, 'UTF-8'))}}
                                {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'))
                                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 1, null, 'UTF-8'))}}
                                {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 0, 1, 'UTF-8'))
                                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}}
                            @endif
                        </span>
                    </td>
                </tr>
                @if($lab_claim->claim_type === 'Y')
                    <tr>
                        <td colspan="2">
                            <div style="font-weight: 700;">
                                Rahbari:
                            </div>
                            <span style="font-size: 14px;">
                                {{!empty($lab_claim->labClaimUsers->first()->user->director_full_name)? $lab_claim->labClaimUsers->first()->user->director_full_name : $contract->director_full_name}}
                        </span>
                        </td>
                    </tr>
                @endif
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        @if($lab_claim->claim_type === 'Y')
                            STIR:
                        @else
                            PINFL:
                        @endif
                    </td>
                    <td>
                        {{$lab_claim->labClaimUsers->first()?->user?->pinfl}}
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Manzil:
                    </td>
                    <td>
                        {{$lab_claim->labClaimUsers->first()?->user?->region?->name_uz}},
                        {{$lab_claim->labClaimUsers->first()?->user?->district?->name_uz}}
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Tel:
                    </td>
                    <td>
                        {{$lab_claim->phone}}
                    </td>
                </tr>
                @if(!$lab_claim->manually_created)
                    <tr>
                        <td colspan="2">
                            <div style="border: 1px solid #8e8686; border-radius: 10px; padding: 4px; margin-top: 6px">
                                <div
                                    style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                                    <img
                                        src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                        width="14" height="14" alt="check"
                                        style="margin-right: 6px"
                                    />
                                    Elekrton tizim orqali tasdiqlandi
                                </div>
                                <div style="color: #918f8f">{{ $contract?->customer_confirmed_at}}</div>
                            </div>
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    <div class="page-break"></div>
    <!-- 1-ilova (jadval) -->
    <table style="width: 100%; padding: 40px 0;">
        <tr class="bold">
            <td></td>
            <td></td>
            <td style="text-align: right;">
                {{$contract->customer_confirmed_at}}
                <br>
                @if($lab_claim->type == 1)
                    {{$contract->contract_number}}
                @else
                    {{ $contract->id }}
                @endif -sonli shartnomaga 1-ilova
            </td>
        </tr>
    </table>
    @if($lab_claim->type === 1)
        <table class="collapsed-table" style="font-size: 8px; line-height: 11px">
            <thead>
            @foreach($details as $key => $detail)
                <tr>
                    <td style="padding: 2px">{{$key+1}}</td>
                    <td style="padding: 2px">{{$detail?->testCategory?->parent?->name_uz}}</td>
                    <td style="padding: 2px">{{$detail?->testCategory?->name_uz}}</td>
                    <td style="padding: 2px">{{$detail->amount}}</td>
                    <td style="padding: 2px">{{$detail->testCategory->unity}}</td>
                    <td style="padding: 2px">{{$detail->price}}</td>
                    <td style="padding: 2px">{{$detail->total_price}}</td>
                    <td style="padding: 2px">{{$detail->nds}}</td>
                    <td style="padding: 2px">{{$detail->total_price_with_nds}}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6" style="font-weight: 700">Jami:</td>
                <td style="padding: 2px">{{$contract->con_total_price}}</td>
                <td style="padding: 2px">{{$contract->con_total_nds}}</td>
                <td>{{$contract->con_total_price_with_nds}}</td>
            </tr>
        </table>
    @else
        @if($details)
            @foreach($contractInfo as $key=>$detail)
                {{--                <div class="section-title">{{ $key }}</div>--}}
                <table class="new-table">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Joylashuv o'rni</th>
                        <th>Konstruksiya elementi</th>
                        <th>Sinov obyekti</th>
                        <th>Sinov usuli</th>
                        <th>Miqdori</th>
                        <th>Narxi (so'm)</th>
                        <th>Umumiy narx (so'm)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($detail as $item)
                        <tr>
                            <td class="text-center">{{$loop->iteration}}</td>
                            <td>{{$item->workType->name}}</td>
                            <td>{{$item->question->name}}</td>
                            <td>{{$item->testCategory->parent->name_uz}}</td>
                            <td class="text-center">{{$item->testCategory->name_uz}}</td>
                            <td class="text-center">{{$item->amount}}</td>
                            <td class="text-right">{{$item->price}}</td>
                            <td class="text-right">{{$item->total_price}}</td>
                        </tr>
                    @endforeach

                    </tbody>
                </table>
                <div class="total-box">
                </div>
            @endforeach
        @endif

        @if($lab_claim->lab_test_category_id !=120)
            <table style="width: 50%; margin-left: auto;" class="new-table">
                <thead>
                <tr>
                    <th>Ko'rsatkich</th>
                    <th>Summa (so'm)</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>QQSsiz jami</td>
                    <td class="text-right font-bold">{{$contract->con_total_price}}</td>
                </tr>
                <tr>
                    <td>QQS (12%)</td>
                    <td class="text-right">{{$contract->con_total_nds}}</td>
                </tr>
                <tr style="background-color: #e6e6e6;">
                    <td class="font-bold">Jami to'lov (QQS bilan)</td>
                    <td class="text-right font-bold">{{$contract->con_total_price_with_nds}}</td>
                </tr>
                </tbody>
            </table>
        @endif
        @if($contract->complication)
            <div class="title">Bino va inshootlarning texnik holatini o'rganish xizmatlari qiymati hisob-kitobi</div>

            <table class="new-table" style="width: 70%; margin: 0 auto 20px auto;">
                <thead>
                <tr>
                    <th>Ko'rsatkich</th>
                    <th>Qiymat</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>Bino hajmi</td>
                    <td class="text-center">{{ $contract?->complication?->building_size }} m³</td>
                </tr>
                <tr>
                    <td>1 m³ bazaviy narx</td>
                    <td class="text-right">{{ $contract?->complication?->price }} so'm</td>
                </tr>
                <tr>
                    <td>Hajmga bog'liqlik koeffitsiyenti (Kv)</td>
                    <td class="text-center">{{ $contract?->complication?->coefficient }}</td>
                </tr>
                <tr style="background-color: #fafafa;">
                    <td>QQSsiz xizmat qiymati</td>
                    <td class="text-right font-bold">{{ $contract?->complication?->total_price }}</td>
                </tr>
                <tr>
                    <td>QQS (12%)</td>
                    <td class="text-right">{{ $contract?->complication?->qqs_price }}</td>
                </tr>
                <tr style="background-color: #e6e6e6;">
                    <td class="font-bold">Jami xizmat qiymati (QQS bilan)</td>
                    <td class="text-right font-bold">{{ $contract?->complication?->total_with_qqs_price }}</td>
                </tr>
                </tbody>
            </table>
        @endif
    @endif
    <div class="total-box">
        Jami xizmat qiymati (QQS bilan): {{ $contract_full_price }}
    </div>
    <div class="page-break"></div>
    <!-- 2-ilova (jadval) -->
    <table style="width: 100%; padding-top: 40px">
        <tr class="bold">
            <td>

            </td>
            <td></td>
            <td style="text-align: right;">
                {{$contract->customer_confirmed_at}} kundagi
                <br>
                @if($lab_claim->type == 1)
                    {{$contract->contract_number}}
                @else
                    {{ $contract->id }}
                @endif -sonli shartnomaga 2-ilova
            </td>
        </tr>
    </table>
    <div class="center bold">
        Korrupsiyaga qarshi kurashishda
        <br/>
        taraflarning shartnomaviy munosabati
    </div>
    <div class="indent">
        Bitim boʻyicha oʻz majburiyatlarini bajarayotganda tomonlar Oʻzbekiston Respublikasining 2017-yil 3-yanvardagi
        OʻRQ-419-son “Korrupsiyaga qarshi kurashish toʻgʻrisida”gi Qonuni hujjati talablariga zid hatti-harakatlarni
        amalga oshirmaydilar, shu jumladan:
    </div>
    <div>- taklif qilmaydilar;</div>
    <div>- vaʼda bermaydilar;</div>
    <div>- tovlamachilik qilmaydilar;</div>
    <div>- toʻlamaydilar;</div>
    <div>- qabul qilishga rozilik berilishi;</div>
    <div>
        - ular bevosita yoki bilvosita, (pora) bergan shaxsning manfaatlari uchun ishlash yoki ishlamaslik uchun
        moddiy boyliklar yoki mulkiy imtiyozlarni pora olmaydilar. Tomonlar bu harakatlarning oldini olish boʻyicha
        chora-tadbirlar qabul qilinishini kafolatlaydi.
    </div>
    <div>
        Agar tashkilot-kontragent
        @if($lab_claim->claim_type === 'Y')
            "{{$lab_claim->labClaimUsers->first()?->user->organization_name}}"
            (keyingi oʻrinlarda
            “Buyurtmachi” deb yuritiladi) nomidan
            rahbari {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'), 'UTF-8') . '.' . mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'), 'UTF-8') . '.'}}
            {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 0, 1, 'UTF-8'))
        . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}}
        @else
            {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'), 'UTF-8') . '.' . mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'), 'UTF-8') . '.'}}
            {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 0, 1, 'UTF-8'))
        . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}} (keyingi
            oʻrinlarda
            “Buyurtmachi” deb yuritiladi)
        @endif
        ushbu shartnoma
        boʻyicha korrupsiya jinoyatini sodir etishga majburlash, shu jumladan,
        hududiy inspeksiya xodimlarining harakatlari Oʻzbekiston Respublikasi qonun hujjatlariga va axloq
        normalariga zid boʻlgan holatlarga duch kelganda, quyidagi aloqa kanallaridan birini xabardor qilishi kerak:
    </div>
    <div>- telefon raqami (komplyans) tel. 95-142-04-17.</div>
    <div>- elektron pochta orqali g.abdullayeva@exat.uz, info@tvdaqn.uz</div>
    <div class="bold" style="padding-top: 40px;">Taraflar:</div>
    <div></div>
    <table style="font-size: 14px; margin-top: 40px; width: 100%">
        <tr style="vertical-align: top;">
            <td style="font-weight: 700;  min-width: 50%;">
                Inspeksiya boshligʻi:
            </td>
            <td style="padding-top: 15px;">
                @if($lab_claim->manually_created)
                    {{ $inspection_director->full_name }}
                @else
                    {{ $inspection_director->surname }} {{ $inspection_director->name }} {{ $inspection_director->middle_name }}
                @endif
                @if(!$lab_claim->manually_created)
                    <div
                        style="border: 1px solid #8e8686; border-radius: 10px; width: 65%; padding: 4px; margin-top: 6px">
                        <div
                            style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                            <img
                                src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                width="14" height="14" alt="check"
                                style="margin-right: 6px"
                            />
                            Elekrton tizim orqali tasdiqlandi
                        </div>
                        <div style="color: #918f8f">{{ $contract?->inspeksiya_confirmed_at}}</div>
                    </div>
                @endif
            </td>
        </tr>
        <tr style="vertical-align: top;">
            <td style="font-weight: 700;  min-width: 50%;">
                Rahbar:
            </td>
            <td style="padding-top: 15px;">
                {{ $lab_claim->labClaimUsers->first()?->user->organization_name }}
                @if(!$lab_claim->manually_created)
                    <div
                        style="border: 1px solid #8e8686; border-radius: 10px; width: 65%; padding: 4px; margin-top: 6px">
                        <div
                            style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                            <img
                                src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                width="14" height="14" alt="check"
                                style="margin-right: 6px"
                            />
                            Elekrton tizim orqali tasdiqlandi
                        </div>
                        <div style="color: #918f8f">{{ $contract?->customer_confirmed_at}}</div>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="page-break"></div>
    <div class="center bold">
        Bajariladigan ishlarning qiymatini kelishish
        <br>
        BAYONNOMASI
    </div>
    <div class="indent">
        <span class="bold">{{$lab_claim->object_name}}</span>
        binosini instrumental texnik tekshiruv holatini aniqlash maqsadida ularning konstruksiyasini texnik koʻrik va
        instrumental tekshiruv oʻtkazish.
    </div>
    <div class="indent">
        Biz, quyida imzochekuvchilar Buyurtmachi, @if($lab_claim->claim_type === 'Y')
            "{{$lab_claim->labClaimUsers->first()?->user->organization_name}}"
            (keyingi oʻrinlarda
            “Buyurtmachi” deb yuritiladi) nomidan <span class="bold">{{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'), 'UTF-8') . '.' . mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'), 'UTF-8') . '.'}}
                {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 0, 1, 'UTF-8'))
            . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}}</span>
        @else
            <span class="bold">{{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'), 'UTF-8') . '.' . mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'), 'UTF-8') . '.'}}
                {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 0, 1, 'UTF-8'))
            . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}}</span>
            (keyingi oʻrinlarda
            “Buyurtmachi” deb yuritiladi)
        @endif va Bajaruvchi
        <span class="bold">{{$inspeksiya->name}}</span> boshligʻi <span class="bold">
            @if($lab_claim->manually_created)
                {{ $inspection_director->full_name }}
            @else
                {{ $inspection_director->surname }} {{ $inspection_director->name }} {{ $inspection_director->middle_name }}
            @endif
            </span>
        tomonlar bajarilgan ishlarning qiymati
        boʻyicha <span class="bold">{{$contract_full_price}}</span> soʻm QQS bilan kelishuvga
        erishilganligini tasdiqlaymiz.
    </div>
    <div class="indent">
        Shartnoma bahosi mehnatga haq toʻlash va bajariladigan ishlar qiymatiga taʼsir qiluvchi boshqa omillarning
        oʻzgarishi hisobiga indeksatsiya qilinishi mumkin.
    </div>
    <div class="indent">
        Mazkur bayonnoma Buyurtmachi va Bajaruvchi oʻrtasida oʻzaro hisob-kitoblarni amalga oshirish uchun asos boʻladi.
    </div>
    <div></div>
    <table style="font-size: 14px; margin-top: 40px; width: 100%">
        <tr style="vertical-align: top;">
            <td style="font-weight: 700;  min-width: 50%;">
                Inspeksiya boshligʻi:
            </td>
            <td style="padding-top: 15px;">
                @if($lab_claim->manually_created)
                    {{ $inspection_director->full_name }}
                @else
                    {{ $inspection_director->surname }} {{ $inspection_director->name }} {{ $inspection_director->middle_name }}
                @endif
                @if(!$lab_claim->manually_created)
                    <div
                        style="border: 1px solid #8e8686; border-radius: 10px; width: 65%; padding: 4px; margin-top: 6px">
                        <div
                            style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                            <img
                                src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                width="14" height="14" alt="check"
                                style="margin-right: 6px"
                            />
                            Elekrton tizim orqali tasdiqlandi
                        </div>
                        <div style="color: #918f8f">{{ $contract?->inspeksiya_confirmed_at}}</div>
                    </div>
                @endif
            </td>
        </tr>
        <tr style="vertical-align: top;">
            <td style="font-weight: 700;  min-width: 50%;">
                Rahbar:
            </td>
            <td style="padding-top: 15px;">
                {{ $lab_claim->labClaimUsers->first()?->user->organization_name }}
                @if(!$lab_claim->manually_created)
                    <div
                        style="border: 1px solid #8e8686; border-radius: 10px; width: 65%; padding: 4px; margin-top: 6px">
                        <div
                            style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                            <img
                                src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                width="14" height="14" alt="check"
                                style="margin-right: 6px"
                            />
                            Elekrton tizim orqali tasdiqlandi
                        </div>
                        <div style="color: #918f8f">{{ $contract?->customer_confirmed_at}}</div>
                    </div>
                @endif
            </td>
        </tr>
    </table>
    </body>
@endif
@if($lab_claim->lab_test_category_id === 1)
    <body>
    <div class="center bold">
        Laboratoriya sinov ishlarini bajarish to‘g‘risida
        <br/>
        SHARTNOMA № @if($lab_claim->type == 1)
            {{$contract->contract_number}}
        @else
            {{ $contract->id }}
        @endif
    </div>
    <table style="width: 100%; padding-top: 40px">
        <tr class="bold">
            <td>
                {{$customer_confirmed_at}}
            </td>
            <td></td>
            <td style="text-align: right;">
                {{$lab_claim?->district?->name_uz}}
            </td>
        </tr>
    </table>
    <div class="indent">
        {{ $region?->name_uz }} hududiy Qurilish va uy-joy kommunal xo’jaligi sohasida
        nazorat qilish inspeksiyasi (keyingi o‘rinlarda “Ijrochi” deb yuritiladi)
        nomidan, nizom asosida faoliyat yurituvchi Inspeksiya boshlig‘i <span
            class="bold">
            @if($lab_claim->manually_created)
                {{ $inspection_director->full_name }}
            @else
                {{ $inspection_director?->surname }} {{ $inspection_director?->name }} {{ $inspection_director?->middle_name }}

            @endif
        </span>
        bir tomondan,
        @if($lab_claim->claim_type === 'Y')
            <span class="bold">{{$lab_claim->labClaimUsers->first()?->user->organization_name}} nomidan
            </span> rahbari
            <span class="bold">
                {{!empty($lab_claim?->labClaimUsers->first()?->user?->director_full_name)? $lab_claim?->labClaimUsers?->first()?->user->director_full_name : $contract?->director_full_name}}
            </span>
            (keyingi
            oʻrinlarda “Buyurtmachi” deb
            yuritiladi)
        @else
            <span class="bold">
                             <span class="bold">{{$lab_claim->labClaimUsers->first()?->user->full_name}}</span>
                    </span>
            (keyingi oʻrinlarda “Buyurtmachi” deb
            yuritiladi)
        @endif ikkinchi tomondan mazkur shartnomani quyidagilar haqida tuzdilar.

    </div>


    <div class="center bold margin section">Shartnoma mazmuni</div>
    <span class="subsection">
    Ijrochi Buyurtmachining topshirig‘iga ko‘ra, mazkur shartnomaning  1.2-bandida ko‘rsatilgan qurilish obyektlarida va
shartnomaning 1-ilovasida ko‘rsatilgan qurilish materiallari, buyumlari va konstruksiyalari bo‘yicha laboratoriya sinovlarini
va tahlil ishlarini amalga oshirish va bu haqda tegishli hujjatlarni rasmiylashtirish majburiyatini, buyurtmachi esa bajarilgan
ishlarni qabul qilib tegishli to‘lovlarni o‘z vaqtida amalga oshirish majburiyatini oladi.
</span>
    <div></div>
    <span class="subsection">
    Qurilish obyektining nomi va manzili:
    <span class="bold">{{ $lab_claim->region->name_uz .', '. $lab_claim->district->name_uz .', '. $lab_claim->address }} hududida {{$lab_claim->object_name}} obyekti </span>.
</span>
    <div></div>
    <span class="subsection">
    Ishlar Buyurtmachi yoki uning vakolatli vakili tomonidan ishlarni qabul qilish-topshirish dalolatnomasi imzolangandan keyin
bajarilgan deb hisoblanadi.
</span>
    <div></div>
    <div class="center bold margin section">Taraflarning huquq va majburiyatlari</div>
    <div class="bold indent">
        Ijrochining huquq va majburiyatlari:
    </div>
    <div></div>
    <span class="subsection">
    Ijrochi shartnomaning 1-ilovasida nazarda tutilgan ishlarni bajarishi uchun Buyurtmachidan zarur hujjatlarni taqdim qilishni
hamda  sinov ishlarini o‘tkazish uchun zarur mahsulotlardan namunalar yetkazib berishni  talab qilish;
</span>
    <div></div>
    <span class="subsection">
    Ijrochi mazkur shartnomada nazarda tutilgan ishlarni bajarishdan oldin Buyurtmachidan oldindan shartnoma summasining kamida ellik foizi
miqdoridagi to‘lovni amalga oshirishni, qolgan mablag‘ni bajarilgan ish Buyurtmachi tomonidan qabul olinishidandan avval to‘lanishini talab qilish;
</span>
    <div></div>
    <span class="subsection">
    Ijrochi Buyurtmachi tomonidan mazkur shartnomaning 3.2. bandida nazarda tutilgan shartlar bajarilmagan taqdirda “Buyurtmachi” ning hisob raqamiga
akseptsiz to‘lov talabnomasi qo‘yish yo‘li bilan pul mablag‘ini yechib olish;
</span>
    <div></div>
    <span class="subsection">
    Shartnomada nazarda tutilgan ishlarni kelishilgan muddatda lozim darajada bajarish
va  Buyurtmachi yoki uning vakiliga belgilangan tartibda rasmiylashtir	ib topshirish.
</span>
    <div></div>
    <span class="subsection">
    O‘tkazilayotgan laboratoriya sinov ishlarini zamonaviy o‘lchov-sinov uskunalaridan foydalangan holda bajarish.
</span>
    <div></div>
    <span class="subsection">
    Shartnomada kelishilgan ishlarni o‘zining kuchlari va/yoki jalb qilingan kuchlar bilan bajarish hamda ishni
Buyurtmachiga mazkur shartnoma shartlariga muvofiq topshirish;
</span>
    <div></div>
    <span class="subsection">
    Agar Ijrochi zimmasidagi ishlarni bajarish jarayonida me’yoriy hujjatlar va boshqa qurilishga oid normativ talablarga
zid bo‘lgan xato va kamchiliklar aniqlangan taqdirda nomuvofiqlik to‘g‘risida xulosa beradi.
</span>
    <div></div>
    <span class="subsection">
    Har bir o‘tkazilgan sinov ishlari yakunlanib, to‘lov summasi to‘liq amalga oshirilgandan so‘ng sinov natijalari bo‘yicha
xulosani besh ish kunidan kechiktirmasdan Buyurtmachiga yoki uning vakiliga taqdim etadi.
</span>
    <div></div>
    <div class="bold indent">
        Buyurtmachining huquq va majburiyatlari:
    </div>
    <span class="subsection">
    Ijrochi tomonidan mazkur shartnomaning 1-ilovasida nazarda tutilgan ishlarni lozim darajada bajarilishi va tegishli tartibda
rasmiylashtirib topshirilishini talab qilish;
</span>
    <div></div>
    <span class="subsection">
   Buyurtmachi sinov natijalaridan norozi bo‘lgan holatlarda, o‘z ixtiyori bilan qonun hujjatlarida belgilangan tartibda
akkreditatsiyadan o‘tgan mustaqil sinov laboratoriyasiga murojaat qilish;
</span>
    <div></div>
    <span class="subsection">
    Zarur hujjatlarni taqdim qilish,  sinov ishlarini o‘tkazish uchun qurilish sinov laboratoriyasiga mahsulotlardan
namunalarni shartnoma imzolangan kundan boshlab 30 kalendar kun mobaynida to‘liq yetkazib berish;
</span>
    <div></div>
    <span class="subsection">
    Sinov ishlarini o‘tkazish uchun ob’ektda yopiq ishlarni (konstruksiyalarni) Ijrochi ko‘rsatgan sath va koordinatlarda
o‘z hisobidan ochib (qazib) berish;
</span>
    <div></div>
    <span class="subsection">
    Ijrochi mazkur shartnomada nazarda tutilgan to‘lovni o‘z vaqtida amalga oshirish;
</span>
    <div></div>
    <span class="subsection">
    Buyurtmachi Ijrochiga sinov ishlarini amalga oshirish uchun yetarli shart-sharoitlarni yaratib berish;
</span>
    <div></div>
    <span class="subsection">
    Buyurtmachi tomonidan yopiq ishlarni (konstruksiyalarni) sath va koordinatlarda ochib (qazib) berilgan joylar o‘zi tomonidan qayta tiklash;
</span>
    <div></div>
    <div class="center bold margin section">Shartnomaning bahosi va hisob-kitob tartibi</div>
    <span class="subsection">
    Mazkur shartnomaning bahosi (QQSni hisobga olgan holda) <span
            class="bold">{{ number_format($contract->con_total_price_with_nds, 2, '.', ' ') }}</span>

       ({{ numberToUzbekWords($contract->con_total_price_with_nds)  }})
so‘mni tashkil qiladi. Ammo to‘lov haqiqatda bajarilgan ishlar uchun amalga oshiriladi.
</span>
    <div></div>
    <span class="subsection">
    Buyurtmachi tomonidan Ijrochiga oldindan shartnoma bahosining kamida ellik foizi miqdorida to‘lov amalga oshiriladi, qolgan to‘lovlar bajarilgan ishlar qabul qilib olishidan
avval amalga oshiriladi.  O‘zbekiston Respublikasi Vazirlar Mahkamasi tomonidan belgilangan hollar bundan mustasno.
</span>
    <div></div>
    <span class="subsection">
Bajarilgan ish uchun to‘lovlar Ijrochining hisob raqamiga pul ko‘chirish yo‘li bilan amalga oshiriladi.
</span>
    <div></div>
    <span class="subsection">
Mazkur shartnomaning bahosi obyektdagi ishlar hajmi va ish holatlaridan kelib chiqqan holda taraflarning o‘zaro kelishuvi bilan o‘zgartirilishi mumkin.
</span>
    <div></div>
    <div class="center bold margin section">Taraflarning javobgarligi</div>
    <span class="subsection">
    Ijrochi Buyurtmachiga mazkur shartnoma bo‘yicha ishlar bajarilmagan yoki xizmatlar ko‘rsatilmagan hollarda har bir kechiktirilgan kun uchun majburiyatlarni bajarmagan qismining 0,4 foizi miqdorida,
ammo bajarilmagan ishlar yoki ko‘rsatilmagan xizmatlar qismi bahosining 50 foizidan ortiq bo‘lmagan miqdorda penya to‘laydi.
</span>
    <div></div>
    <span class="subsection">
    Buyurtmachi  mazkur shartnoma bo‘yicha bajarilgan ish uchun to‘lovlarni shartnomaning 3-bobida belgilangan tartib va muddatda amalga oshirmagan taqdirda,
Ijrochiga qarzdorlik summasini va to‘lov kechiktirilgan har bir kun uchun kechiktirilgan to‘lov summasining 0,4 foizi miqdorida, ammo kechiktirilgan to‘lov
summasining 50 foizidan ortiq bo‘lmagan miqdorda penya to‘laydi.
</span>
    <div></div>
    <span class="subsection">
    Mazkur shartnomada ko‘zda tutilmagan taraflarning javobgarligi masalasi O‘zbekiston Respublikasi hududida amalda bo‘lgan qonun hujjatlariga muvofiq hal qilinadi.
</span>
    <div></div>
    <span class="subsection">
    Neustoyka to‘lash taraflarning ular oldida turgan majburiyatlarni bajarishdan yoki shartlarning buzilishini bartaraf etishdan ozod qilmaydi.
</span>
    <div></div>
    <span class="subsection">
    Ushbu shartnoma bo‘yicha belgilangan xizmatni asossiz ravishda rad etganlik uchun Buyurtmachi Ijrochiga rad etilgan xizmat qiymatining
15 foizi miqdorida jarima to‘laydi.
</span>
    <div></div>
    <span class="subsection">
    Ijrochi Buyurtmachining hisob raqamidan pul mablag‘ini asossiz ravishda akseptsiz hisobdan chiqarganlik uchun Buyurtmachiga asossiz
ravishda akseptsiz hisobidan chiqarilgan summaning 10 foizi miqdorida jarima to‘laydi.
</span>
    <div></div>
    <div class="center bold margin section">Fors – Major xolatlari</div>
    <span class="subsection">
    Taraflar o‘zlariga bog‘liq bo‘lmagan favqulodda, yengib bo‘lmas kuch (fors-major holatlari) ta’sirida o‘zlarining shartnomaviy
majburiyatlarini bajarmaganliklari yoki yetarli darajada bajarmaganliklari uchun javobgarlikdan ozod qilinadilar.
</span>
    <div></div>
    <div class="indent">
        Buyurtmachi mazkur shartnoma boʻyicha bajarilgan ish uchun toʻlovlarni shartnomaning 4-bobida belgilangan tartib
        va muddatda amalga oshirmagan taqdirda, Ijrochiga qarzdorlik summasini va toʻlov kechiktirilgan har bir kun
        uchun kechiktirilgan toʻlov summasining 0,4 foizi miqdorida, ammo kechiktirilgan toʻlov summasining 50 foizidan
        ortiq boʻlmagan miqdorda penya toʻlaydi.
    </div>
    <div></div>
    <div class="indent">
        Bu holatda majburiyatlarini bajarish muddatlari fors-major holatining davom etish muddatiga mutanosib tarzda
        uzaytiriladi.
        Fors-major holati 3 oydan ortiq muddat davom etadigan bo‘lsa shartnoma taraflardan birining tashabbusi bilan
        bekor qilinishi mumkin.
    </div>
    <div></div>
    <div class="center bold margin section">Shartnomani o‘zgartirish va bekor qilish haqida</div>
    <span class="subsection">
    Ushbu shartnoma taraflarning o‘zaro kelishuvi bilan asoslangan xolda va bir tarafning tashabbusi bilan sud tartibida
O‘zbekiston Respublikasining amaldagi qonun hujjatlariga asosan o‘zgartirilishi va bekor qilinishi mumkin.
</span>
    <div></div>
    <div class="center bold margin section">Nizolarni hal qilish tartibi</div>
    <span class="subsection">
    Mazkur shartnomani bajarishda yuzaga keladigan nizolar va kelishmovchiliklar taraflar oʻrtasida muzokaralar
    yoʻli bilan hal qilinadi.
</span>
    <div></div>
    <span class="subsection">
    Taraflar nizoni muzokara yoʻli bilan bartaraf qilishmagan taqdirda, Oʻzbekiston Respublikasining amaldagi
    qonunchiligida nazarda tutilgan tartibda {{$lab_claim?->region->name_uz}} tumanlararo iqtisodiy sudi orqali hal etiladi.
</span>
    <div class="center bold margin section">
        Alohida shartlar, Korrupsion holatlarni bartaraf etish
    </div>
    <span class="subsection">
    Shartnoma yuzasidan ishlarni amalga oshirish jarayonida taraflar tomonidan manfaatlar toʻqnashuvini yuzaga
    keltirishga (taʼmagirlik, asossiz ravishda muddatlarni choʻzib yuborish, normativ xujjatlarga rioya etmaslik,
    sansolarlikka yoʻl qoʻyish) yoʻl qoʻymasliklari shart.
</span>
    <div></div>
    <span class="subsection">
    Taraflarning biri tomonidan korrupsion harakatlarga daʼvat qilishlik ikkinchi taraf zimmasiga darhol mazkur
    holat yuzasidan tashkilot rahbariga yozma shaklda xabardor qilish majburiyatini yuklaydi. (1-ilova)
</span>
    <div class="center bold margin section">Konfidensiallik</div>
    <span class="subsection">
    Ushbu shartnomani amal qilish davrida taraflar oʻzlariga maʼlum boʻlgan mahsulot sifati, sinov natijalari va
    texnik tavsiflari haqidagi maʼlumotlarning maxfiyligini saqlash majburiyatini oladilar. Qurilish mahsulotlarini
    sinovdan oʻtkazish boʻyicha ishlarni amalga oshiruvchi shaxslar sinov jarayonida olingan maʼlumotlar, sinov
    natijalari va tomonlarning shartnoma majburiyatlari toʻgʻrisida maxfiylikni saqlash majburiyatini oladi, aholi
    hayoti va sogʻligʻi xavfsizligi bilan bogʻliq hollar bundan mustasno.
</span>
    <div></div>
    <span class="subsection">
    Shartnoma boʻyicha bir taraf tomonidan boshqa tarafga taqdim etilgan barcha hujjatlar va maʼlumotlar faqat ushbu
    shartnomada nazarda tutilgan maqsadlarda foydalaniladi va har bir taraf olingan maʼlumotlarni konfidensialligini
    saqlaydi. Maʼlumotlar ikkinchi tarafning yozma roziligisiz ushbu shartnomada koʻzda tutilmagan maqsadlarda
    uchinchi shaxslarga oshkor etilmasligi shart. Ushbu shart mazkur Shartnoma bekor qilingandan keyin 3 yil
    davomida amal qiladi. Maʼlumotlarni oshkor qilgan taraf ikkinchi tarafga yetkazilgan zararni toʻliq qoplaydi va
    maʼlumotlarni oshkor boʻlishi natijasida yuzaga kelgan oqibatlar uchun javobgar boʻladi.
</span>
    <div class="center bold margin section">Yakuniy qoidalar</div>
    <span class="subsection">
    Mazkur shartnoma imzolangan kundan (budjet tashkilotlari uchun belgilangan tartibda gʻaznachilikdan roʻyxatdan oʻtgandan soʻng kuchga kiradi.
</span>
    <div></div>
    <span class="subsection">
    Mazkur shartnomaga kiritiladigan har qanday oʻzgartirish va qoʻshimchalar, ular faqat yozma shaklda tuzilib,
    taraflarning bunga vakolatli vakillari tomonidan oʻzaro kelishuviga asosan haqiqiy hisoblanadi. Mazkur
    shartnomaga ilovalar uning ajralmas qismi boʻlib hisoblanadi.
</span>
    <div></div>
    <span class="subsection">
    Ijrochi tomonidan ushbu shartnomani 2.2 bandini ijrosi taʼminlangandan soʻng inspeksiya tomonidan qurilish
    ishlarini boshlash uchun roʻyxatga olinishiga ruxsat beradi.
</span>
    <div></div>
    <span class="subsection">
    Mazkur shartnoma ikki nusxada tuzildi. Ikkala nusxa aynan bir xil yuridik kuchga ega. Tomonlarning har birida
    mazkur shartnomaning bir nusxasi saqlanadi.
</span>
    <div></div>
    <span class="subsection">
    Yuridik manzil, bank rekvizitlari oʻzgarganda va boshqa oʻzgarishlar boʻlganda, tomonlar bu haqda bir birlarini
    10 kun ichida darhol yozma ravishda xabardor qilish majburiyatlarini oʻz zimmalariga oladilar.
</span>
    <div class="page-break"></div>
    <div class="center bold margin section">Tomonlarning yuridik manzillari, bank rekvizitlari va imzolari:</div>
    <div style="padding-top: 20px; margin-bottom: 20px; display: block">
        <div style="width: 48%;  float: left; box-sizing: border-box; padding-right: 8px">
            <table style="width: 100%; border: 1px dotted #000; font-size: 12px; line-height: 15px">
                <caption>"Ijrochi"</caption>
                <tr>
                    <td colspan="2">
                        <div style="font-weight: 700;">
                            Nomi:
                        </div>
                        <span>
                    <span>{{$inspeksiya->name}}</span>
                </span>
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Manzil:
                    </td>
                    <td>
                        <span>{{$inspeksiya->address}}</span>,
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        G'azna H/r:
                    </td>
                    <td>
                        <span>{{$rekvizit->personal_account}}</span>
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Bank nomi:
                    </td>
                    <td>
                        <span>
                            Markaziy bank Toshkent
                        shahar hisob-kitob kassa
                        markazi
                        </span>
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
                        <span>{{$inspeksiya->stir}}</span>
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Tel:
                    </td>
                    <td>
                        <span>{{$inspeksiya->phone}}</span>
                    </td>
                </tr>
                <tr style="vertical-align: bottom;">
                    <td style="font-weight: 700;">
                        Inspeksiya boshligʻi:
                    </td>
                    <td>
                        <span>
                            @if($lab_claim->manually_created)
                                {{ $inspection_director->full_name }}
                            @else
                                {{ $inspection_director?->surname }} {{ $inspection_director?->name }} {{ $inspection_director?->middle_name }}
                            @endif
                            </span>
                    </td>
                </tr>
                @if(!$lab_claim->manually_created)
                    <tr>
                        <td colspan="2">
                            <div style="border: 1px solid #8e8686; border-radius: 10px; padding: 4px; margin-top: 6px">
                                <div
                                    style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                                    <img
                                        src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                        width="14" height="14" alt="check"
                                        style="margin-right: 6px"
                                    />
                                    Elekrton tizim orqali tasdiqlandi
                                </div>
                                <div style="color: #918f8f">{{ $contract?->inspeksiya_confirmed_at}}</div>
                            </div>
                        </td>
                    </tr>
                @endif
            </table>
        </div>
        <div style="width: 48%;  float: right; box-sizing: border-box;">
            <table style="width: 100%; border: 1px dotted #000; font-size: 12px; line-height: 15px">
                <caption>“Buyurtmachi”</caption>
                <tr>
                    <td colspan="2">
                        <div style="font-weight: 700;">
                            Nomi:
                        </div>
                        <span style="font-size: 14px;">
                            @if($lab_claim->claim_type === 'Y')
                                {{$lab_claim->labClaimUsers->first()?->user->organization_name}}
                            @else
                                {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'))
                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 1, null, 'UTF-8'))}}
                                {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'))
                                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 1, null, 'UTF-8'))}}
                                {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user?->surname, 0, 1, 'UTF-8'))
                                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}}
                            @endif
                        </span>
                    </td>
                </tr>
                @if($lab_claim->claim_type === 'Y')
                    <tr>
                        <td colspan="2">
                            <div style="font-weight: 700;">
                                Rahbari:
                            </div>
                            <span style="font-size: 14px;">
                                {{!empty($lab_claim->labClaimUsers->first()->user->director_full_name)? $lab_claim->labClaimUsers->first()->user->director_full_name : $contract->director_full_name}}
                            </span>
                        </td>
                    </tr>
                @endif
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Manzil:
                    </td>
                    <td>
                        {{$lab_claim->labClaimUsers->first()?->user?->region?->name_uz}},
                        {{$lab_claim->labClaimUsers->first()?->user?->district?->name_uz}}
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        @if($lab_claim->claim_type === 'Y')
                            STIR:
                        @else
                            PINFL:
                        @endif
                    </td>
                    <td>
                        {{$lab_claim->labClaimUsers->first()?->user->pinfl}}
                    </td>
                </tr>
                <tr style="vertical-align: top;">
                    <td style="font-weight: 700;">
                        Tel:
                    </td>
                    <td>
                        {{$lab_claim->phone}}
                    </td>
                </tr>
                @if(!$lab_claim->manually_created)
                    <tr>
                        <td colspan="2">
                            <div style="border: 1px solid #8e8686; border-radius: 10px; padding: 4px; margin-top: 6px">
                                <div
                                    style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                                    <img
                                        src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                        width="14" height="14" alt="check"
                                        style="margin-right: 6px"
                                    />
                                    Elekrton tizim orqali tasdiqlandi
                                </div>
                                <div style="color: #918f8f">{{ $contract?->customer_confirmed_at}}</div>
                            </div>
                            {{--                        <div class="seal center confirm-seal">--}}

                            {{--                            <div style="text-align: center; font-size: 12px">Elektron tizim orqali</div>--}}
                            {{--                            <div--}}
                            {{--                                style="--}}
                            {{--                                 text-align: center;--}}
                            {{--                                 margin-top: 5px;--}}
                            {{--                                 margin-bottom: 5px;--}}
                            {{--                                 font-size: 17px;--}}
                            {{--                                "--}}
                            {{--                            >--}}
                            {{--                                TASDIQLANDI--}}
                            {{--                            </div>--}}
                            {{--                            <div style="font-size: 13px" class="center">--}}
                            {{--                                {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'), 'UTF-8') . '.' . mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'), 'UTF-8') . '.'}}--}}
                            {{--                                {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 0, 1, 'UTF-8'))--}}
                            {{--                         . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}}--}}
                            {{--                            </div>--}}
                            {{--                            <div--}}
                            {{--                                style="font-size: 12px; "--}}
                            {{--                            >--}}
                            {{--                                <span> {{$contract->customer_confirmed_at}} </span>--}}
                            {{--                            </div>--}}
                            {{--                        </div>--}}
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    <div class="page-break"></div>
    <!-- 1-ilova (jadval) -->
    <table style="width: 100%; padding: 40px 0;">
        <tr class="bold">
            <td></td>
            <td></td>
            <td style="text-align: right;">
                {{ $customer_confirmed_at }} kundagi
                <br>
                @if($lab_claim->type == 1)
                    {{$contract->contract_number}}
                @else
                    {{ $contract->id }}
                @endif -sonli shartnomaga 1-ilova
            </td>
        </tr>
    </table>
    @if($lab_claim->type === 1)
        <table class="collapsed-table" style="font-size: 8px; line-height: 11px">
            <thead>
            @foreach($details as $key => $detail)
                <tr>
                    <td style="padding: 2px">{{$key+1}}</td>
                    <td style="padding: 2px">{{$detail?->testCategory?->parent?->name_uz}}</td>
                    <td style="padding: 2px">{{$detail?->testCategory?->name_uz}}</td>
                    <td style="padding: 2px">{{$detail->amount}}</td>
                    <td style="padding: 2px">{{$detail->testCategory->unity}}</td>
                    <td style="padding: 2px">{{$detail->price}}</td>
                    <td style="padding: 2px">{{$detail->total_price}}</td>
                    <td style="padding: 2px">{{$detail->nds}}</td>
                    <td style="padding: 2px">{{$detail->total_price_with_nds}}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6" style="font-weight: 700">Jami:</td>
                <td style="padding: 2px">{{$contract->con_total_price}}</td>
                <td style="padding: 2px">{{$contract->con_total_nds}}</td>
                <td>{{$contract->con_total_price_with_nds}}</td>
            </tr>
        </table>
    @else
        @if($details)
            @foreach($contractInfo as $key=>$detail)
                {{--                <div class="section-title">{{ $key }}</div>--}}
                <table class="new-table">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Joylashuv o'rni</th>
                        <th>Konstruksiya elementi</th>
                        <th>Sinov obyekti</th>
                        <th>Sinov usuli</th>
                        <th>Miqdori</th>
                        <th>Narxi (so'm)</th>
                        <th>Umumiy narx (so'm)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($detail as $item)
                        <tr>
                            <td class="text-center">{{$loop->iteration}}</td>
                            <td>{{$item->workType->name}}</td>
                            <td>{{$item->question->name}}</td>
                            <td>{{$item->testCategory->parent->name_uz}}</td>
                            <td class="text-center">{{$item->testCategory->name_uz}}</td>
                            <td class="text-center">{{$item->amount}}</td>
                            <td class="text-right">{{$item->price}}</td>
                            <td class="text-right">{{$item->total_price}}</td>
                        </tr>
                    @endforeach

                    </tbody>
                </table>
                <div class="total-box">
                    jami: {{ number_format($detail->sum('total_price'), 2, '.', ' ') }} so'm
                </div>
            @endforeach
        @endif

        <table style="width: 50%; margin-left: auto;" class="new-table">
            <thead>
            <tr>
                <th>Ko'rsatkich</th>
                <th>Summa (so'm)</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>QQSsiz jami</td>
                <td class="text-right font-bold">{{$contract->con_total_price}}</td>
            </tr>
            <tr>
                <td>QQS (12%)</td>
                <td class="text-right">{{$contract->con_total_nds}}</td>
            </tr>
            <tr style="background-color: #e6e6e6;">
                <td class="font-bold">Jami to'lov (QQS bilan)</td>
                <td class="text-right font-bold">{{$contract->con_total_price_with_nds}}</td>
            </tr>
            </tbody>
        </table>

        @if($contract->complication)
            <div class="title">Bino va inshootlarning texnik holatini o'rganish xizmatlari qiymati hisob-kitobi</div>

            <table class="new-table" style="width: 70%; margin: 0 auto 20px auto;">
                <thead>
                <tr>
                    <th>Ko'rsatkich</th>
                    <th>Qiymat</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>Bino hajmi</td>
                    <td class="text-center">{{ $contract?->complication?->building_size }} m³</td>
                </tr>
                <tr>
                    <td>1 m³ bazaviy narx</td>
                    <td class="text-right">{{ $contract?->complication?->price }} so'm</td>
                </tr>
                <tr>
                    <td>Hajmga bog'liqlik koeffitsiyenti (Kv)</td>
                    <td class="text-center">{{ $contract?->complication?->coefficient }}</td>
                </tr>
                <tr style="background-color: #fafafa;">
                    <td>QQSsiz xizmat qiymati</td>
                    <td class="text-right font-bold">{{ $contract?->complication?->total_price }}</td>
                </tr>
                <tr>
                    <td>QQS (12%)</td>
                    <td class="text-right">{{ $contract?->complication?->qqs_price }}</td>
                </tr>
                <tr style="background-color: #e6e6e6;">
                    <td class="font-bold">Jami xizmat qiymati (QQS bilan)</td>
                    <td class="text-right font-bold">{{ $contract?->complication?->total_with_qqs_price }}</td>
                </tr>
                </tbody>
            </table>

            <div class="formula-box">
                <strong>Hisoblash formulasi:</strong><br> [cite: 18]
                Xizmat qiymati = V × B × Kv × Ks × Km [cite: 19]<br>
                1001 × 300 × 1,0 × 0,8 × 0,8 = 192 192 so'm [cite: 20]<br>
                QQS (12%) = 23 063 so'm [cite: 21]<br>
                <strong>Jami: 215 255 so'm</strong> [cite: 22]
            </div>
        @endif

    @endif
    <div class="page-break"></div>
    <!-- 2-ilova (jadval) -->
    <table style="width: 100%; padding-top: 40px">
        <tr class="bold">
            <td>

            </td>
            <td></td>
            <td style="text-align: right;">
                {{$customer_confirmed_at}} kundagi
                <br>
                @if($lab_claim->type == 1)
                    {{$contract->contract_number}}
                @else
                    {{ $contract->id }}
                @endif -sonli shartnomaga 2-ilova
            </td>
        </tr>
    </table>
    <div class="center bold">
        Korrupsiyaga qarshi kurashishda
        <br/>
        taraflarning shartnomaviy munosabati
    </div>
    <div class="indent">
        Bitim boʻyicha oʻz majburiyatlarini bajarayotganda tomonlar Oʻzbekiston Respublikasining 2017-yil 3-yanvardagi
        OʻRQ-419-son “Korrupsiyaga qarshi kurashish toʻgʻrisida”gi Qonuni hujjati talablariga zid hatti-harakatlarni
        amalga oshirmaydilar, shu jumladan:
    </div>
    <div>- taklif qilmaydilar;</div>
    <div>- vaʼda bermaydilar;</div>
    <div>- tovlamachilik qilmaydilar;</div>
    <div>- toʻlamaydilar;</div>
    <div>- qabul qilishga rozilik berilishi;</div>
    <div>
        - ular bevosita yoki bilvosita, (pora) bergan shaxsning manfaatlari uchun ishlash yoki ishlamaslik uchun
        moddiy boyliklar yoki mulkiy imtiyozlarni pora olmaydilar. Tomonlar bu harakatlarning oldini olish boʻyicha
        chora-tadbirlar qabul qilinishini kafolatlaydi.
    </div>
    <div>
        Agar tashkilot-kontragent @if($lab_claim->claim_type === 'Y')
            <span class="bold">
            "{{$lab_claim->labClaimUsers->first()?->user->organization_name}}"
            </span>
            (keyingi oʻrinlarda
            “Buyurtmachi” deb yuritiladi)
            nomidan {{mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->name, 0, 1, 'UTF-8'), 'UTF-8') . '.' . mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->middle_name, 0, 1, 'UTF-8'), 'UTF-8') . '.'}}
            {{ mb_strtoupper(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 0, 1, 'UTF-8'))
        . mb_strtolower(mb_substr($lab_claim->labClaimUsers->first()?->user->surname, 1, null, 'UTF-8'))}}
        @else
            <span class="bold">{{ $lab_claim->labClaimUsers->first()?->user?->full_name }}</span> (keyingi
            oʻrinlarda
            “Buyurtmachi” deb yuritiladi)
        @endif ushbu shartnoma
        boʻyicha korrupsiya jinoyatini sodir etishga majburlash, shu jumladan,
        hududiy inspeksiya xodimlarining harakatlari Oʻzbekiston Respublikasi qonun hujjatlariga va axloq
        normalariga zid boʻlgan holatlarga duch kelganda, quyidagi aloqa kanallaridan birini xabardor qilishi kerak:
    </div>
    <div>- telefon raqami (komplayens) tel. 95-142-04-17.</div>
    <div>- elektron pochta orqali g.abdullayeva@exat.uz, info@tvdaqn.uz</div>
    <div class="bold" style="padding-top: 40px;">Taraflar:</div>
    <div></div>
    <table style="font-size: 14px; margin-top: 40px; width: 100%">
        <tr style="vertical-align: top;">
            <td style="font-weight: 700;  min-width: 50%;">
                Inspeksiya boshligʻi:
            </td>
            <td style="padding-top: 15px;">
                @if($lab_claim->manually_created)
                    {{ $inspection_director->full_name }}
                @else
                    {{ $inspection_director?->surname }} {{ $inspection_director?->name }} {{ $inspection_director?->middle_name }}
                @endif
                @if(!$lab_claim->manually_created)
                    <div
                        style="border: 1px solid #8e8686; border-radius: 10px; width: 65%; padding: 4px; margin-top: 6px">
                        <div
                            style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                            <img
                                src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                width="14" height="14" alt="check"
                                style="margin-right: 6px"
                            />
                            Elekrton tizim orqali tasdiqlandi
                        </div>
                        <div style="color: #918f8f">{{ $contract?->inspeksiya_confirmed_at}}</div>
                    </div>
                @endif
            </td>
        </tr>
        <tr style="vertical-align: top;">
            <td style="font-weight: 700;  min-width: 50%;">
                Rahbar:
            </td>
            <td style="padding-top: 15px;">
                @if($lab_claim->claim_type === 'Y')
                    {{!empty($lab_claim->director_full_name)? $lab_claim->director_full_name : $lab_claim->labClaimUsers->first()?->user->director_full_name}}
                @else
                    {{$lab_claim->labClaimUsers->first()?->user->full_name}}
                @endif
                @if(!$lab_claim->manually_created)
                    <div
                        style="border: 1px solid #8e8686; border-radius: 10px; width: 65%; padding: 4px; margin-top: 6px">
                        <div
                            style="background-color: #c3f1bf; padding: 3px; border-radius: 5px; color: #11852b; white-space: nowrap;">
                            <img
                                src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBzdHJva2U9IiMxMTg1MmIiIHN0cm9rZS13aWR0aD0iMiIgdmlld0JveD0iMCAwIDI0IDI0IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIj4KICA8cGF0aCBkPSJNIDIyIDExLjA4IFYgMTJhIDEwIDEwIDAgMSAxIC01LjkzIC05LjE0Ij48L3BhdGg+CiAgPHBvbHlsaW5lIHBvaW50cz0iMjIgNCAxMiAxNC4wMSA5IDExLjAxIj48L3BvbHlsaW5lPgo8L3N2Zz4="
                                width="14" height="14" alt="check"
                                style="margin-right: 6px"
                            />
                            Elekrton tizim orqali tasdiqlandi
                        </div>
                        <div style="color: #918f8f">{{ $contract?->customer_confirmed_at}}</div>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <!--<div class="page-break"></div>-->
    <!--<div class="center bold">-->
    <!--    Bajariladigan ishlarning qiymatini kelishish-->
    <!--    <br>-->
    <!--    BAYONNOMASI-->
    <!--</div>-->
    <!--<div class="indent">-->
    <!--    Toshkent viloyati, Zangiota tumani, “Oʻrta Ovul” MFY, Furqat koʻchasi hududida joylashgan Omborxona va ofis binosini-->
    <!--    binosini instrumental texnik tekshiruv holatini aniqlash maqsadida ularning konstruksiyasini texnik koʻrik va-->
    <!--    instrumental tekshiruv oʻtkazish.-->
    <!--</div>-->
    <!--<div class="indent">-->
    <!--    Biz, quyida imzochekuvchilar Buyurtmachi, “BIRYUZA GROUP” MCHJ XK (keyingi oʻrinlarda “Buyurtmachi” deb yuritiladi)-->
    <!--    nomidan rahbari I.V.Eydin va Bajaruvchi Toshkent viloyati hududiy Qurilish va uy-joy kommunal xoʻjaligi sohasida-->
    <!--    hududiy nazorat qilish inspeksiyasi boshligʻi Y.Ikramov tomonlar bajarilgan ishlarning qiymati boʻyicha 40 598-->
    <!--    750,96 (qirq million besh yuz toʻqson sakkiz ming yetti yuz ellik soʻm 96 tiyin) soʻm QQS bilan kelishuvga-->
    <!--    erishilganligini tasdiqlaymiz.-->
    <!--</div>-->
    <!--<div class="indent">-->
    <!--    Shartnoma bahosi mehnatga haq toʻlash va bajariladigan ishlar qiymatiga taʼsir qiluvchi boshqa omillarning-->
    <!--    oʻzgarishi hisobiga indeksatsiya qilinishi mumkin.-->
    <!--</div>-->
    <!--<div class="indent">-->
    <!--    Mazkur bayonnoma Buyurtmachi va Bajaruvchi oʻrtasida oʻzaro hisob-kitoblarni amalga oshirish uchun asos boʻladi.-->
    <!--</div>-->

    <!--<table style="font-size: 12px; margin-top: 40px">-->
    <!--    <tr style="vertical-align: bottom;">-->
    <!--        <td style="font-weight: 700; padding-bottom: 30px">-->
    <!--            Inspeksiya boshligʻi v.v.b.-->
    <!--        </td>-->
    <!--        <td style="padding-top: 15px;">-->
    <!--            <div style="width: 120px; display: inline-block;">-->
    <!--                <div style="border-bottom: 1px solid #000"></div>-->
    <!--                <div class="subtext center">-->
    <!--                    (imzo)-->
    <!--                </div>-->
    <!--            </div>-->
    <!--        </td>-->
    <!--        <td style="padding-top: 15px;">-->
    <!--            <div style="width: 320px; display: inline-block;">-->
    <!--                <div style="border-bottom: 1px solid #000"></div>-->
    <!--                <div class="subtext center">-->
    <!--                    (F.I.O.)-->
    <!--                </div>-->
    <!--            </div>-->
    <!--        </td>-->
    <!--    </tr>-->
    <!--</table>-->
    <!--<table style="font-size: 12px; margin-top: 40px">-->
    <!--    <tr style="vertical-align: bottom;">-->
    <!--        <td style="font-weight: 700; padding-bottom: 25px; width: 180px">-->
    <!--            Rahbar-->
    <!--        </td>-->
    <!--        <td style="padding-top: 15px;">-->
    <!--            <div style="width: 120px; display: inline-block;">-->
    <!--                <div style="border-bottom: 1px solid #000"></div>-->
    <!--                <div class="subtext center">-->
    <!--                    (imzo)-->
    <!--                </div>-->
    <!--            </div>-->
    <!--        </td>-->
    <!--        <td style="padding-top: 15px;">-->
    <!--            <div style="width: 320px; display: inline-block;">-->
    <!--                <div style="border-bottom: 1px solid #000"></div>-->
    <!--                <div class="subtext center">-->
    <!--                    (F.I.O.)-->
    <!--                </div>-->
    <!--            </div>-->
    <!--        </td>-->
    <!--    </tr>-->
    <!--</table>-->
    </body>
@endif

</html>
