@php
    use Carbon\Carbon;
    use App\Enums\UserRoleEnum;
    use App\Models\User;
        $taskId = $object->task_id ?: $object->manual_task_id;
        $deadline = $object->deadline
            ? Carbon::parse($object->deadline)->toIso8601String()
            : null;

        $objectAddress = $object->location_building ?? '';
        $regionName = $object->region->name_uz ?? '';

        $latitude = $object->lat;
        $longitude = $object->long;


        $mapUrl = filled($latitude) && filled($longitude)
            ? 'https://www.google.com/maps?q=' . urlencode($latitude . ',' . $longitude)
            : 'https://www.google.com/maps/search/?api=1&query='
                . urlencode(trim($objectAddress . ', ' . $regionName));
@endphp

    <!doctype html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Obyekt — ShaffofQurilish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Anton&family=Roboto:wght@400;500;700&family=Roboto+Mono:wght@500&display=swap">
    <style>
        :root {
            --brand: #2c4a8a;
            --blue: #1a73e8;
            --ink: #1f2430;
            --muted: #5b6475;
            --line: #e8ecf4;
            --soft: #f1f5fd;
            --soft-line: #dbe3f3;
            --bg: #fff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef1f7;
            font-family: 'Roboto', system-ui, sans-serif;
            color: var(--ink);
            -webkit-text-size-adjust: 100%;
        }

        .page {
            max-width: 520px;
            margin: 0 auto;
            background: var(--bg);
            min-height: 100vh;
            box-shadow: 0 0 24px rgba(30, 50, 100, .08);
        }

        .hero {
            position: relative;
            background: linear-gradient(135deg, #2c4a8a, #3d62ad);
            min-height: 104px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .hero h1 {
            margin: 0;
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .share {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border: 0;
            border-radius: 22px;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .timer {
            margin: -28px 16px 0;
            position: relative;
            background: #f8f9fc;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(30, 50, 100, .15);
            padding: 12px 8px 10px;
            text-align: center;
        }

        .timer-title {
            font-size: 15px;
            letter-spacing: .2px;
        }

        .timer-row {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 10px;
            margin-top: 2px;
        }

        .unit {
            min-width: 65px;
        }

        .num {
            font-family: 'Anton', sans-serif;
            font-size: 34px;
            line-height: 40px;
            color: var(--blue);
        }

        .lbl {
            font-size: 12px;
            font-weight: 700;
            color: #8a93a5;
            letter-spacing: .5px;
        }

        .sep {
            font-size: 22px;
            line-height: 40px;
            color: #b5bccb;
        }

        .timer.done .num {
            color: var(--muted);
        }

        .content {
            padding: 8px 16px;
        }

        .field {
            padding: 14px 0;
            border-bottom: 1px solid var(--line);
        }

        .field:last-child {
            border-bottom: 0;
        }

        .label {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .value {
            font-size: 15px;
            font-weight: 700;
            line-height: 21px;
            overflow-wrap: anywhere;
        }

        .org {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            border-radius: 50%;
            background: #f3f5fa;
            color: #b8650a;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .org-logos {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            flex-shrink: 0;
        }

        .person {
            background: var(--soft);
            border-radius: 12px;
            padding: 12px 12px 4px;
            margin-top: 10px;
        }

        .person-role {
            font-size: 12px;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 2px;
        }

        .person-name {
            font-size: 14px;
            font-weight: 700;
            line-height: 20px;
            padding-bottom: 8px;
            overflow-wrap: anywhere;
        }

        .cert {
            border-top: 1px solid var(--soft-line);
            padding: 8px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .cert-info {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 2px 10px;
            min-width: 0;
        }

        .cert-no {
            font-family: 'Roboto Mono', monospace;
            font-size: 14px;
            font-weight: 500;
            overflow-wrap: anywhere;
        }

        .cert-dir {
            font-size: 12px;
            font-weight: 400;
            color: #67718a;
        }

        .cert-none {
            min-height: 44px;
        }

        .none-text {
            font-size: 13px;
            color: #67718a;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .none-text::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 3px;
            background: #b9c2d3;
        }

        .copy {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #1a5fd0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .copy:hover {
            background: rgba(26, 95, 208, .08);
        }

        .copy.ok {
            color: #0b7a4b;
        }

        .empty {
            border: 1px dashed #b9c2d3;
            border-radius: 12px;
            padding: 12px;
            font-size: 14px;
            color: var(--muted);
            margin-top: 10px;
        }

        .addr {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .pin {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d64545;
            border-radius: 10px;
        }

        .pin:hover {
            background: #fdeeee;
        }

        .docs {
            padding: 4px 16px 8px;
            border-top: 1px solid var(--line);
        }

        .doc {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
        }

        .doc:last-child {
            border-bottom: 0;
        }

        .doc-name {
            flex: 1;
            min-width: 0;
            font-size: 14px;
            line-height: 19px;
        }

        .doc-no {
            font-size: 13px;
            color: #7a8396;
            min-width: 72px;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 14px;
            border: 0;
            border-radius: 999px;
            background: var(--blue);
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
        }

        .btn:hover {
            background: #1565d0;
        }

        .btn:disabled {
            opacity: .65;
            cursor: wait;
        }

        footer {
            padding: 16px;
            color: var(--muted);
            font-size: 14px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            color: var(--ink);
            font-size: 14px;
        }

        .brand svg {
            color: var(--blue);
        }

        .defects {
            padding: 12px 16px 18px;
            border-top: 1px solid var(--line);
        }

        .defects-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 12px;
            color: var(--ink);
            background: #f7f9fd;
            border: 1px solid #e3eaf5;
            border-radius: 12px;
            text-decoration: none;
        }

        .defects-link-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            color: #b7791f;
            background: #fff3dc;
            border-radius: 10px;
        }

        .defects-link-text {
            display: grid;
            gap: 4px;
            min-width: 0;
            flex: 1;
        }

        .defects-link-text strong {
            font-size: 13px;
            line-height: 18px;
        }

        .defects-link-text small {
            color: var(--muted);
            font-size: 11px;
            line-height: 15px;
        }

        .defects-link-arrow {
            color: #7b879b;
            font-size: 27px;
            line-height: 1;
        }

        /* Kamchiliklar sahifasi */
        [hidden] {
            display: none !important;
        }

        .dfx-hero {
            background: linear-gradient(135deg, #2c4a8a, #3d62ad);
            color: #fff;
            padding: 12px 16px 18px;
        }

        .dfx-back {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-height: 44px;
            margin-left: -8px;
            padding: 0 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border-radius: 10px;
        }

        .dfx-back:hover {
            background: rgba(255, 255, 255, .14);
        }

        .dfx-hero h1 {
            margin: 2px 0 4px;
            color: #fff;
            font-size: 20px;
            line-height: 26px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .dfx-sub {
            font-size: 13px;
            opacity: .9;
        }

        .dfx-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #f6f8fc;
        }

        .dfx-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .dfx-stat {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px 12px;
        }

        .dfx-stat b {
            display: block;
            font-size: 22px;
            line-height: 28px;
        }

        .dfx-stat span {
            font-size: 12px;
            color: var(--muted);
        }

        .dfx-stat.ok b {
            color: #0b6b43;
        }

        .dfx-stat.warn b {
            color: #a24a00;
        }

        .dfx-stat.bad b {
            color: #b3261e;
        }

        .dfx-bar {
            grid-column: 1 / -1;
            height: 8px;
            border-radius: 4px;
            background: #e1e7f2;
            overflow: hidden;
            display: flex;
        }

        .dfx-bar i {
            display: block;
            height: 100%;
        }

        .dfx-search {
            display: flex;
            gap: 8px;
        }

        .dfx-search input {
            flex: 1;
            min-width: 0;
            height: 48px;
            padding: 0 16px;
            border: 1px solid var(--soft-line);
            border-radius: 24px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 15px;
        }

        .dfx-search input:focus {
            outline: 2px solid var(--blue);
            outline-offset: 1px;
            border-color: transparent;
        }

        .dfx-search button {
            height: 48px;
            padding: 0 18px;
            border: 0;
            border-radius: 24px;
            background: var(--brand);
            color: #fff;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .dfx-found {
            font-size: 13px;
            color: var(--muted);
        }

        .dfx-found a {
            color: #1a5fd0;
        }

        .dfx-flabel {
            margin-bottom: 6px;
            font-size: 12px;
            color: var(--muted);
        }

        .dfx-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .dfx-chip {
            min-height: 44px;
            padding: 0 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--soft-line);
            border-radius: 22px;
            background: #fff;
            color: var(--ink);
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
        }

        .dfx-chip em {
            font-style: normal;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
        }

        .dfx-chip[aria-current="true"] {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .dfx-chip[aria-current="true"] em {
            color: #d6e0f7;
        }

        .dfx-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .dfx-empty {
            padding: 28px 12px;
            text-align: center;
            color: var(--muted);
            font-size: 14px;
        }

        .dfx-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }

        .dfx-card summary {
            list-style: none;
            cursor: pointer;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .dfx-card summary::-webkit-details-marker {
            display: none;
        }

        .dfx-card summary:hover {
            background: #fafbfe;
        }

        .dfx-card summary:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: -2px;
        }

        .dfx-row1 {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .dfx-no {
            font-size: 16px;
            font-weight: 700;
            line-height: 22px;
        }

        .dfx-date {
            font-size: 12px;
            color: var(--muted);
        }

        .dfx-badges {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }

        .dfx-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 16px;
            text-align: right;
        }

        .dfx-badge::before {
            content: "";
            width: 7px;
            height: 7px;
            flex-shrink: 0;
            border-radius: 4px;
            background: currentColor;
        }

        .dfx-st-warn {
            background: #fff1e3;
            color: #a24a00;
        }

        .dfx-st-ok {
            background: #e3f6ec;
            color: #0b6b43;
        }

        .dfx-st-bad {
            background: #fdecea;
            color: #b3261e;
        }

        .dfx-who {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2px 8px;
            font-size: 13px;
            line-height: 18px;
        }

        .dfx-who .k {
            color: var(--muted);
        }

        .dfx-who .v {
            min-width: 0;
            font-weight: 500;
            overflow-wrap: anywhere;
        }

        .dfx-who .v small {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 400;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .dfx-row3 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: 13px;
            color: var(--muted);
        }

        .dfx-row3 b {
            color: var(--ink);
        }

        .dfx-chev {
            display: flex;
            color: #1a5fd0;
            transition: transform .15s;
        }

        .dfx-card[open] .dfx-chev {
            transform: rotate(180deg);
        }

        .dfx-details {
            border-top: 1px solid var(--line);
            padding: 10px 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fbfcfe;
        }

        .dfx-item {
            background: var(--soft);
            border-radius: 12px;
            padding: 12px;
        }

        .dfx-item-top {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .dfx-n {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: var(--brand);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }

        .dfx-type {
            font-size: 13px;
            font-weight: 700;
            line-height: 20px;
        }

        .dfx-fld {
            margin-top: 10px;
        }

        .dfx-fld .l {
            margin-bottom: 2px;
            font-size: 12px;
            color: var(--muted);
        }

        .dfx-fld .t {
            font-size: 14px;
            line-height: 20px;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .dfx-foot {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--soft-line);
            font-size: 13px;
        }

        .dfx-foot .k {
            color: var(--muted);
        }

        .dfx-foot .v {
            font-weight: 500;
        }

        .dfx-more {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            border: 1px solid var(--soft-line);
            border-radius: 12px;
            background: #fff;
            color: #1a5fd0;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .dfx-more:hover {
            background: var(--soft);
        }

        .dfx-top {
            align-self: center;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
        }

        @media (max-width: 380px) {
            .doc {
                flex-wrap: wrap;
            }

            .doc-name {
                flex-basis: 100%;
            }

            .doc-no {
                text-align: left;
                flex: 1;
            }

            .unit {
                min-width: 55px;
            }

            .num {
                font-size: 30px;
            }
        }
    </style>
</head>
<body>


<div class="page">
    <div id="viewObject">
        <header class="hero">
            <h1>ID: <span id="objId"></span></h1>
            <button class="share" id="shareBtn" type="button" aria-label="Ulashish">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="18" cy="5" r="3"/>
                    <circle cx="6" cy="12" r="3"/>
                    <circle cx="18" cy="19" r="3"/>
                    <path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>
                </svg>
            </button>
        </header>

        <section class="timer" id="timer" aria-live="off">
            <div class="timer-title">QURILISH YAKUNLANISHIGA</div>
            <div class="timer-row">
                <div class="unit">
                    <div class="num" id="tD">0</div>
                    <div class="lbl">KUN</div>
                </div>
                <div class="sep">:</div>
                <div class="unit">
                    <div class="num" id="tH">0</div>
                    <div class="lbl">SOAT</div>
                </div>
                <div class="sep">:</div>
                <div class="unit">
                    <div class="num" id="tM">0</div>
                    <div class="lbl">MINUT</div>
                </div>
            </div>
        </section>

        <main class="content" id="content"></main>
        <section class="docs" id="docs" aria-label="Hujjatlar"></section>
        <section class="defects" id="defects" aria-label="Kamchiliklar">
            <a class="defects-link" href="#kamchiliklar">
            <span class="defects-link-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 9v4"/><path d="M12 17h.01"/>
                    <path d="M10.3 3.9 1.8 18.2A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                </svg>
            </span>
                <span class="defects-link-text">
                <strong>Qurilish jarayonida aniqlangan kamchiliklar</strong>
                <small>Ko‘rsatmalar va aniqlangan kamchiliklarni ko‘rish</small>
            </span>
                <span class="defects-link-arrow" aria-hidden="true">›</span>
            </a>
        </section>
    </div>

    @php

    $roles = [5,6,7]

//        $dfxDoneStatusIds = [];
//
//        $dfxRows = collect($object->regulations ?? [])->map(function ($reg) use ($dfxDoneStatusIds) {
//            $violations = collect($reg->violations ?? [])->map(fn ($v) => [
//                'workType'    => data_get($v, 'work_type_name'),   // Ish turi
//                'defect'      => data_get($v, 'description'),      // Aniqlangan kamchilik
//                'instruction' => data_get($v, 'instruction'),      // Berilgan ko'rsatma
//                'block'       => data_get($v, 'block_name'),       // Blok nomi
//            ])->values()->all();
//
//            $statusName = (string) data_get($reg, 'regulationStatus.name', '');   // holat nomi (name/title/...)
//            $done = ! empty($dfxDoneStatusIds)
//                ? in_array((int) data_get($reg, 'regulation_status_id'), $dfxDoneStatusIds, true)
//                : mb_stripos($statusName, 'bartaraf') !== false;
//
//            $created  = data_get($reg, 'created_at') ? Carbon::parse($reg->created_at) : null;
//            $deadline = data_get($reg, 'deadline') ? Carbon::parse($reg->deadline) : null;
//
//            return [
//                'no'        => data_get($reg, 'number') ?? data_get($reg, 'id'),   // Ko'rsatma raqami
//                'createdTs' => $created?->timestamp ?? 0,
//                'created'   => $created?->format('d.m.Y'),
//                'status'    => $statusName,
//                'done'      => $done,
//                'deadline'  => $deadline?->format('d.m.Y H:i'),
//                'overdue'   => ! $done && $deadline && $deadline->isPast(),
//                'byFio'     => data_get($reg, 'issuer.full_name'),                 // Kim tomonidan berildi
//                'byPos'     => data_get($reg, 'issuer.position'),
//                'toFio'     => data_get($reg, 'recipient.full_name'),              // Kimga berildi
//                'toPos'     => data_get($reg, 'recipient.position'),
//                'toRole'    => ucfirst(mb_strtolower((string) data_get($reg, 'recipient.position', ''))),
//                'violations' => $violations,
//                'count'     => count($violations),
//            ];
//        })
//            ->sort(fn ($a, $b) => ($a['done'] <=> $b['done']) ?: ($b['createdTs'] <=> $a['createdTs']))
//            ->values();
//
//        $dfxTotal  = $dfxRows->count();
//        $dfxDone   = $dfxRows->where('reg', true)->count();
//        $dfxActive = $dfxTotal - $dfxDone;
//        $dfxLate   = $dfxRows->where('overdue', true)->count();
//        $dfxDefect = $dfxRows->sum('count');
//
//        $dfxRoles  = $dfxRows->pluck('toRole')->filter()->unique()->values();
//        $fStatus   = in_array(request('status'), ['active', 'done'], true) ? request('status') : 'all';
//        $fRole     = $dfxRoles->contains(fn ($x) => $x === request('role')) ? request('role') : 'all';
//        $fQ        = preg_replace('/\D/', '', (string) request('q', ''));
//        $fLimit    = max(10, (int) request('limit', 10));
//
//        $dfxUrl = fn (array $q = [], string $hash = 'kamchiliklar') => request()->fullUrlWithQuery(
//            array_map(fn ($x) => $x === 'all' ? null : $x, array_merge(['limit' => null], $q))
//        ) . '#' . $hash;
//
//        $dfxShown = $dfxRows->filter(fn ($r) =>
//            ($fQ === '' || str_contains((string) $r['no'], $fQ))
//            && ($fStatus === 'all' || ($fStatus === 'done') === $r['done'])
//            && ($fRole === 'all' || $r['toRole'] === $fRole)
//        )->values();
//
//        $dfxFound   = $dfxShown->count();
//        $dfxVisible = $dfxShown->take($fLimit);
//        $dfxLeft    = $dfxFound - $dfxVisible->count();
//        $dfxOpened  = 0;
    @endphp

    <div id="viewDefects" hidden>
        <div class="dfx" id="dfx-top">

            <header class="dfx-hero">
                <a class="dfx-back" href="#obyekt" aria-label="Obyekt sahifasiga qaytish">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="M15 6l-6 6 6 6"/>
                    </svg>
                    Ortga
                </a>

                <h1>Qurilish jarayonida aniqlangan kamchiliklar</h1>
                <div class="dfx-sub">Obyekt ID: {{ $taskId }}</div>
            </header>

            <main class="dfx-body">

                @if ($regulation_count === 0)

                    <div class="dfx-empty">
                        Qurilish jarayonida kamchiliklar aniqlanmagan
                    </div>

                @else

                    @php
                        $pendingRegulations = $regulation_count - $finished_regulations;
                        $finishedPercent = $regulation_count > 0
                            ? round($finished_regulations / $regulation_count * 100, 2)
                            : 0;

                        $pendingPercent = 100 - $finishedPercent;
                    @endphp

                    {{-- Umumiy statistika --}}
                    <section class="dfx-summary" aria-label="Umumiy xulosa">

                        <div class="dfx-stat">
                            <b>{{ $regulation_count }}</b>
                            <span>Jami ko‘rsatma</span>
                        </div>

                        <div class="dfx-stat">
                            <b>{{ $violation_count }}</b>
                            <span>Jami kamchilik</span>
                        </div>

                        <div class="dfx-stat ok">
                            <b>{{ $finished_regulations }}</b>
                            <span>Bartaraf etilgan</span>
                        </div>

                        <div class="dfx-stat warn">
                            <b>{{ $pendingRegulations }}</b>
                            <span>Jarayonda</span>
                        </div>

                        <div class="dfx-bar"
                             role="img"
                             aria-label="Bartaraf etilgan {{ $finished_regulations }} / {{ $regulation_count }}">

                            <i style="width: {{ $finishedPercent }}%; background: #2e9e6b"></i>

                            <i style="width: {{ $pendingPercent }}%; background: #e08a2e"></i>
                        </div>

                    </section>

                    {{-- Qidiruv --}}
                    <form class="dfx-search"
                          method="GET"
                          action="{{ url()->current() }}#kamchiliklar">

                        <input
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            autocomplete="off"
                            placeholder="Ko‘rsatma raqami bo‘yicha qidirish"
                            aria-label="Ko‘rsatma raqami bo‘yicha qidirish"
                        >

                        <button type="submit">Qidirish</button>
                    </form>

                    {{-- Holat filtrlari --}}
                    <section aria-label="Filtrlar">

                        <div class="dfx-flabel">Holati</div>

                        <div class="dfx-chips">
                            <a class="dfx-chip" aria-current="true">
                                Hammasi <em>{{ $regulation_count }}</em>
                            </a>

                            <a class="dfx-chip">
                                Jarayonda <em>{{ $pendingRegulations }}</em>
                            </a>

                            <a class="dfx-chip">
                                Bartaraf etildi <em>{{ $finished_regulations }}</em>
                            </a>
                        </div>

                        {{-- Kimga berilgan --}}
                        @if (count($roles) > 1)

                            <div class="dfx-flabel" style="margin-top: 12px">
                                Kimga berilgan
                            </div>

                            <div class="dfx-chips">

                                <a class="dfx-chip">
                                    Hammasi <em>{{ $regulation_count }}</em>
                                </a>

                                @foreach ($roles as $roleId)

                                    @php
                                        $role = \App\Models\Role::find($roleId);
                                    @endphp

                                    @if ($role)
                                        <a class="dfx-chip">
                                            {{ $role->name }}
                                            <em>{{ $object->regulations->where('role_id', $roleId)->count() }}</em>
                                        </a>
                                    @endif

                                @endforeach

                            </div>

                        @endif

                    </section>

                    {{-- Yozma ko‘rsatmalar --}}
                    <section class="dfx-list"
                             id="kamchiliklar-list"
                             aria-label="Yozma ko‘rsatmalar">

                        @forelse ($object->regulations as $r)

                            <details class="dfx-card">

                                <summary>

                                    <div class="dfx-row1">

                                        <div>
                                            <div class="dfx-no">
                                                № {{ $r->regulation_number }}
                                            </div>

                                            @if ($r->created_at)
                                                <div class="dfx-date">
                                                    Yaratilgan: {{ $r->created_at }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="dfx-badges">

                                            @if (in_array(
                                                $r->regulation_status_id,
                                                [
                                                    \App\Enums\RegulationStatusEnum::ELIMINATED,
                                                    \App\Enums\RegulationStatusEnum::LATE_EXECUTION
                                                ]
                                            ))
                                                <span class="dfx-badge dfx-st-ok">
                                                Bartaraf etilgan
                                            </span>
                                            @else
                                                <span class="dfx-badge dfx-st-warn">
                                                Jarayonda
                                            </span>
                                            @endif

                                        </div>

                                    </div>

                                    {{-- Kimdan / Kimga --}}
                                    <div class="dfx-who">

                                        <span class="k">Kimdan:</span>

                                        <span class="v">
                                        {{ $r->createdByUser?->full_name ?? '—' }}

                                        <small>
                                            {{ $r->createdByRole?->name ?? '—' }}
                                        </small>
                                    </span>

                                        <span class="k">Kimga:</span>

                                        <span class="v">
                                        {{ $r->responsibleUser?->full_name ?? '—' }}

                                        <small>
                                            {{ $r->responsibleRole?->name ?? '—' }}
                                        </small>
                                    </span>

                                    </div>

                                    {{-- Ko‘rsatma ma'lumotlari --}}
                                    <div class="dfx-row3">

                                    <span>
                                        <b>{{ $r->violations->count() }} ta</b>
                                        kamchilik

                                        @if ($r->deadline)
                                            · Yopish muddati:
                                            <b>{{ $r->deadline }}</b>
                                        @endif
                                    </span>

                                        <span class="dfx-chev" aria-hidden="true">
                                        <svg width="22" height="22" viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor"
                                             stroke-width="2.2" stroke-linecap="round"
                                             stroke-linejoin="round">
                                            <path d="M6 9l6 6 6-6"/>
                                        </svg>
                                    </span>

                                    </div>

                                </summary>

                                {{-- Kamchiliklar --}}
                                <div class="dfx-details">

                                    @foreach ($r->violations as $i => $v)

                                        <div class="dfx-item">

                                            <div class="dfx-item-top">
                                                <span class="dfx-n">{{ $i + 1 }}</span>
                                                <div class="dfx-type">{{ $v->title }}</div>
                                            </div>

                                            <div class="dfx-fld">
                                                <div class="l">Aniqlangan kamchiliklar</div>
                                                <div class="t">{{ $v->comment }}</div>
                                            </div>

                                            <div class="dfx-fld">
                                                <div class="l">Berilgan ko‘rsatma</div>
                                                <div class="t">{{ $v->description }}</div>
                                            </div>

                                            <div class="dfx-foot">
                                                <span class="k">Blok nomi:</span>
                                                <span class="v">
                                                {{ $r->monitoring?->block?->name ?? '—' }}
                                            </span>
                                            </div>

                                        </div>

                                    @endforeach

                                </div>

                            </details>

                        @empty

                            <div class="dfx-empty">
                                Ko‘rsatmalar mavjud emas
                            </div>

                        @endforelse

                    </section>

                    <a class="dfx-top" href="#dfx-top">
                        ↑ Tepaga qaytish
                    </a>

                @endif

            </main>
        </div>
    </div>

    <footer>
        <div>© {{ date('Y') }}. Barcha huquqlar himoyalangan.</div>
        <div class="brand">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path
                    d="M3 3h5v5H3zM10 3h4v4h-4zM16 3h5v5h-5zM3 10h4v4H3zM10 10h4v4h-4zM17 10h4v4h-4zM3 16h5v5H3zM10 17h4v4h-4zM16 16h5v5h-5z"
                    opacity=".85"/>
            </svg>
            ShaffofQurilish
        </div>
    </footer>
</div>

<script>
    const OBJECT = {{ \Illuminate\Support\Js::from([
        'id' => $taskId,
        'name' => $object->name,
        'permitDate' => $object->created_at
            ? Carbon::parse($object->created_at)->format('d.m.Y')
            : null,
        'deadline' => $deadline,
        'address' => $objectAddress,
        'city' => $regionName,
        'mapUrl' => $mapUrl,
        'organizations' => [
            [
                'role' => 'Buyurtmachi',
                'name' => $users->get(UserRoleEnum::BUYURTMACHI->value)?->organization_name,
                'persons' => [
                    [
                        'role' => 'Texnik nazoratchi',
                        'fio' => $users->get(UserRoleEnum::TEXNIK->value)?->full_name,
                        'certs' => collect($texnik ?? [])
                                ->map(fn ($item) => [
                                    'dir' => data_get($item, 'spec_name', ''),
                                    'desc' => data_get($item, 'spec_name', ''),
                                    'no' => data_get($item, 'sert.0.sert_number', ''),
                                ])
                                ->values()
                                ->all(),
                    ],
                ],
            ],
            [
                'role' => 'Loyihachi',
                'logo' => $rating_loyiha,
                'name' => $users->get(UserRoleEnum::LOYIHA->value)?->organization_name,
                'persons' => [
                    [
                        'role' => 'Mualliflik nazoratchisi',
                        'fio' => $users->get(UserRoleEnum::MUALLIF->value)?->full_name,
                       'certs' => collect($muallif ?? [])
                                ->map(fn ($item) => [
                                    'dir' => data_get($item, 'spec_name', ''),
                                    'desc' => data_get($item, 'spec_name', ''),
                                    'no' => data_get($item, 'sert.0.sert_number', ''),
                                ])
                                ->values()
                                ->all(),
                    ],
                ],
            ],
            [
                'role' => 'Pudrat tashkiloti',
               'logo' => $rating_qurilish,
                'name' => $users->get(UserRoleEnum::QURILISH->value)?->organization_name,
                'persons' => [
                    [
                        'role' => 'Ichki nazoratchi',
                        'fio' => $users->get(UserRoleEnum::ICHKI->value)?->full_name,
                       'certs' => collect($ichki ?? [])
                            ->map(fn ($item) => [
                                'dir' => data_get($item, 'spec_name', ''),
                                'desc' => data_get($item, 'spec_name', ''),
                                'no' => data_get($item, 'sert.0.sert_number', ''),
                            ])
                            ->values()
                            ->all(),
                    ],
                ],
            ],
        ],
        'documents' => [
            [
                'title' => 'Ekspertiza xulosasining reestr raqami va hujjatlari:',
                'number' => $object->reestr_number,
                'type' => 'expertise',
            ],
            ...(
                $object->object_type_id == 2
                    ? [[
                        'title' => 'Arxitektura-shaharsozlik kengashi xulosasi raqami va hujjatlari:',
                        'number' => $object->number_protocol,
                        'type' => 'apz',
                    ]]
                    : []
            ),
            [
                'title' => 'Obyekt ro‘yxatdan o‘tkazilganligi haqida ko‘chirma:',
                'number' => $taskId,
                'type' => 'object',
            ],
        ],
        'expertiseUrl' => $reestr,
        'apzUrl' => $object->number_protocol
            ? 'https://api.dx.mc.uz/api/module2/v1/tasks/' . $object->number_protocol . '/download-conclusion-file'
            : null,
        'objectPdfUrl' => $taskId
            ? 'https://api-nazorat.mc.uz/api/get-object-pdf/' . $taskId
            : null,
    ]) }};

    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[char]));

    const copyIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>';
    const okIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

    document.getElementById('objId').textContent = OBJECT.id ?? '—';
    document.title = 'Obyekt ID: ' + (OBJECT.id ?? '—') + ' — ShaffofQurilish';

    function field(label, valueHtml) {
        return `<div class="field"><div class="label">${esc(label)}</div>${valueHtml}</div>`;
    }

    function personHtml(person) {
        const certificates = Array.isArray(person.certs) && person.certs.length
            ? person.certs.map(cert => `
                <div class="cert">
                    <div class="cert-info">
                        <span class="cert-no">${esc(cert.no)}</span>
                        <span class="cert-dir" title="${esc(cert.desc || '')}">${esc(cert.dir)}</span>
                    </div>
                    <button class="copy" type="button" data-copy="${esc(cert.no)}" aria-label="${esc(cert.no)} raqamini nusxalash">
                        ${copyIcon}
                    </button>
                </div>
            `).join('')
            : '<div class="cert cert-none"><span class="none-text">Sertifikat mavjud emas</span></div>';

        return `
            <div class="person">
                <div class="person-role">${esc(person.role || '')}</div>
                <div class="person-name">${esc(person.fio || '')}</div>
                ${certificates}
            </div>
        `;
    }

    function organizationHtml(organization) {
        const logos = (Array.isArray(organization.logo)
                ? organization.logo
                : [organization.logo]
        ).filter(value => value !== null && value !== undefined && value !== '');

        const logoHtml = logos.length
            ? `<div class="org-logos">${logos.map(logo => `<div class="logo">${esc(logo)}</div>`).join('')}</div>`
            : '';

        const header = logos.length
            ? `<div class="org">${logoHtml}<div class="value">${esc(organization.name || '—')}</div></div>`
            : `<div class="value">${esc(organization.name || '—')}</div>`;

        const persons = Array.isArray(organization.persons) && organization.persons.length
            ? organization.persons.map(personHtml).join('')
            : '<div class="empty">Mas’ul shaxs va sertifikat ma’lumoti kiritilmagan</div>';

        return field(organization.role + ':', header + persons);
    }

    document.getElementById('content').innerHTML =
        field('Obyekt nomi:', `<div class="value">${esc(OBJECT.name || '—')}</div>`) +
        field('Qurilishga ruxsat berilgan sana:', `<div class="value">${esc(OBJECT.permitDate || '—')}</div>`) +
        OBJECT.organizations.map(organizationHtml).join('') +
        field('Manzili:', `
            <div class="addr">
                <div class="value">${esc(OBJECT.address || '—')}</div>
                <a class="pin" href="${esc(OBJECT.mapUrl)}" target="_blank" rel="noopener noreferrer" aria-label="Xaritada ochish">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>
                    </svg>
                </a>
            </div>
        `);

    document.getElementById('docs').innerHTML = OBJECT.documents.map(document => `
        <div class="doc">
            <div class="doc-name">${esc(document.title)}</div>
            <div class="doc-no">${esc(document.number || '—')}</div>
            <button class="btn" type="button" data-download="${esc(document.type)}">Ko‘chirib olish</button>
        </div>
    `).join('');

    document.addEventListener('click', async event => {
        const button = event.target.closest('.copy');
        if (!button) return;

        const text = button.dataset.copy;

        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
            } finally {
                textarea.remove();
            }
        }

        const previousHtml = button.innerHTML;
        button.innerHTML = okIcon;
        button.classList.add('ok');

        setTimeout(() => {
            button.innerHTML = previousHtml;
            button.classList.remove('ok');
        }, 1200);
    });

    document.getElementById('shareBtn').addEventListener('click', async () => {
        const data = {
            title: 'Obyekt ID: ' + (OBJECT.id ?? ''),
            text: OBJECT.name ?? '',
            url: location.href
        };

        if (navigator.share) {
            try {
                await navigator.share(data);
            } catch (error) {
                if (error.name !== 'AbortError') console.error(error);
            }
            return;
        }

        try {
            await navigator.clipboard.writeText(location.href);
            alert('Havola nusxalandi.');
        } catch {
            prompt('Havolani nusxalash uchun:', location.href);
        }
    });

    async function downloadFile(url, filename) {
        if (!url) {
            alert('Hujjat manzili mavjud emas.');
            return;
        }

        const response = await fetch(url);

        if (!response.ok) {
            throw new Error('Hujjatni yuklab olishda xatolik: ' + response.status);
        }

        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            const data = await response.json();
            const base64 = data?.result?.data?.file
                ?? data?.data?.result?.data?.file
                ?? data?.data?.file
                ?? data?.file;

            if (!base64) {
                throw new Error('API javobidan fayl topilmadi.');
            }

            const cleanBase64 = base64.includes(',') ? base64.split(',').pop() : base64;
            const binary = atob(cleanBase64);
            const bytes = new Uint8Array(binary.length);

            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }

            saveBlob(new Blob([bytes], { type: 'application/pdf' }), filename);
            return;
        }

        saveBlob(await response.blob(), filename);
    }

    function saveBlob(blob, filename) {
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    }

    document.getElementById('docs').addEventListener('click', async event => {
        const button = event.target.closest('[data-download]');
        if (!button) return;

        const type = button.dataset.download;
        const originalText = button.textContent;
        let url = null;
        let filename = 'hujjat.pdf';

        if (type === 'expertise') {
            url = OBJECT.expertiseUrl;
            filename = 'ekspertiza-xulosasi.pdf';
        } else if (type === 'apz') {
            url = OBJECT.apzUrl;
            filename = 'arxitektura-shaharsozlik-xulosasi.pdf';
        } else if (type === 'object') {
            url = OBJECT.objectPdfUrl;
            filename = 'obyekt-kuchirma.pdf';
        }

        button.disabled = true;
        button.textContent = 'Yuklanmoqda...';

        try {
            await downloadFile(url, filename);
        } catch (error) {
            console.error(error);
            alert('Hujjatni yuklab olib bo‘lmadi. API manzili va CORS sozlamalarini tekshiring.');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });

    function tick() {
        const timer = document.getElementById('timer');
        const daysEl = document.getElementById('tD');
        const hoursEl = document.getElementById('tH');
        const minutesEl = document.getElementById('tM');

        const end = OBJECT.deadline
            ? new Date(OBJECT.deadline).getTime()
            : NaN;

        if (Number.isNaN(end)) {
            daysEl.textContent = '—';
            hoursEl.textContent = '—';
            minutesEl.textContent = '—';
            timer.classList.add('done');
            return;
        }

        const distance = end - Date.now();
        let remaining = Math.abs(distance);

        let days = Math.floor(remaining / 86400000);
        remaining %= 86400000;

        let hours = Math.floor(remaining / 3600000);
        remaining %= 3600000;

        let minutes = Math.floor(remaining / 60000);

        if (distance < 0) {
            if (days > 0) {
                days = -days;
            } else if (hours > 0) {
                hours = -hours;
            } else if (minutes > 0) {
                minutes = -minutes;
            }
        }

        daysEl.textContent = days;
        hoursEl.textContent = hours;
        minutesEl.textContent = minutes;

        timer.classList.toggle('done', distance <= 0);
    }

    tick();
    setInterval(tick, 30000);

    /* ============================================================
       KO'RINISHLAR ORASIDA O'TISH (#kamchiliklar yoki bosh sahifa)
       #kamchiliklar-list va #dfx-top — kamchiliklar bo'limi ichidagi havolalar
       ============================================================ */
    function route(){
        const showDefects = /^#(kamchiliklar|dfx-)/.test(location.hash);
        document.getElementById('viewObject').hidden = showDefects;
        document.getElementById('viewDefects').hidden = !showDefects;
        document.title = showDefects
            ? 'Kamchiliklar — Obyekt ID: ' + OBJECT.id + ' — ShaffofQurilish'
            : 'Obyekt ID: ' + OBJECT.id + ' — ShaffofQurilish';
        // "Yana ko'rsatish" va "Tepaga" havolalarida sahifa tepasiga sakramaymiz
        if (!/^#(kamchiliklar-|dfx-)/.test(location.hash)) scrollTo(0, 0);
    }
    addEventListener('hashchange', route);
    route();
</script>
</body>
</html>
