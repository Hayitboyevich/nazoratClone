@php
    /** @var \App\Models\LabTestContract $contract */
    $money = fn($value, $decimals = 2) => number_format((float)$value, $decimals, '.', ' ');
    $blank = '___ ____________ 20__ yil';
    $no = $contract->contract_number;
    $inspectionName = $inspection?->name ?: (($regionName ?? '') . ' hududiy Qurilish va uy-joy kommunal xo‘jaligi sohasida nazorat qilish inspeksiyasi');
    $customerSigner = $isLegal ? 'Rahbar' : 'Buyurtmachi';
@endphp
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Shartnoma № {{ $no }}</title>
    <style>
        @page { size: A4; margin: 18mm 15mm 18mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Serif", serif; font-size: 10.5px; line-height: 1.35; color: #000; text-align: justify; }
        p { margin: 0 0 2px; }
        h1 { font-size: 12.5px; text-align: center; margin: 0 0 8px; font-weight: bold; }
        h2 { font-size: 11px; text-align: center; margin: 10px 0 5px; font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .page-break { page-break-after: always; }
        .head { width: 100%; margin-bottom: 6px; }
        .head td { padding: 0; vertical-align: top; }
        table.parties { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.parties td { width: 50%; vertical-align: top; padding: 0 8px 0 0; text-align: left; }
        table.tb { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9px; text-align: left; }
        table.tb th, table.tb td { border: 0.6px solid #000; padding: 3px 4px; vertical-align: top; }
        table.tb th { background: #e6e6e6; text-align: center; }
        table.tb td.n { text-align: right; white-space: nowrap; }
        table.tb td.c { text-align: center; }
        tr.bh td { background: #cfd8ea; font-weight: bold; }
        tr.st td { background: #eeeeee; font-weight: bold; }
        tr.bt td { font-weight: bold; }
        table.tot { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9.5px; }
        table.tot th, table.tot td { border: 0.6px solid #000; padding: 4px; text-align: center; }
        table.tot th { background: #e6e6e6; }
        ul.tests { margin: 0; padding: 0; list-style: none; }
        ul.tests li { margin: 0 0 1px; }
        ul.dash { margin: 4px 0 6px 18px; padding: 0; }
        .sign { margin-top: 14px; }
        .stamp { display: inline-block; border: 1px solid #1a7a3c; color: #1a7a3c; padding: 3px 6px; font-size: 8.5px; margin-top: 4px; text-align: left; }
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 96px; font-weight: bold;
            color: #c0392b; opacity: 0.13; transform: rotate(-35deg); z-index: -1; }
        .draft-note { border: 1px solid #c0392b; color: #c0392b; padding: 4px 8px; font-size: 9px; text-align: center; margin-bottom: 8px; }
    </style>
</head>
<body>
@if($draft)
    <div class="watermark">{{ $cancelled ? 'BEKOR QILINGAN' : 'NAMUNA' }}</div>
    <div class="draft-note">
        @if($cancelled)
            Ariza buyurtmachi tomonidan bekor qilingan. Hujjat yuridik kuchga ega emas.
        @else
            NAMUNA — shartnoma hali tasdiqlanmagan. Hujjat tanishish uchun, yuridik kuchga ega emas.
        @endif
    </div>
@endif

@if ($contract->parent_id)
<h1>Laboratoriya sinov ishlarini bajarish to‘g‘risida<br>QO‘SHIMCHA SHARTNOMA № {{ $no }}</h1>
<p style="text-align: center">({{ $contract->parent?->contract_number }}-sonli shartnomaga qo‘shimcha)</p>
@else
<h1>Laboratoriya sinov ishlarini bajarish to‘g‘risida<br>SHARTNOMA № {{ $no }}</h1>
@endif

<table class="head">
    <tr>
        <td style="text-align: left">{{ $date ?? $blank }}</td>
        <td style="text-align: right">{{ $contract->district }}</td>
    </tr>
</table>

<p>{{ $inspectionName }} (keyingi o‘rinlarda “Ijrochi” deb yuritiladi) nomidan, nizom asosida faoliyat yurituvchi Inspeksiya
    boshlig‘i {{ $directorName }} bir tomondan, {{ $contract->customer_name }} (keyingi o‘rinlarda “Buyurtmachi” deb yuritiladi)
    ikkinchi tomondan mazkur shartnomani quyidagilar haqida tuzdilar.</p>

<h2>I. Shartnoma mazmuni</h2>
<p>1.1. Ijrochi Buyurtmachining topshirig‘iga ko‘ra, mazkur shartnomaning 1.2-bandida ko‘rsatilgan qurilish obyektlarida va
    shartnomaning 1-ilovasida ko‘rsatilgan qurilish materiallari, buyumlari va konstruksiyalari bo‘yicha laboratoriya sinovlarini
    va tahlil ishlarini amalga oshirish va bu haqda tegishli hujjatlarni rasmiylashtirish majburiyatini, buyurtmachi esa bajarilgan
    ishlarni qabul qilib tegishli to‘lovlarni o‘z vaqtida amalga oshirish majburiyatini oladi.</p>
<p>1.2. Qurilish obyektining nomi va manzili: {{ $objectTitle }}.</p>
<p>1.3. Ishlar Buyurtmachi yoki uning vakolatli vakili tomonidan ishlarni qabul qilish-topshirish dalolatnomasi imzolangandan
    keyin bajarilgan deb hisoblanadi.</p>

<h2>II. Taraflarning huquq va majburiyatlari</h2>
<p class="bold">Ijrochining huquq va majburiyatlari:</p>
<p>2.1. Ijrochi shartnomaning 1-ilovasida nazarda tutilgan ishlarni bajarishi uchun Buyurtmachidan zarur hujjatlarni taqdim
    qilishni hamda sinov ishlarini o‘tkazish uchun zarur mahsulotlardan namunalar yetkazib berishni talab qilish;</p>
<p>2.2. Ijrochi mazkur shartnomada nazarda tutilgan ishlarni bajarishdan oldin Buyurtmachidan oldindan shartnoma summasining
    kamida ellik foizi miqdoridagi to‘lovni amalga oshirishni, qolgan mablag‘ni bajarilgan ish Buyurtmachi tomonidan qabul
    olinishidandan avval to‘lanishini talab qilish;</p>
<p>2.3. Ijrochi Buyurtmachi tomonidan mazkur shartnomaning 3.2. bandida nazarda tutilgan shartlar bajarilmagan taqdirda
    “Buyurtmachi” ning hisob raqamiga akseptsiz to‘lov talabnomasi qo‘yish yo‘li bilan pul mablag‘ini yechib olish;</p>
<p>2.4. Shartnomada nazarda tutilgan ishlarni kelishilgan muddatda lozim darajada bajarish va Buyurtmachi yoki uning vakiliga
    belgilangan tartibda rasmiylashtirib topshirish.</p>
<p>2.5. O‘tkazilayotgan laboratoriya sinov ishlarini zamonaviy o‘lchov-sinov uskunalaridan foydalangan holda bajarish.</p>
<p>2.6. Shartnomada kelishilgan ishlarni o‘zining kuchlari va/yoki jalb qilingan kuchlar bilan bajarish hamda ishni
    Buyurtmachiga mazkur shartnoma shartlariga muvofiq topshirish;</p>
<p>2.7. Agar Ijrochi zimmasidagi ishlarni bajarish jarayonida me’yoriy hujjatlar va boshqa qurilishga oid normativ talablarga
    zid bo‘lgan xato va kamchiliklar aniqlangan taqdirda nomuvofiqlik to‘g‘risida xulosa beradi.</p>
<p>2.8. Har bir o‘tkazilgan sinov ishlari yakunlanib, to‘lov summasi to‘liq amalga oshirilgandan so‘ng sinov natijalari bo‘yicha
    xulosani besh ish kunidan kechiktirmasdan Buyurtmachiga yoki uning vakiliga taqdim etadi.</p>
<p class="bold">Buyurtmachining huquq va majburiyatlari:</p>
<p>2.9. Ijrochi tomonidan mazkur shartnomaning 1-ilovasida nazarda tutilgan ishlarni lozim darajada bajarilishi va tegishli
    tartibda rasmiylashtirib topshirilishini talab qilish;</p>
<p>2.10. Buyurtmachi sinov natijalaridan norozi bo‘lgan holatlarda, o‘z ixtiyori bilan qonun hujjatlarida belgilangan tartibda
    akkreditatsiyadan o‘tgan mustaqil sinov laboratoriyasiga murojaat qilish;</p>
<p>2.11. Zarur hujjatlarni taqdim qilish, sinov ishlarini o‘tkazish uchun qurilish sinov laboratoriyasiga mahsulotlardan
    namunalarni shartnoma imzolangan kundan boshlab 30 kalendar kun mobaynida to‘liq yetkazib berish;</p>
<p>2.12. Sinov ishlarini o‘tkazish uchun ob’ektda yopiq ishlarni (konstruksiyalarni) Ijrochi ko‘rsatgan sath va koordinatlarda
    o‘z hisobidan ochib (qazib) berish;</p>
<p>2.13. Ijrochi mazkur shartnomada nazarda tutilgan to‘lovni o‘z vaqtida amalga oshirish;</p>
<p>2.14. Buyurtmachi Ijrochiga sinov ishlarini amalga oshirish uchun yetarli shart-sharoitlarni yaratib berish;</p>
<p>2.15. Buyurtmachi tomonidan yopiq ishlarni (konstruksiyalarni) sath va koordinatlarda ochib (qazib) berilgan joylar o‘zi
    tomonidan qayta tiklash;</p>

<h2>III. Shartnomaning bahosi va hisob-kitob tartibi</h2>
<p>3.1. Mazkur shartnomaning bahosi (QQSni hisobga olgan holda) {{ $totalText }} ({{ $totalWords }}) so‘mni tashkil qiladi.
    Ammo to‘lov haqiqatda bajarilgan ishlar uchun amalga oshiriladi.</p>
<p>3.2. Buyurtmachi tomonidan Ijrochiga oldindan shartnoma bahosining kamida ellik foizi miqdorida to‘lov amalga oshiriladi,
    qolgan to‘lovlar bajarilgan ishlar qabul qilib olishidan avval amalga oshiriladi. O‘zbekiston Respublikasi Vazirlar
    Mahkamasi tomonidan belgilangan hollar bundan mustasno.</p>
<p>3.3. Bajarilgan ish uchun to‘lovlar Ijrochining hisob raqamiga pul ko‘chirish yo‘li bilan amalga oshiriladi.</p>
<p>3.4. Mazkur shartnomaning bahosi obyektdagi ishlar hajmi va ish holatlaridan kelib chiqqan holda taraflarning o‘zaro
    kelishuvi bilan o‘zgartirilishi mumkin.</p>

<h2>IV. Taraflarning javobgarligi</h2>
<p>4.1. Ijrochi Buyurtmachiga mazkur shartnoma bo‘yicha ishlar bajarilmagan yoki xizmatlar ko‘rsatilmagan hollarda har bir
    kechiktirilgan kun uchun majburiyatlarni bajarmagan qismining 0,4 foizi miqdorida, ammo bajarilmagan ishlar yoki
    ko‘rsatilmagan xizmatlar qismi bahosining 50 foizidan ortiq bo‘lmagan miqdorda penya to‘laydi.</p>
<p>4.2. Buyurtmachi mazkur shartnoma bo‘yicha bajarilgan ish uchun to‘lovlarni shartnomaning 3-bobida belgilangan tartib va
    muddatda amalga oshirmagan taqdirda, Ijrochiga qarzdorlik summasini va to‘lov kechiktirilgan har bir kun uchun
    kechiktirilgan to‘lov summasining 0,4 foizi miqdorida, ammo kechiktirilgan to‘lov summasining 50 foizidan ortiq bo‘lmagan
    miqdorda penya to‘laydi.</p>
<p>4.3. Mazkur shartnomada ko‘zda tutilmagan taraflarning javobgarligi masalasi O‘zbekiston Respublikasi hududida amalda
    bo‘lgan qonun hujjatlariga muvofiq hal qilinadi.</p>
<p>4.4. Neustoyka to‘lash taraflarning ular oldida turgan majburiyatlarni bajarishdan yoki shartlarning buzilishini bartaraf
    etishdan ozod qilmaydi.</p>
<p>4.5. Ushbu shartnoma bo‘yicha belgilangan xizmatni asossiz ravishda rad etganlik uchun Buyurtmachi Ijrochiga rad etilgan
    xizmat qiymatining 15 foizi miqdorida jarima to‘laydi.</p>
<p>4.6. Ijrochi Buyurtmachining hisob raqamidan pul mablag‘ini asossiz ravishda akseptsiz hisobdan chiqarganlik uchun
    Buyurtmachiga asossiz ravishda akseptsiz hisobidan chiqarilgan summaning 10 foizi miqdorida jarima to‘laydi.</p>

<h2>V. Fors – Major xolatlari</h2>
<p>5.1. Taraflar o‘zlariga bog‘liq bo‘lmagan favqulodda, yengib bo‘lmas kuch (fors-major holatlari) ta’sirida o‘zlarining
    shartnomaviy majburiyatlarini bajarmaganliklari yoki yetarli darajada bajarmaganliklari uchun javobgarlikdan ozod
    qilinadilar.</p>
<p>5.2. Bu holatda majburiyatlarini bajarish muddatlari fors-major holatining davom etish muddatiga mutanosib tarzda
    uzaytiriladi. Fors-major holati 3 oydan ortiq muddat davom etadigan bo‘lsa shartnoma taraflardan birining tashabbusi bilan
    bekor qilinishi mumkin.</p>

<h2>VI. Shartnomani o‘zgartirish va bekor qilish haqida</h2>
<p>6.1. Ushbu shartnoma taraflarning o‘zaro kelishuvi bilan asoslangan xolda va bir tarafning tashabbusi bilan sud tartibida
    O‘zbekiston Respublikasining amaldagi qonun hujjatlariga asosan o‘zgartirilishi va bekor qilinishi mumkin.</p>

<h2>VII. Nizolarni hal qilish tartibi</h2>
<p>7.1. Mazkur shartnomani bajarishda yuzaga keladigan nizolar va kelishmovchiliklar taraflar o‘rtasida muzokaralar yo‘li bilan
    hal qilinadi.</p>
<p>7.2. Taraflar nizoni muzokara yo‘li bilan bartaraf qilishmagan taqdirda, O‘zbekiston Respublikasining amaldagi
    qonunchiligida nazarda tutilgan tartibda {{ $regionName }} tumanlararo iqtisodiy sudi orqali hal etiladi.</p>

<h2>VIII. Alohida shartlar, Korrupsion holatlarni bartaraf etish</h2>
<p>8.1. Shartnoma yuzasidan ishlarni amalga oshirish jarayonida taraflar tomonidan manfaatlar to‘qnashuvini yuzaga keltirishga
    (ta’magirlik, asossiz ravishda muddatlarni cho‘zib yuborish, normativ xujjatlarga rioya etmaslik, sansolarlikka yo‘l
    qo‘yish) yo‘l qo‘ymasliklari shart.</p>
<p>8.2. Taraflarning biri tomonidan korrupsion harakatlarga da’vat qilishlik ikkinchi taraf zimmasiga darhol mazkur holat
    yuzasidan tashkilot rahbariga yozma shaklda xabardor qilish majburiyatini yuklaydi. (2-ilova)</p>

<h2>IX. Konfidensiallik</h2>
<p>9.1. Ushbu shartnomani amal qilish davrida taraflar o‘zlariga ma’lum bo‘lgan mahsulot sifati, sinov natijalari va texnik
    tavsiflari haqidagi ma’lumotlarning maxfiyligini saqlash majburiyatini oladilar. Qurilish mahsulotlarini sinovdan o‘tkazish
    bo‘yicha ishlarni amalga oshiruvchi shaxslar sinov jarayonida olingan ma’lumotlar, sinov natijalari va tomonlarning
    shartnoma majburiyatlari to‘g‘risida maxfiylikni saqlash majburiyatini oladi, aholi hayoti va sog‘ligi xavfsizligi bilan
    bog‘liq hollar bundan mustasno.</p>
<p>9.2. Shartnoma bo‘yicha bir taraf tomonidan boshqa tarafga taqdim etilgan barcha hujjatlar va ma’lumotlar faqat ushbu
    shartnomada nazarda tutilgan maqsadlarda foydalaniladi va har bir taraf olingan ma’lumotlarni konfidensialligini saqlaydi.
    Ma’lumotlar ikkinchi tarafning yozma roziligisiz ushbu shartnomada ko‘zda tutilmagan maqsadlarda uchinchi shaxslarga oshkor
    etilmasligi shart. Ushbu shart mazkur Shartnoma bekor qilingandan keyin 3 yil davomida amal qiladi. Ma’lumotlarni oshkor
    qilgan taraf ikkinchi tarafga yetkazilgan zararni to‘liq qoplaydi va ma’lumotlarni oshkor bo‘lishi natijasida yuzaga kelgan
    oqibatlar uchun javobgar bo‘ladi.</p>

<h2>X. Yakuniy qoidalar</h2>
<p>10.1. Mazkur shartnoma imzolangan kundan (budjet tashkilotlari uchun belgilangan tartibda g‘aznachilikdan ro‘yxatdan
    o‘tgandan so‘ng) kuchga kiradi.</p>
<p>10.2. Mazkur shartnomaga kiritiladigan har qanday o‘zgartirish va qo‘shimchalar, ular faqat yozma shaklda tuzilib,
    taraflarning bunga vakolatli vakillari tomonidan o‘zaro kelishuviga asosan haqiqiy hisoblanadi. Mazkur shartnomaga ilovalar
    uning ajralmas qismi bo‘lib hisoblanadi.</p>
<p>10.3. Ijrochi tomonidan ushbu shartnomani 2.2 bandini ijrosi ta’minlangandan so‘ng inspeksiya tomonidan qurilish ishlarini
    boshlash uchun ro‘yxatga olinishiga ruxsat beradi.</p>
<p>10.4. Mazkur shartnoma ikki nusxada tuzildi. Ikkala nusxa aynan bir xil yuridik kuchga ega. Tomonlarning har birida mazkur
    shartnomaning bir nusxasi saqlanadi.</p>
<p>10.5. Yuridik manzil, bank rekvizitlari o‘zgarganda va boshqa o‘zgarishlar bo‘lganda, tomonlar bu haqda bir birlarini 10 kun
    ichida darhol yozma ravishda xabardor qilish majburiyatlarini o‘z zimmalariga oladilar.</p>

<h2>XI. Tomonlarning yuridik manzillari, bank rekvizitlari va imzolari:</h2>
<table class="parties">
    <tr>
        <td>
            <p class="bold">“Ijrochi”</p>
            <p>Nomi: {{ $inspectionName }}</p>
            <p>Manzil: {{ $inspection?->address }}</p>
            <p>G‘azna H/r: {{ $requisite?->personal_account }}</p>
            <p>Bank nomi: {{ $requisite?->bank_name ?: 'Markaziy bank hisob-kitob kassa markazi' }}</p>
            <p>Bank kodi: {{ $requisite?->bank_code ?: '00014' }}</p>
            <p>STIR: {{ $inspection?->stir ?: $requisite?->inn }}</p>
            <p>Tel: {{ $inspection?->phone }}</p>
            <p>Inspeksiya boshlig‘i: {{ $directorName }}</p>
            <p class="sign">Inspeksiya boshlig‘i: ____________ {{ $directorName }}</p>
            @if(!$draft)
                <div class="stamp">Elektron tasdiqlangan<br>{{ $directorSignedAt }}</div>
            @endif
        </td>
        <td>
            <p class="bold">“Buyurtmachi”</p>
            <p>Nomi: {{ $contract->customer_name }}</p>
            <p>Manzil: {{ collect([$regionName, $contract->district])->filter()->implode(', ') }}</p>
            <p>{{ $isLegal ? 'STIR' : 'JShShIR' }}: {{ $contract->customer_tin }}</p>
            <p>Tel: {{ $contract->customer_phone }}</p>
            <p class="sign">{{ $customerSigner }}: ____________ {{ $contract->customer_name }}</p>
            @if(!$draft)
                <div class="stamp">Elektron tasdiqlangan<br>{{ $customerSignedAt }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="page-break"></div>

{{-- 1-ilova --}}
<p class="right"><b>{{ $no }} -sonli shartnomaga 1-ilova</b><br>{{ $date ?? $blank }}</p>
<h2>{{ $contract->test_type_name }} ishlari ro‘yxati va narxi (bloklar bo‘yicha: {{ $blockNames }})</h2>

<table class="tb">
    <thead>
    <tr>
        <th style="width: 4%">№</th>
        <th style="width: 16%">Ish turi</th>
        <th style="width: 42%">Sinovlar (narxi, so‘m)</th>
        <th style="width: 12%">Miqdori</th>
        <th style="width: 11%">Birlik narxi (so‘m)</th>
        <th style="width: 15%">Summa (so‘m)</th>
    </tr>
    </thead>
    <tbody>
    @foreach($blocks as $index => $block)
        <tr class="bh">
            <td colspan="5">{{ $index + 1 }}. {{ $block['name'] }}</td>
            <td class="n">{{ $money($block['sum']) }}</td>
        </tr>
        @forelse($block['stages'] as $stage)
            <tr class="st">
                <td colspan="5">{{ $stage['title'] }}</td>
                <td class="n">{{ $money($stage['sum']) }}</td>
            </tr>
            @foreach($stage['rows'] as $row)
                <tr>
                    <td class="c">{{ $row['n'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>
                        <ul class="tests">
                            @foreach($row['tests'] as $test)
                                <li>• {{ $test }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td class="c">{{ $row['quantity'] }} {{ $row['unit'] }}</td>
                    <td class="n">{{ $money($row['unit_price'], 0) }}</td>
                    <td class="n">{{ $money($row['sum']) }}</td>
                </tr>
            @endforeach
        @empty
            <tr>
                <td colspan="6" class="c">Bu blok uchun ishlar tanlanmagan.</td>
            </tr>
        @endforelse
        <tr class="bt">
            <td colspan="5">{{ $block['name'] }} bo‘yicha jami</td>
            <td class="n">{{ $money($block['sum']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p class="right" style="margin-top: 6px"><b>jami: {{ $money($contract->total_price) }} so‘m</b></p>

<table class="tot">
    <tr>
        @foreach($blocks as $block)
            <th>{{ $block['name'] }}<br>(QQSsiz)</th>
        @endforeach
        <th>QQSsiz jami</th>
        <th>QQS ({{ rtrim(rtrim(number_format((float)$contract->vat_percent, 2, '.', ''), '0'), '.') }}%)</th>
        <th>Jami to‘lov<br>(QQS bilan)</th>
    </tr>
    <tr>
        @foreach($blocks as $block)
            <td>{{ $money($block['sum']) }}</td>
        @endforeach
        <td>{{ $money($contract->total_price) }}</td>
        <td>{{ $money($contract->total_vat) }}</td>
        <td><b>{{ $money($contract->total_with_vat) }}</b></td>
    </tr>
</table>

<p style="margin-top: 8px">Jami to‘lov so‘z bilan: {{ $totalWords }}.</p>

<div class="page-break"></div>

{{-- 2-ilova --}}
<p class="right"><b>{{ $no }} -sonli shartnomaga 2-ilova</b><br>{{ $date ?? $blank }}</p>
<h2>Korrupsiyaga qarshi kurashishda<br>taraflarning shartnomaviy munosabati</h2>

<p>Bitim bo‘yicha o‘z majburiyatlarini bajarayotganda tomonlar O‘zbekiston Respublikasining 2017-yil 3-yanvardagi O‘RQ-419-son
    “Korrupsiyaga qarshi kurashish to‘g‘risida”gi Qonuni hujjati talablariga zid hatti-harakatlarni amalga oshirmaydilar, shu
    jumladan:</p>
<ul class="dash">
    <li>taklif qilmaydilar;</li>
    <li>va’da bermaydilar;</li>
    <li>tovlamachilik qilmaydilar;</li>
    <li>to‘lamaydilar;</li>
    <li>qabul qilishga rozilik berilishi;</li>
    <li>ular bevosita yoki bilvosita, (pora) bergan shaxsning manfaatlari uchun ishlash yoki ishlamaslik uchun moddiy boyliklar
        yoki mulkiy imtiyozlarni pora olmaydilar. Tomonlar bu harakatlarning oldini olish bo‘yicha chora-tadbirlar qabul
        qilinishini kafolatlaydi.</li>
</ul>
<p>Agar tashkilot-kontragent {{ $contract->customer_name }} (keyingi o‘rinlarda “Buyurtmachi” deb yuritiladi) ushbu shartnoma
    bo‘yicha korrupsiya jinoyatini sodir etishga majburlash, shu jumladan, hududiy inspeksiya xodimlarining harakatlari
    O‘zbekiston Respublikasi qonun hujjatlariga va axloq normalariga zid bo‘lgan holatlarga duch kelganda, quyidagi aloqa
    kanallaridan birini xabardor qilishi kerak:</p>
<ul class="dash">
    <li>telefon raqami (komplayens) tel. 95-142-04-17.</li>
    <li>elektron pochta orqali g.abdullayeva@exat.uz, info@tvdaqn.uz</li>
</ul>

<p class="bold" style="margin-top: 12px">Taraflar:</p>
<p class="sign">Inspeksiya boshlig‘i: ____________ {{ $directorName }}</p>
<p class="sign">{{ $customerSigner }}: ____________ {{ $contract->customer_name }}</p>
</body>
</html>
