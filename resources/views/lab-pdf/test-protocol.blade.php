@php
    /** @var \App\Models\LabTest $test */
    $verdictClass = function (string $v) {
        $t = mb_strtolower(trim($v));
        if ($t === '') return '';
        if (str_starts_with($t, 'muvofiq emas') || str_starts_with($t, 'nomuvofiq') || str_starts_with($t, 'ishonchsiz') || str_starts_with($t, 'salbiy')) return 'bad';
        if (str_starts_with($t, 'muvofiq') || str_starts_with($t, 'ijobiy')) return 'ok';
        return '';
    };
    $hasGroupTitles = collect($groups)->contains(fn($g) => !empty($g['title']));
@endphp
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Sinov bayonnomasi № {{ $number }}</title>
    <style>
        @page { size: A4; margin: 12mm 12mm 12mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5px; line-height: 1.3; color: #000; }
        h1 { font-size: 12.5px; text-align: center; margin: 8px 0 3px; font-weight: bold; }
        h3 { font-size: 9.5px; margin: 10px 0 3px; font-weight: bold; }
        p { margin: 0 0 3px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .muted { color: #555; }
        .lab-head { text-align: center; font-size: 10.5px; font-weight: bold; }
        .lab-addr { text-align: center; font-size: 8px; color: #555; margin-bottom: 6px; }
        .approve { text-align: right; font-size: 8.5px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        table.info { margin-top: 6px; }
        table.info th, table.info td { border: 0.6px solid #000; padding: 2px 5px; vertical-align: top; text-align: left; }
        table.info th { width: 27%; background: #f0f0f0; font-weight: bold; }
        table.tb { margin-top: 2px; font-size: 7.8px; }
        table.tb th, table.tb td { border: 0.6px solid #000; padding: 3px 4px; vertical-align: top; }
        table.tb th { background: #e6e6e6; text-align: center; font-weight: bold; }
        table.tb td.c { text-align: center; }
        tr.grp td { background: #eef1f8; font-weight: bold; text-align: left; }
        .cap { width: 100%; margin-top: 10px; }
        .cap td { padding: 0; border: 0; font-size: 9.5px; }
        .ok { color: #1a7a3c; font-weight: bold; }
        .bad { color: #c0392b; font-weight: bold; }
        .result { margin-top: 8px; font-size: 9.5px; }
        table.sign { margin-top: 16px; }
        table.sign td { border: 0; padding: 0 6px 0 0; vertical-align: top; font-size: 8.5px; }
        .stamp { display: inline-block; border: 1px solid #1a7a3c; color: #1a7a3c; padding: 2px 5px; font-size: 7.5px; margin-top: 3px; }
        .keep { page-break-inside: avoid; }
        .foot { margin-top: 10px; font-size: 7.5px; color: #333; }
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 88px; font-weight: bold;
            color: #c0392b; opacity: 0.12; transform: rotate(-35deg); z-index: -1; }
        .draft-note { border: 1px solid #c0392b; color: #c0392b; padding: 3px 8px; font-size: 8px; text-align: center; margin-bottom: 6px; }
    </style>
</head>
<body>
@if($draft)
    <div class="watermark">{{ $rejected ? 'RAD ETILGAN' : 'NAMUNA' }}</div>
    <div class="draft-note">
        @if($rejected)
            Bayonnoma laboratoriya rahbari tomonidan rad etilgan. Hujjat kuchga ega emas.
        @else
            NAMUNA — bayonnoma hali laboratoriya rahbari tomonidan tasdiqlanmagan.
        @endif
    </div>
@endif

@if(!empty($labName))
    <div class="lab-head">{{ $labName }}</div>
    <div class="lab-addr">{{ $labAddress }}</div>
@endif

<div class="approve">
    «TASDIQLAYMAN»<br>
    Laboratoriya rahbari: {{ $reviewer ?: '____________' }}<br>
    @if($confirmedAt)
        {{ $confirmedAt }}<br><span class="stamp">Elektron tasdiqlangan · {{ $confirmedAt }}</span>
    @else
        «____» ____________ 20__ yil
    @endif
</div>

<h1>SINOV BAYONNOMASI № {{ $number }}</h1>
<div class="center muted">
    Rasmiylashtirilgan sana: {{ $formedAt ?: '__________' }} · Tasdiqlangan sana: {{ $confirmedAt ?: '__________' }}
</div>

<table class="info">
    @foreach($info as [$label, $value])
        <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
    @endforeach
</table>

<table class="cap"><tr><td><b>Sinov natijalari</b></td><td class="right">1-jadval</td></tr></table>
<table class="tb">
    <thead>
    <tr>
        <th style="width:4%">№</th>
        <th style="width:19%">Ko'rsatkich nomi</th>
        <th style="width:7%">O'lchov birligi</th>
        <th style="width:14%">Talablar (NH bandi)</th>
        <th style="width:11%">Sinov usuli</th>
        <th style="width:17%">NH bo'yicha qiymat</th>
        <th style="width:18%">Haqiqiy qiymat (X ± U, k=2, P≈95 %)</th>
        <th style="width:10%">Muvofiqligi</th>
    </tr>
    </thead>
    <tbody>
    @forelse($groups as $group)
        @if($hasGroupTitles)
            <tr class="grp"><td class="c">{{ $group['no'] }}</td><td colspan="7">{{ $group['title'] }}</td></tr>
        @endif
        @foreach($group['rows'] as $row)
            <tr>
                <td class="c">{{ $row['no'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="c">{{ $row['unit'] }}</td>
                <td class="c">{{ $row['requirement'] }}</td>
                <td class="c">{{ $row['method'] }}</td>
                <td class="c">{{ $row['norm'] }}</td>
                <td class="c">{{ $row['fact'] }}</td>
                <td class="c {{ $verdictClass($row['verdict']) }}">{{ $row['verdict'] !== '' ? $row['verdict'] : '—' }}</td>
            </tr>
        @endforeach
    @empty
        <tr><td colspan="8" class="c">Natijalar kiritilmagan</td></tr>
    @endforelse
    </tbody>
</table>

@if($summary)
    <table class="cap"><tr><td><b>{{ $summaryTitle }}</b></td><td class="right">2-jadval</td></tr></table>
    <table class="tb">
        @if($summary['heads'])
            <thead><tr>@foreach($summary['heads'] as $head)<th>{{ $head }}</th>@endforeach</tr></thead>
        @endif
        <tbody>
        @foreach($summary['rows'] as $cells)
            <tr>@foreach($cells as $cell)<td class="{{ $verdictClass($cell) }}">{{ $cell }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($resultName)
    <div class="result"><b>Bayonnoma bo'yicha umumiy natija:</b>
        <span class="{{ $verdictClass($resultName) }}">{{ $resultName }}</span></div>
@endif

@foreach($notes as $note)
    <p style="margin-top:6px">{{ $note }}</p>
@endforeach

{{-- sarlavha jadvalidan ajralib qolmasligi uchun blok bo'linmaydi --}}
<div class="keep">
<h3>Qo'llanilgan sinov uskunalari va o'lchash vositalari</h3>
<table class="tb">
    <thead>
    <tr>
        <th style="width:4%">№</th><th style="width:30%">Nomi, markasi</th><th style="width:13%">Kodi</th><th style="width:11%">Zavod №</th>
        <th style="width:11%">Hujjat</th><th style="width:18%">Hujjat №</th><th style="width:13%">Amal qilish muddati</th>
    </tr>
    </thead>
    <tbody>
    @forelse($instruments as $i => $instrument)
        @php($cert = $instrument['certificate'] ?? null)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td>{{ $instrument['name'] }}</td>
            <td>{{ $instrument['code'] }}</td>
            <td>{{ $instrument['serial_number'] ?: '—' }}</td>
            <td>{{ $cert['doc_type_name'] ?? '—' }}</td>
            <td>{{ $cert ? $cert['number'] . ($cert['issued_at'] ? ' (' . \Carbon\Carbon::parse($cert['issued_at'])->format('d.m.Y') . ')' : '') : '—' }}</td>
            <td>{{ !empty($cert['valid_to']) ? \Carbon\Carbon::parse($cert['valid_to'])->format('d.m.Y') : '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="c">—</td></tr>
    @endforelse
    </tbody>
</table>

<table class="sign">
    <tr>
        <td>{{ $signers[0] }}: <b>{{ $laborant ?: '____________________' }}</b></td>
        <td>{{ $signers[1] }}: <b>{{ $laborant ?: '____________________' }}</b></td>
        <td>{{ $signers[2] }}: ____________________</td>
    </tr>
</table>

</div>

<p class="foot">Sinov natijalari faqat buyurtmachi taqdim etgan va sinovdan o'tkazilgan namunalarga taalluqli.
    Bayonnomani laboratoriya ruxsatisiz to'liq yoki qisman ko'paytirish va tarqatish taqiqlanadi.</p>
</body>
</html>
