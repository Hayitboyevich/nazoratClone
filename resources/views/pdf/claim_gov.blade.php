@php
    use Carbon\Carbon;
    $customerConfirmed = Carbon::parse($declaration->customer_confirmed_at);
    $deadline          = Carbon::parse($declaration->object->deadline);
    $objectCreated     = Carbon::parse($declaration->object->created_at);
@endphp
    <!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, charset=utf-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Dalolatnoma</title>
    <style>

        * {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 13px;
            color: #080B22;
            line-height: 16px;
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        .page {
            height: 297mm;
        }

        .first-page-content {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 250mm;
        }

        .page-break-after {
            page-break-after: always;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #080B22;
        }

        .w-full {
            width: 100%;
        }

        .table {
            width: 100%;
        }

        .table th,
        .table td {
            padding: 3px;
        }

        .table.table-bordered {
            border-collapse: collapse;
        }

        .table.table-bordered,
        .table.table-bordered th,
        .table.table-bordered td {
            border: 1px solid #222;
        }

        .table.table-borderless,
        .table.table-borderless th,
        .table.table-borderless td {
            border: none;
        }

        .table {
            width: 100%;
        }

        .font-500 {
            font-weight: 500;
        }

        .font-700 {
            font-weight: 700;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .text-lg {
            font-size: 16px !important;
            line-height: 18px;
        }

        .text-xl {
            font-size: 18px !important;
            line-height: 18px;
        }

        .text-2xl {
            font-size: 24px !important;
            line-height: 24px;
        }

        .flex {
            display: flex;
        }

        .flex-col {
            flex-direction: column;
        }

        .items-center {
            align-items: center;
        }

        .justify-between {
            justify-content: space-between;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .indent-sm {
            text-indent: 16px;
        }

        .indent-lg {
            text-indent: 20px;
        }

        .indent-xl {
            text-indent: 24px;
        }

        .justify {
            text-align: justify;
        }

        .imzo-item {
            display: inline-block;
            border: 1px solid #D9D9D9;
            padding: 3px;
            border-radius: 2px;
        }

        .imzo-inside {
            display: block;
            line-height: 10px;
            padding: 1px 3px;
            border-radius: 2px;
            color: #099122;
            background-color: #B2FABD;
            font-size: 8px;
        }

        .imzo-inside img {
            display: inline-block;
            margin-right: 2px;
        }

        .imzo-date {
            display: block;
            font-size: 8px;
            line-height: 10px;
            color: #CCCCCC;
        }
    </style>
</head>

<body>
<div style="padding: 10px; border: 1px solid #687196;">
    <table class="table">
        <tbody>
        <tr>
            <td class="text-center" style="width: 60%;">
                <img src="{{ public_path('images/qr/gasn.png') }}" style="width: 200px" alt="">
            </td>
            <td style="width: 40%;">
                <img
                    src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYzIiBoZWlnaHQ9IjIzIiB2aWV3Qm94PSIwIDAgMTYzIDIzIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMzUuNjMzMSAxNi4yNzM1QzM2LjM0ODQgMTYuODY5NSAzNy4yMjI3IDE3LjE0NzcgMzguMzM1NCAxNy4xNDc3QzM5LjMyODkgMTcuMTQ3NyA0MC4wODM5IDE2Ljk0OSA0MC43MTk3IDE2LjUxMTlDNDEuMzE1OCAxNi4wNzQ4IDQxLjYzMzcgMTUuNDc4NyA0MS42MzM3IDE0LjcyMzZDNDEuNjMzNyAxNC4xNjczIDQxLjQ3NDggMTMuNzMwMSA0MS4xMTcxIDEzLjQxMjJDNDAuNzk5MiAxMy4wOTQzIDQwLjM2MjEgMTIuODU1OSAzOS44NDU1IDEyLjY5NjlDMzkuMzI4OSAxMi41MzggMzguNjEzNiAxMi4zNzkgMzcuNjk5NSAxMi4yMkMzNi4yNjg5IDExLjk4MTYgMzUuMTU2MiAxMS41ODQyIDM0LjI4MiAxMS4wMjc5QzMzLjQ0NzQgMTAuNDcxNSAzMy4wMTAzIDkuNTU3NSAzMy4wMTAzIDguMzI1NThDMzMuMDEwMyA3LjUzMDc5IDMzLjIwOSA2LjgxNTQ4IDMzLjYwNjQgNi4xNzk2NUMzNC4wMDM4IDUuNTQzODIgMzQuNTk5OSA1LjA2Njk1IDM1LjMxNTIgNC43NDkwM0MzNi4wMzA1IDQuNDMxMTIgMzYuOTA0OCA0LjIzMjQyIDM3Ljg1ODUgNC4yMzI0MkMzOC44NTIgNC4yMzI0MiAzOS43NjYgNC40MzExMiA0MC41MjEgNC43ODg3N0M0MS4yNzYxIDUuMTQ2NDMgNDEuOTExOSA1LjY2MzA0IDQyLjM0OTEgNi4zMzg2MUM0Mi43ODYyIDcuMDE0MTggNDMuMDY0NCA3Ljc2OTIzIDQzLjEwNDEgOC42MDM3NUg0MS4zNTU2QzQxLjIzNjMgNy43NjkyMyA0MC44Nzg3IDcuMDkzNjYgNDAuMjQyOSA2LjYxNjc5QzM5LjYwNyA2LjEwMDE3IDM4LjgxMjIgNS44NjE3NCAzNy44NTg1IDUuODYxNzRDMzYuOTA0OCA1Ljg2MTc0IDM2LjE0OTcgNi4wNjA0MyAzNS41OTM0IDYuNDk3NTdDMzUuMDM3IDYuODk0OTYgMzQuNzU4OCA3LjQ5MTA1IDM0Ljc1ODggOC4yNDYxQzM0Ljc1ODggOC44MDI0NSAzNC45MTc4IDkuMTk5ODUgMzUuMjM1NyA5LjUxNzc2QzM1LjU1MzYgOS44MzU2OCAzNS45OTA4IDEwLjAzNDQgMzYuNTA3NCAxMC4xOTMzQzM3LjAyNCAxMC4zNTIzIDM3LjczOTMgMTAuNTExMiAzOC42MTM1IDEwLjY3MDJDNDAuMDQ0MiAxMC45MDg2IDQxLjE5NjYgMTEuMzA2IDQyLjAzMTEgMTEuODYyNEM0Mi45MDU0IDEyLjQxODcgNDMuMzAyOCAxMy4zMzI3IDQzLjMwMjggMTQuNTY0N0M0My4zMDI4IDE1LjM1OTQgNDMuMTA0MSAxNi4xMTQ1IDQyLjY2NyAxNi43NTAzQzQyLjIyOTggMTcuMzg2MiA0MS42MzM3IDE3LjkwMjggNDAuODc4NyAxOC4yMjA3QzQwLjEyMzYgMTguNTc4MyAzOS4yNDk0IDE4LjczNzMgMzguMjU1OSAxOC43MzczQzM3LjE0MzIgMTguNzM3MyAzNi4xODk0IDE4LjUzODYgMzUuMzU0OSAxOC4xNDEyQzM0LjUyMDQgMTcuNzQzOCAzMy44ODQ2IDE3LjE4NzUgMzMuNDQ3NCAxNi40MzI0QzMzLjAxMDMgMTUuNzE3MSAzMi43MzIxIDE0Ljg4MjYgMzIuNjkyNCAxMy45Mjg4SDM0LjQ0MDlDMzQuNTIwNCAxNC45MjIzIDM0LjkxNzggMTUuNjc3NCAzNS42MzMxIDE2LjI3MzVaIiBmaWxsPSIjMDQwNTA1Ii8+CjxwYXRoIGQ9Ik01NC42NjgyIDEyLjgxNjFWMTguNjE4SDUyLjk5OTFWMTIuOTM1M0M1Mi45OTkxIDExLjg2MjMgNTIuNzYwNyAxMS4wMjc4IDUyLjI4MzggMTAuNDcxNEM1MS44MDY5IDkuOTE1MDggNTEuMDkxNiA5LjYzNjkgNTAuMTc3NiA5LjYzNjlDNDkuMjIzOSA5LjYzNjkgNDguNDY4OCA5Ljk5NDU2IDQ3Ljg3MjcgMTAuNjcwMUM0Ny4zMTY0IDExLjM0NTcgNDcuMDM4MiAxMi4yNTk3IDQ3LjAzODIgMTMuNDUxOVYxOC42NTc3SDQ1LjM2OTFWNC4zNTE1Nkg0Ny4wMzgyVjkuOTE1MDhDNDcuMzk1OSA5LjM1ODczIDQ3LjgzMyA4LjkyMTU5IDQ4LjM4OTMgOC42MDM2OEM0OC45NDU3IDguMjQ2MDIgNDkuNjYxIDguMDg3MDcgNTAuNDU1OCA4LjA4NzA3QzUxLjcyNzQgOC4wODcwNyA1Mi43MjA5IDguNDg0NDYgNTMuNTE1NyA5LjIzOTUxQzU0LjI3MDggOS45OTQ1NiA1NC42NjgyIDExLjE4NjcgNTQuNjY4MiAxMi44MTYxWiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNNjYuNTEwOCAxOC42MTk0SDY1LjU5NjhDNjQuOTIxMiAxOC42MTk0IDY0LjQ0NDMgMTguNTAwMiA2NC4xNjYyIDE4LjIyMkM2My44ODggMTcuOTQzOSA2My43MjkgMTcuNTQ2NSA2My43MjkgMTcuMDI5OUM2Mi45MzQyIDE4LjE4MjMgNjEuNzgxOCAxOC43Nzg0IDYwLjIzMiAxOC43Nzg0QzU5LjA3OTUgMTguNzc4NCA1OC4xNjU1IDE4LjUwMDIgNTcuNDUwMiAxNy45ODM2QzU2LjczNDkgMTcuNDY3IDU2LjQxNyAxNi43MTE5IDU2LjQxNyAxNS43NTgyQzU2LjQxNyAxNC43MjUgNTYuNzc0NiAxMy45MzAyIDU3LjQ5IDEzLjM3MzhDNTguMjA1MyAxMi44MTc1IDU5LjIzODUgMTIuNTM5MyA2MC41ODk2IDEyLjUzOTNINjMuNjQ5NlYxMS44MjRDNjMuNjQ5NiAxMS4xNDg0IDYzLjQxMTEgMTAuNjMxOCA2Mi45NzQgMTAuMjM0NEM2Mi41MzY5IDkuODM3MDIgNjEuOTAxIDkuNjc4MDYgNjEuMDY2NSA5LjY3ODA2QzYwLjM1MTIgOS42NzgwNiA1OS43NTUxIDkuODM3MDIgNTkuMjc4MiAxMC4xNTQ5QzU4LjgwMTQgMTAuNDcyOSA1OC41MjMyIDEwLjkxIDU4LjQwNCAxMS40MjY2SDU2LjczNDlDNTYuODU0MSAxMC4zOTM0IDU3LjI5MTMgOS41OTg1OSA1OC4wODYgOS4wNDIyNEM1OC44ODA4IDguNDg1ODggNTkuODc0MyA4LjE2Nzk3IDYxLjE0NiA4LjE2Nzk3QzYyLjQ1NzQgOC4xNjc5NyA2My40OTA2IDguNDg1ODggNjQuMjQ1NyA5LjEyMTcxQzY1LjAwMDcgOS43NTc1NCA2NS4zMTg2IDEwLjcxMTMgNjUuMzE4NiAxMS45MDM1VjE2LjM5NEM2NS4zMTg2IDE2LjkxMDYgNjUuNTU3MSAxNy4xNDkxIDY1Ljk5NDIgMTcuMTQ5MUg2Ni41MTA4VjE4LjYxOTRaTTYwLjQzMDcgMTMuODkwNEM1OC44ODA4IDEzLjg5MDQgNTguMTI1OCAxNC40ODY1IDU4LjEyNTggMTUuNjM5QzU4LjEyNTggMTYuMTU1NiA1OC4zMjQ1IDE2LjU1MyA1OC43MjE5IDE2Ljg3MDlDNTkuMTE5MyAxNy4xODg4IDU5LjY3NTYgMTcuMzQ3OCA2MC4zOTA5IDE3LjM0NzhDNjEuNDI0MiAxNy4zNDc4IDYyLjIxODkgMTcuMDY5NiA2Mi43NzUzIDE2LjU1M0M2My4zNzE0IDE2LjAzNjQgNjMuNjQ5NiAxNS4zMjExIDYzLjY0OTYgMTQuNDA3MVYxMy44OTA0SDYwLjQzMDdaIiBmaWxsPSIjMDQwNTA1Ii8+CjxwYXRoIGQ9Ik02OC40OTczIDcuMDUzODRDNjguNDk3MyA2LjEzOTg0IDY4LjY5NiA1LjQ2NDI3IDY5LjEzMzIgNS4wMjcxM0M2OS41NzAzIDQuNTkgNzAuMjQ1OSA0LjM1MTU2IDcxLjE5OTYgNC4zNTE1Nkg3My4xMDcxVjUuODYxNjZINzEuMjc5MUM3MC44ODE3IDUuODYxNjYgNzAuNTYzOCA1Ljk0MTE0IDcwLjQwNDggNi4xMzk4NEM3MC4yNDU5IDYuMjk4NzkgNzAuMTI2NiA2LjYxNjcxIDcwLjEyNjYgNy4wMTQxVjguMjA2MjhINzMuMDY3NFY5LjcxNjM4SDcwLjEyNjZWMTguNTc4M0g2OC40NTc2VjkuNzU2MTJINjYuNTg5OFY4LjI0NjAySDY4LjQ1NzZWNy4wNTM4NEg2OC40OTczWiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNNzYuOTIyIDQuMzUxNTZINzguODI5NVY1Ljg2MTY2SDc3LjAwMTVDNzYuNjA0MSA1Ljg2MTY2IDc2LjI4NjIgNS45NDExNCA3Ni4xMjcyIDYuMTM5ODRDNzUuOTY4MyA2LjI5ODc5IDc1Ljg0OSA2LjYxNjcxIDc1Ljg0OSA3LjAxNDFWOC4yMDYyOEg3OC43ODk4VjkuNzE2MzhINzUuODg4OFYxOC41NzgzSDc0LjIxOTdWNy4wNTM4NEM3NC4yMTk3IDYuMTM5ODQgNzQuNDE4NCA1LjQ2NDI3IDc0Ljg1NTYgNS4wMjcxM0M3NS4yOTI3IDQuNTkgNzUuOTY4MyA0LjM1MTU2IDc2LjkyMiA0LjM1MTU2WiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNNzkuNzQ0MiAxNi4xOTI4Qzc5LjMwNzEgMTUuMzk4IDc5LjEwODQgMTQuNDg0IDc5LjEwODQgMTMuNDExQzc5LjEwODQgMTIuMzc3OCA3OS4zMDcxIDExLjQyNCA3OS43NDQyIDEwLjYyOTNDODAuMTgxNCA5LjgzNDQ3IDgwLjc3NzUgOS4xOTg2NCA4MS41NzIyIDguNzYxNTFDODIuMzY3IDguMzI0MzcgODMuMjQxMyA4LjA4NTk0IDg0LjIzNDggOC4wODU5NEM4NS4yMjgzIDguMDg1OTQgODYuMTQyMyA4LjMyNDM3IDg2Ljg5NzMgOC43NjE1MUM4Ny42OTIxIDkuMTk4NjQgODguMjg4MiA5LjgzNDQ3IDg4LjcyNTMgMTAuNjI5M0M4OS4xNjI1IDExLjQyNCA4OS4zNjEyIDEyLjMzODEgODkuMzYxMiAxMy40MTFDODkuMzYxMiAxNC40NDQyIDg5LjE2MjUgMTUuMzk4IDg4LjcyNTMgMTYuMTkyOEM4OC4yODgyIDE2Ljk4NzYgODcuNjkyMSAxNy42MjM0IDg2Ljg5NzMgMTguMDYwNUM4Ni4xMDI1IDE4LjQ5NzcgODUuMjI4MyAxOC43MzYxIDg0LjIzNDggMTguNzM2MUM4My4yNDEzIDE4LjczNjEgODIuMzI3MyAxOC40OTc3IDgxLjU3MjIgMTguMDYwNUM4MC43Nzc1IDE3LjYyMzQgODAuMTgxNCAxNi45ODc2IDc5Ljc0NDIgMTYuMTkyOFpNODcuMjU1IDE1LjM5OEM4Ny41MzMxIDE0LjgwMTkgODcuNjkyMSAxNC4xNjYxIDg3LjY5MjEgMTMuNDExQzg3LjY5MjEgMTIuNjk1NyA4Ny41MzMxIDEyLjAyMDEgODcuMjU1IDExLjQyNEM4Ni45NzY4IDEwLjgyOCA4Ni41Nzk0IDEwLjM5MDggODYuMDYyOCAxMC4wNzI5Qzg1LjU0NjIgOS43NTQ5OSA4NC45NTAxIDkuNTk2MDMgODQuMjc0NSA5LjU5NjAzQzgzLjU5ODkgOS41OTYwMyA4My4wMDI5IDkuNzU0OTkgODIuNDg2MiAxMC4wNzI5QzgxLjk2OTYgMTAuMzkwOCA4MS41NzIyIDEwLjg2NzcgODEuMjk0MSAxMS40MjRDODEuMDE1OSAxMS45ODA0IDgwLjg1NjkgMTIuNjU2IDgwLjg1NjkgMTMuNDExQzgwLjg1NjkgMTQuMTI2MyA4MS4wMTU5IDE0LjgwMTkgODEuMjk0MSAxNS4zOThDODEuNTcyMiAxNS45OTQxIDgxLjk2OTYgMTYuNDMxMiA4Mi40ODYyIDE2Ljc0OTFDODMuMDAyOSAxNy4wNjcgODMuNTk4OSAxNy4yMjYgODQuMjc0NSAxNy4yMjZDODQuOTUwMSAxNy4yMjYgODUuNTQ2MiAxNy4wNjcgODYuMDYyOCAxNi43NDkxQzg2LjU3OTQgMTYuNDMxMiA4Ni45NzY4IDE1Ljk5NDEgODcuMjU1IDE1LjM5OFoiIGZpbGw9IiMwNDA1MDUiLz4KPHBhdGggZD0iTTkxLjg2NDUgNy4wNTM4NEM5MS44NjQ1IDYuMTM5ODQgOTIuMDYzMiA1LjQ2NDI3IDkyLjUwMDQgNS4wMjcxM0M5Mi45Mzc1IDQuNTkgOTMuNjEzMSA0LjM1MTU2IDk0LjU2NjggNC4zNTE1Nkg5Ni40NzQzVjUuODYxNjZIOTQuNjQ2M0M5NC4yNDg5IDUuODYxNjYgOTMuOTMxIDUuOTQxMTQgOTMuNzcyIDYuMTM5ODRDOTMuNjEzMSA2LjI5ODc5IDkzLjQ5MzggNi42MTY3MSA5My40OTM4IDcuMDE0MVY4LjIwNjI4SDk2LjQzNDZWOS43MTYzOEg5My40OTM4VjE4LjU3ODNIOTEuODI0OFY5Ljc1NjEySDg5Ljk1N1Y4LjI0NjAySDkxLjgyNDhWNy4wNTM4NEg5MS44NjQ1WiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNMTExLjgxMyAxOC42MTg4SDExMC41MDJDMTA5Ljk4NSAxOC42MTg4IDEwOS41NDggMTguNTM5MyAxMDkuMjMgMTguMzgwM0MxMDguOTEyIDE4LjIyMTQgMTA4LjU5NSAxNy45ODI5IDEwOC4zNTYgMTcuNjI1M0wxMDguMTk3IDE3LjQyNjZDMTA3LjA0NSAxOC4zMDA4IDEwNS42OTQgMTguNzM4IDEwNC4xNDQgMTguNzM4QzEwMi44MzIgMTguNzM4IDEwMS42NCAxOC40MjAxIDEwMC42MDcgMTcuNzg0MkM5OS41NzM3IDE3LjE0ODQgOTguNzc5IDE2LjMxMzkgOTguMTgyOSAxNS4yMDEyQzk3LjU4NjggMTQuMDg4NSA5Ny4zMDg2IDEyLjg1NjUgOTcuMzA4NiAxMS40NjU3Qzk3LjMwODYgMTAuMDc0OCA5Ny41ODY4IDguODQyODcgOTguMTgyOSA3LjczMDE2Qzk4Ljc3OSA2LjYxNzQ2IDk5LjU3MzcgNS43ODI5MyAxMDAuNjA3IDUuMTQ3MUMxMDEuNjQgNC41MTEyNyAxMDIuODMyIDQuMTkzMzYgMTA0LjE0NCA0LjE5MzM2QzEwNS40NTUgNC4xOTMzNiAxMDYuNjQ3IDQuNTExMjcgMTA3LjY4MSA1LjE0NzFDMTA4LjcxNCA1Ljc4MjkzIDEwOS41MDkgNi42MTc0NiAxMTAuMTA1IDcuNzMwMTZDMTEwLjcwMSA4Ljg0Mjg3IDExMC45NzkgMTAuMDc0OCAxMTAuOTc5IDExLjQ2NTdDMTEwLjk3OSAxMi40MTk0IDExMC44NiAxMy4yOTM3IDExMC41ODIgMTQuMDg4NUMxMTAuMzAzIDE0Ljg4MzMgMTA5LjkwNiAxNS42MzgzIDEwOS40MjkgMTYuMjc0MUMxMDkuNjI4IDE2LjUxMjYgMTA5LjgyNyAxNi43MTEzIDExMC4wMjUgMTYuODMwNUMxMTAuMjI0IDE2Ljk0OTcgMTEwLjQ2MiAxNi45ODk0IDExMC43OCAxNi45ODk0SDExMS44OTNWMTguNjE4OEgxMTEuODEzWk0xMDYuNzY3IDE1LjUxOTFDMTA2LjU2OCAxNS4yODA2IDEwNi4zNjkgMTUuMDgxOSAxMDYuMjEgMTQuOTYyN0MxMDYuMDUxIDE0Ljg0MzUgMTA1LjgxMyAxNC44MDM4IDEwNS41MzUgMTQuODAzOEgxMDQuMzQyVjEzLjE3NDVIMTA1LjgxM0MxMDYuMjkgMTMuMTc0NSAxMDYuNjQ3IDEzLjI1MzkgMTA2Ljk2NSAxMy40MTI5QzEwNy4yODMgMTMuNTcxOSAxMDcuNTYxIDEzLjgxMDMgMTA3LjggMTQuMTY3OUwxMDguMjM3IDE0Ljc2NEMxMDguNzkzIDEzLjg1IDEwOS4xMTEgMTIuNzM3MyAxMDkuMTExIDExLjQ2NTdDMTA5LjExMSAxMC4zOTI3IDEwOC45MTIgOS4zOTkyMiAxMDguNDc1IDguNTY0NjlDMTA4LjAzOCA3LjczMDE2IDEwNy40ODIgNy4wNTQ1OSAxMDYuNzI3IDYuNTc3NzJDMTA1Ljk3MiA2LjEwMDg1IDEwNS4wOTggNS44NjI0MSAxMDQuMTQ0IDUuODYyNDFDMTAzLjE1IDUuODYyNDEgMTAyLjMxNiA2LjEwMDg1IDEwMS41NjEgNi41Nzc3MkMxMDAuODA2IDcuMDU0NTkgMTAwLjIxIDcuNzMwMTYgOTkuODEyMiA4LjU2NDY5Qzk5LjM3NSA5LjM5OTIyIDk5LjE3NjMgMTAuMzkyNyA5OS4xNzYzIDExLjQ2NTdDOTkuMTc2MyAxMi41Mzg2IDk5LjM3NSAxMy41MzIxIDk5LjgxMjIgMTQuMzY2NkMxMDAuMjQ5IDE1LjIwMTIgMTAwLjgwNiAxNS44NzY3IDEwMS41NjEgMTYuMzUzNkMxMDIuMzE2IDE2LjgzMDUgMTAzLjE5IDE3LjA2ODkgMTA0LjE0NCAxNy4wNjg5QzEwNS4zMzYgMTcuMDY4OSAxMDYuMzI5IDE2LjcxMTMgMTA3LjIwNCAxNS45OTZMMTA2Ljc2NyAxNS41MTkxWiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNMTIxLjk0NyAxOC42MkgxMjAuNTE2TDEyMC4yNzggMTcuMjI5MUMxMTkuNDgzIDE4LjIyMjYgMTE4LjM3IDE4LjczOTIgMTE2Ljk0IDE4LjczOTJDMTE1LjcwOCAxOC43MzkyIDExNC43MTQgMTguMzQxOSAxMTMuOTU5IDE3LjU4NjhDMTEzLjIwNCAxNi44MzE4IDExMi44NDcgMTUuNjM5NiAxMTIuODQ3IDE0LjAxMDNWOC4yNDgwNUgxMTQuNTE2VjEzLjkzMDhDMTE0LjUxNiAxNS4wMDM3IDExNC43NTQgMTUuODM4MyAxMTUuMjMxIDE2LjM5NDZDMTE1LjcwOCAxNi45NTEgMTE2LjM4MyAxNy4yMjkxIDExNy4yMTggMTcuMjI5MUMxMTguMTcyIDE3LjIyOTEgMTE4LjkyNyAxNi44NzE1IDExOS40NDMgMTYuMTk1OUMxMTkuOTYgMTUuNTIwNCAxMjAuMjM4IDE0LjYwNjMgMTIwLjIzOCAxMy40MTQyVjguMjQ4MDVIMTIxLjkwN1YxOC42MkgxMjEuOTQ3WiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNMTI5LjQ5NyA5Ljg3NjA4SDEyOC42NjNDMTI3LjY2OSA5Ljg3NjA4IDEyNi45NTQgMTAuMTk0IDEyNi41MTcgMTAuODY5NkMxMjYuMDggMTEuNTQ1MSAxMjUuODgxIDEyLjMzOTkgMTI1Ljg4MSAxMy4zMzM0VjE4LjYxODdIMTI0LjIxMlY4LjI0Njc3SDEyNS42NDNMMTI1Ljg4MSA5Ljc5NjYxQzEyNi4xOTkgOS4zMTk3MyAxMjYuNTU3IDguOTIyMzQgMTI3LjAzMyA4LjY0NDE2QzEyNy41MSA4LjM2NTk5IDEyOC4xODYgOC4yMDcwMyAxMjkuMDIgOC4yMDcwM0gxMjkuNDk3VjkuODc2MDhaIiBmaWxsPSIjMDQwNTA1Ii8+CjxwYXRoIGQ9Ik0xMzIuOTE1IDUuMzA1MzlDMTMyLjkxNSA1LjYyMzMgMTMyLjc5NiA1Ljg2MTc0IDEzMi41OTggNi4wNjA0M0MxMzIuMzk5IDYuMjU5MTMgMTMyLjEyMSA2LjM3ODM1IDEzMS44NDIgNi4zNzgzNUMxMzEuNTY0IDYuMzc4MzUgMTMxLjI4NiA2LjI1OTEzIDEzMS4wODcgNi4wNjA0M0MxMzAuODg5IDUuODYxNzQgMTMwLjc3IDUuNTgzNTYgMTMwLjc3IDUuMzA1MzlDMTMwLjc3IDUuMDI3MjEgMTMwLjg4OSA0Ljc0OTAzIDEzMS4wODcgNC41NTAzNEMxMzEuMjg2IDQuMzUxNjQgMTMxLjU2NCA0LjIzMjQyIDEzMS44NDIgNC4yMzI0MkMxMzIuMTIxIDQuMjMyNDIgMTMyLjM5OSA0LjM1MTY0IDEzMi41OTggNC41NTAzNEMxMzIuODM2IDQuNzQ5MDMgMTMyLjkxNSA0Ljk4NzQ3IDEzMi45MTUgNS4zMDUzOVpNMTMyLjY3NyA4LjI0NjFWMTguNjE4MUgxMzEuMDA4VjguMjQ2MUgxMzIuNjc3WiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNMTM2LjY1IDQuMzUxNTZWMTguNjE4SDEzNC45ODFWNC4zNTE1NkgxMzYuNjVaIiBmaWxsPSIjMDQwNTA1Ii8+CjxwYXRoIGQ9Ik0xNDAuODI0IDUuMzA1MzlDMTQwLjgyNCA1LjYyMzMgMTQwLjcwNCA1Ljg2MTc0IDE0MC41MDYgNi4wNjA0M0MxNDAuMzA3IDYuMjU5MTMgMTQwLjAyOSA2LjM3ODM1IDEzOS43NTEgNi4zNzgzNUMxMzkuNDczIDYuMzc4MzUgMTM5LjE5NCA2LjI1OTEzIDEzOC45OTYgNi4wNjA0M0MxMzguNzk3IDUuODYxNzQgMTM4LjY3OCA1LjU4MzU2IDEzOC42NzggNS4zMDUzOUMxMzguNjc4IDUuMDI3MjEgMTM4Ljc5NyA0Ljc0OTAzIDEzOC45OTYgNC41NTAzNEMxMzkuMTk0IDQuMzUxNjQgMTM5LjQ3MyA0LjIzMjQyIDEzOS43NTEgNC4yMzI0MkMxNDAuMDI5IDQuMjMyNDIgMTQwLjMwNyA0LjM1MTY0IDE0MC41MDYgNC41NTAzNEMxNDAuNzQ0IDQuNzQ5MDMgMTQwLjgyNCA0Ljk4NzQ3IDE0MC44MjQgNS4zMDUzOVpNMTQwLjU4NSA4LjI0NjFWMTguNjE4MUgxMzguOTE2VjguMjQ2MUgxNDAuNTg1WiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNMTQ0Ljk5NSAxNi43MDk0QzE0NS41MTIgMTcuMTA2OCAxNDYuMTg3IDE3LjMwNTUgMTQ3LjAyMiAxNy4zMDU1QzE0Ny43MzcgMTcuMzA1NSAxNDguMzMzIDE3LjE0NjUgMTQ4LjgxIDE2Ljg2ODNDMTQ5LjI0NyAxNi41OTAyIDE0OS40ODYgMTYuMTkyOCAxNDkuNDg2IDE1LjcxNTlDMTQ5LjQ4NiAxNS4zMTg1IDE0OS4zNjYgMTUuMDAwNiAxNDkuMTY4IDE0Ljc2MjJDMTQ4Ljk2OSAxNC41NjM1IDE0OC42NTEgMTQuNDA0NSAxNDguMzMzIDE0LjMyNUMxNDcuOTc1IDE0LjI0NTUgMTQ3LjQ5OSAxNC4xNjYxIDE0Ni44NjMgMTQuMDg2NkMxNDUuOTQ5IDEzLjk2NzQgMTQ1LjIzMyAxMy44NDgxIDE0NC42NzcgMTMuNjQ5NUMxNDQuMTIxIDEzLjQ1MDggMTQzLjY0NCAxMy4xNzI2IDE0My4yODYgMTIuODE0OUMxNDIuOTI5IDEyLjQxNzUgMTQyLjc3IDExLjkwMDkgMTQyLjc3IDExLjIyNTNDMTQyLjc3IDEwLjYyOTMgMTQyLjkyOSAxMC4wNzI5IDE0My4yODYgOS41OTYwM0MxNDMuNjQ0IDkuMTE5MTYgMTQ0LjEyMSA4Ljc2MTUxIDE0NC43MTcgOC40ODMzM0MxNDUuMzEzIDguMjA1MTYgMTQ1Ljk4OCA4LjA4NTk0IDE0Ni43NDQgOC4wODU5NEMxNDcuOTc1IDguMDg1OTQgMTQ4Ljk2OSA4LjM2NDExIDE0OS43MjQgOC45NjAyQzE1MC40NzkgOS41MTY1NiAxNTAuOTE2IDEwLjM1MTEgMTUwLjk5NiAxMS4zODQzSDE0OS4zMjdDMTQ5LjI0NyAxMC44MjggMTQ5LjAwOSAxMC4zOTA4IDE0OC41MzIgMTAuMDMzMkMxNDguMDU1IDkuNjc1NTEgMTQ3LjQ5OSA5LjUxNjU2IDE0Ni43ODMgOS41MTY1NkMxNDYuMDY4IDkuNTE2NTYgMTQ1LjUxMiA5LjY3NTUxIDE0NS4wNzQgOS45NTM2OUMxNDQuNjM3IDEwLjIzMTkgMTQ0LjQzOSAxMC42MjkzIDE0NC40MzkgMTEuMTA2MUMxNDQuNDM5IDExLjQ2MzggMTQ0LjU1OCAxMS43NDIgMTQ0Ljc1NyAxMS45MDA5QzE0NC45NTUgMTIuMDU5OSAxNDUuMjMzIDEyLjIxODggMTQ1LjU1MSAxMi4yOTgzQzE0NS44NjkgMTIuMzc3OCAxNDYuMzQ2IDEyLjQ1NzMgMTQ3LjAyMiAxMi41MzY3QzE0Ny45MzYgMTIuNjU2IDE0OC42OTEgMTIuNzc1MiAxNDkuMjg3IDEyLjk3MzlDMTQ5Ljg4MyAxMy4xNzI2IDE1MC4zNiAxMy40NTA4IDE1MC43MTcgMTMuODg3OUMxNTEuMDc1IDE0LjMyNSAxNTEuMjc0IDE0Ljg4MTQgMTUxLjI3NCAxNS41OTY3QzE1MS4yNzQgMTYuMjMyNSAxNTEuMDc1IDE2Ljc4ODkgMTUwLjcxNyAxNy4yNjU3QzE1MC4zNiAxNy43NDI2IDE0OS44NDMgMTguMTAwMyAxNDkuMjQ3IDE4LjM3ODRDMTQ4LjY1MSAxOC42NTY2IDE0Ny45MzYgMTguNzc1OCAxNDcuMTgxIDE4Ljc3NThDMTQ1LjgzIDE4Ljc3NTggMTQ0LjcxNyAxOC40NTc5IDE0My45MjIgMTcuODIyMUMxNDMuMDg4IDE3LjE4NjMgMTQyLjY5IDE2LjMxMiAxNDIuNjUgMTUuMTk5M0gxNDQuMzE5QzE0NC4yIDE1LjgzNTEgMTQ0LjQ3OCAxNi4zMTIgMTQ0Ljk5NSAxNi43MDk0WiIgZmlsbD0iIzA0MDUwNSIvPgo8cGF0aCBkPSJNMTYyLjMyMiAxMi44MTYxVjE4LjYxOEgxNjAuNjUzVjEyLjkzNTNDMTYwLjY1MyAxMS44NjIzIDE2MC40MTUgMTEuMDI3OCAxNTkuOTM4IDEwLjQ3MTRDMTU5LjQ2MSA5LjkxNTA4IDE1OC43NDYgOS42MzY5IDE1Ny44MzIgOS42MzY5QzE1Ni44NzggOS42MzY5IDE1Ni4xMjMgOS45OTQ1NiAxNTUuNTI3IDEwLjY3MDFDMTU0Ljk3MSAxMS4zNDU3IDE1NC42OTIgMTIuMjU5NyAxNTQuNjkyIDEzLjQ1MTlWMTguNjU3N0gxNTMuMDIzVjQuMzUxNTZIMTU0LjY5MlY5LjkxNTA4QzE1NS4wNSA5LjM1ODczIDE1NS40ODcgOC45MjE1OSAxNTYuMDQ0IDguNjAzNjhDMTU2LjYgOC4yNDYwMiAxNTcuMzE1IDguMDg3MDcgMTU4LjExIDguMDg3MDdDMTU5LjM4MiA4LjA4NzA3IDE2MC4zNzUgOC40ODQ0NiAxNjEuMTcgOS4yMzk1MUMxNjEuOTY1IDkuOTk0NTYgMTYyLjMyMiAxMS4xODY3IDE2Mi4zMjIgMTIuODE2MVoiIGZpbGw9IiMwNDA1MDUiLz4KPHBhdGggZD0iTTMuOTQyNDIgMC41ODM5ODRIMC41ODM5ODRWMy45NDI0MkgzLjk0MjQyVjAuNTgzOTg0WiIgZmlsbD0iIzAwN0FGRiIvPgo8cGF0aCBkPSJNMjIuMzQwOSAwLjU4Mzk4NEgxOC45ODI0VjMuOTQyNDJIMjIuMzQwOVYwLjU4Mzk4NFoiIGZpbGw9IiMwMDdBRkYiLz4KPHBhdGggZD0iTTE5LjEyMTIgOS4xOTY5NEwxMy43MjM5IDMuODAzNTdWMEg5LjE5Njk0VjMuODAzNTdMMy44MDM1NyA5LjE5Njk0SDBWMTMuNzIzOUg0LjUyNjkzVjkuOTIwMjlMOS45MjAyOSA0LjUyNjkzSDEzLjAwMDVMMTguMzk3OSA5LjkyMDI5VjEzLjcyMzlIMjIuOTI0OFY5LjE5Njk0SDE5LjEyMTJaIiBmaWxsPSIjMDA3QUZGIi8+CjxwYXRoIGQ9Ik0xMy43MjQyIDE4LjM5ODRIOS4xOTcyN1YyMi45MjU0SDEzLjcyNDJWMTguMzk4NFoiIGZpbGw9IiMwMDdBRkYiLz4KPHBhdGggZD0iTTEzLjcyMzYgMTMuMDAwOFY5LjE5NzI3SDkuMTk2NjhWMTMuMDAwOEwzLjIxOTA2IDE4Ljk4MjRIMC41ODM5ODRWMjIuMzQwOUgzLjk0MjQyVjE5LjcwNThMOS45MjAwMyAxMy43MjQySDEzLjAwMDJMMTguOTgxOCAxOS43MDU4VjIyLjM0MDlIMjIuMzQwM1YxOC45ODI0SDE5LjcwNTJMMTMuNzIzNiAxMy4wMDA4WiIgZmlsbD0iIzAwN0FGRiIvPgo8L3N2Zz4K"
                    alt="">
            </td>
            <td>
                @if(!empty($qrImage))<img width="50" height="50" src="data:image/png;base64, {{$qrImage}}" alt=""> @endif
            </td>
        </tr>
        </tbody>
    </table>
    <p style="border-bottom: 1px solid #687196; border-top: 1px solid #687196; height: 2px;"></p>
    <p class="text-center font-700 text-lg border-b" style="margin-top: 30px; margin-bottom: 10px;">QURILISHI
        TUGALLANGAN
        OBYEKTNI TOPSHIRISH-QABUL QILISH DALOLATNOMASI</p>

    <table class="table table-borderless">
        <tbody>
        <tr>
            <td>
                <div>{{ $claimGov->created_at }}</div>
            </td>
            <td>
                <div class="text-right">{{ $claimGov->region->name_uz }}</div>
            </td>
        </tr>
        </tbody>
    </table>

    <p>{{ $claimGov->object->name }}</p>

    <div>
        <p>1. Buyurtmachi {{ $claimGov->legal_name }}
            tomonidan {{ $claimGov->district->name_uz ." ".  $claimGov->address}} manzilda
            joylashgan {{ $claimGov->object->name }} foydalanishga qabul qilish uchun taqdim etildi.</p>
        <p>2. Qurilish ishlari {{ $claimGov->region->name_uz }} hududiy qurilish va uy-joy kommunal xo'jaligi sohasida
            nazorat qilish inspeksiyasining {{ $claimGov->object->task_id }}-son ruxsatnomasi asosida amalga
            oshirilgan.</p>
        <p>3. Qurilish ishlarni bajargan bosh pudrat
            tashkiloti {{ $vendor?->organization_name }}
            tomonidan bajarilgan</p>
        <p>4. Qurilish ishlari uchun loyiha hujjatlari {{ $claimGov->object->name }} ishlab chiqilgan bosh loyiha
            tashkiloti {{ $designer?->organization_name }}
            tomonidan bajarilgan.</p>
        <p>5. Loyihalashtirish uchun dastlabki maʼlumotlar buyurtmachi tomonidan taqdim etilgan.</p>
        <p>6. Qurilish yakka tartibdagi loyiha boʻyicha amalga
            oshirilgan.</p>
        <p>7. Loyiha hujjatlariga {{ $object->reestr_number }}-sonli
            ijobiy ekspertiza xulosasi olingan.</p>
        <p>8. Qurilish-montaj ishlari qurilish davomiyligi meʼyor boʻyicha {{ $objectCreated->diffInDays($deadline) }}
            haqiqatda {{ $customerConfirmed->diffInDays($objectCreated) }} bo'lgan holda
            quyidagi muddatlarda bajarilgan: ishlarni boshlanishi {{ $objectCreated }},
            tugatish {{ $claimGov->object->closed_at }}</p>
        <p>9. Foydalanishga qabul qilish uchun taqdim etilgan obyekt quvvati, ishlab chiqarish unumdorligi, ishlab
            chiqarish maydoni, tarmoq uzunligi, sigʻimi, hajmi, oʻtkazish qobiliyati, ishchi oʻrinlari soni boʻyicha
            quyidagi asosiy koʻrsatkichlarga ega (barcha obyektlar, turar joy uylardan tashqari), maqsadli mahsulot yoki
            asosiy xizmat turlariga muvofiq oʻlchov birliklarida toʻldiriladi):</p>
    </div>
    <table class="table table-bordered">
        <thead>
        <tr>
            <th class="text-left font-700" rowspan="2">Quvvati, unumdorligi</th>
            <th class="font-700" rowspan="2">Oʻlchov birligi</th>
            <th class="font-700" colspan="2">Loyiha bo'yicha</th>
            <th class="font-700" colspan="2">Amalda</th>
        </tr>
        <tr>
            <th class="font-700">Umumiy (avval qabul qilinganlarini hisobga olgan holda)</th>
            <th class="font-700">Shu jumladan ishga tushirish kompleksi yoki navbati</th>
            <th class="font-700">Umumiy (shu jumladan, avval qabul qilinganlari)</th>
            <th class="font-700">Shu jumladan, ishga tushirish kompleksi yoki navbati</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ $declaration->capacity }}</td>
            <td>{{ $declaration->unit->name_uz }}</td>
            <td>{{ $declaration->general_project_info }}</td>
            <td>{{ $declaration->general_project_queue }}</td>
            <td>{{ $declaration->process_project_info }}</td>
            <td>{{ $declaration->process_project_queue }}</td>
        </tr>
        </tbody>
    </table>

    <p class="indent-sm">Boshlangʻich davrda loyihaviy quvvatlarini oʻzlashtirish meʼyorlariga mos keluvchi hajmda
        loyihada koʻzda tutilgan mahsulotni ishlab chiqarish (xizmat
        koʻrsatish) {{ $declaration->product_production }}</p>
    <p class="indent-sm">Foydalanishga qabul qilish uchun taqdim etilgan ijtimoiy va turar joy obyektlari boʻyicha
        majburiy quyidagi koʻrsatkichlarga ega: </p>
    @if($declaration->blocks)
        <table class="table table-bordered">
            <thead>
            <tr>
                <th class="text-left">Ko'rsatkich</th>
                <th>O'lchov birligi</th>
                <th style="width: 20%">Loyiha bo'yicha</th>
                <th style="width: 20%">Amalda</th>
            </tr>
            </thead>
            <tbody class="text-center">
            @foreach($declaration->blocks  as $block)
                <tr>
                    <td colspan="4" class="text-center font-700" style="background-color: #eee">
                        Blok: {{ $block->block->name }}</td>
                </tr>
                <tr>
                    <td>Umumiy maydon</td>
                    <td class="text-center">m<sup>2</sup></td>
                    <td>{{ $block->area }}</td>
                    <td>{{ $block->real_area }}</td>
                </tr>
                <tr>
                    <td>Qavatlar soni,shu jumladan, <br> yerto'la qavatlari</td>
                    <td class="text-center">qavat</td>
                    <td>{{ $block->floor }}</td>
                    <td>{{ $block->real_floor }}</td>
                </tr>
                <tr>
                    <td>Umumiy qurilish hajmi</td>
                    <td class="text-center">m<sup>3</sup></td>
                    <td>{{ $block->size }}</td>
                    <td>{{ $block->real_size }}</td>
                </tr>
                <tr>
                    <td>Shu jumladan, yer osti qismi</td>
                    <td class="text-center">yerto'lali/yerto'lasiz</td>
                    <td>{{ $block->under_area }}</td>
                    <td>{{ $block->real_under_area }}</td>
                </tr>
            @endforeach

            </tbody>
        </table>
    @endif

    <div style="margin-bottom: 20px;"></div>

    {{--    <table class="table table-bordered">--}}
    {{--        <thead>--}}
    {{--        <tr>--}}
    {{--            <th class="text-left font-700" rowspan="2">Ko'rsatkichlar</th>--}}
    {{--            <th class="font-700" colspan="3">Loyiha bo'yicha</th>--}}
    {{--            <th class="font-700" colspan="3">Amalda</th>--}}
    {{--        </tr>--}}
    {{--        <tr>--}}
    {{--            <th class="font-700">Xonadonlar soni</th>--}}
    {{--            <th class="font-700" colspan="2">Xonadonlar maydoni, m2</th>--}}
    {{--            <th class="font-700">Xonadonlar soni</th>--}}
    {{--            <th class="font-700" colspan="2">Xonadonlar maydoni, m2</th>--}}
    {{--        </tr>--}}
    {{--        </thead>--}}
    {{--        <tbody>--}}
    {{--        <tr>--}}
    {{--            <td>Jami xonadonlar</td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--        </tr>--}}
    {{--        <tr>--}}
    {{--            <td>shu jumladan:</td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--        </tr>--}}
    {{--        <tr>--}}
    {{--            <td>bir xonali</td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--        </tr>--}}
    {{--        <tr>--}}
    {{--            <td>ikki xonali</td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--        </tr>--}}
    {{--        <tr>--}}
    {{--            <td>uch xonali</td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--        </tr>--}}
    {{--        <tr>--}}
    {{--            <td>to'rt xonali va undan ko'p</td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--            <td></td>--}}
    {{--        </tr>--}}
    {{--        </tbody>--}}
    {{--    </table>--}}
    <p>11. Obyektning texnik va meʼmoriy-qurilish yechimlari quyidagi maʼlumotlar bilan tavsiflanadi:</p>
    <p> {{ $declaration->solution_desc }} </p>

    <p>12. Obyektda loyihada koʻzda tutilgan uskuna, shu jumladan, yongʻin avtomatikasi tizimi yakka va kompleks
        sinovlardan keyin qabul qilish toʻgʻrisidagi dalolatnomalarga muvofiq miqdorda oʻrnatilgan. (dalolatnomalar
        ilova qilinadi) </p>
    <p>13. Loyihada koʻzda tutilgan mehnatni muhofaza qilish, portlash xavfsizligini, yongʻin xavfsizligini, atrof-muhit
        muhofazasini taʼminlaydigan va zilzilaga qarshi tadbirlar:</p>
    <p> {{ $declaration->labor_protection }} </p>
    <p>14. Tashqi sovuq va issiq suv taʼminoti, kanalizatsiya, issiqlik taʼminoti, gaz taʼminoti, elektr taʼminoti va
        aloqa tarmoqlari bino, inshoot va xonaning normal ishlashini taʼminlaydi hamda shahar (tuman) tarmoqlardan
        foydalanish tashkilotlari tomonidan qabul qilingan. </p>
    <p class="indent-sm">Shahar (tuman) tarmoqlardan foydalanish tashkilotlari maʼlumotlari roʻyxati dalolatnomaga ilova
        qilinadi.</p>
    <p>15. Binolarda liftni qurish, bino atrofini koʻkalamzorlashtirish, binolarga olib boruvchi yoʻllar trotuarlar,
        xoʻjalik, oʻyin va sport maydonchalari qoplamalarini, chiqindi yigʻish shoxobchalari, avtoturargoh, elektr
        yoritish tizimini qurish, shuningdek, energiya hamda suv tejash ishlari, shuningdek, binolarning fasadlari
        elementlarini pardozlash ishlari bajarilgan</p>
    <table class="table table-bordered">
        <thead>
        <tr>
            <th class="font-700">Ishlar turi</th>
            <th class="font-700">Oʻlchov birligi</th>
            <th class="font-700">Ishlar hajmi</th>
        </tr>
        </thead>
        <tbody>
        @foreach($declaration->done_work as $work)
            <tr class="text-center">
                <td>{{ $work['done_work_type'] }}</td>
                <td>{{ $work['done_volume'] }}</td>
                <td>{{ $work['done_unit_name'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-bottom: 20px;"></div>
    <div>16. Obyektning joriy narxlardagi umumiy haqiqiy qiymati:</div>
    <div>jami {{ $declaration->construction_price + $declaration->instrument_price }} so'm, shu jumladan:</div>
    <div>qurilish-montaj ishlarining qiymati {{ $declaration->construction_price }} so'm</div>
    <div>uskunalar, asboblar va jihozlar qiymati {{ $declaration->instrument_price }} so'm</div>
    <div style="margin-bottom: 10px;"></div>
    <div>17. Foydalanishga qabul qilinayotgan asosiy fondlar qiymati</div>
    <div>jami {{ $declaration->fond_construction_price + $declaration->fond_instrument_price }} so'm, shu
        jumladan:
    </div>
    <div>qurilish-montaj ishlarining qiymati {{ $declaration->fond_construction_price }} so'm</div>
    <div>uskunalar, asboblar va jihozlar qiymati {{ $declaration->fond_instrument_price }} so'm</div>
    <p>18. Koʻzdan kechirish va tegishli hujjatlar bilan tanishish natijalariga koʻra obyektda qoʻllanilgan texnologik
        yechimlarning ilgʻorligiga baho berildi: <br> {{ $declaration->done_note }} </p>
    <p class="text-center font-700">XULOSA BERUVCHILARNING QARORI:</p>
    <p>Xulosa beruvchilarning xulosalari hamda pudrat tashkilotining muvofiqlik deklaratsiyasiga asosan foydalanishga
        qabul qilish uchun taqdim etilgan <br> {{$claimGov->object->name}}</p>
    <p class="text-center font-700">FOYDALANISHGA QABUL QILINSIN.</p>

    <table class="table table-borderless">
        <tbody>
        {{--        <tr>--}}
        {{--            <td class="align-top justify" style="width: 50%;"><span class="font-700">Komissiya raisi</span>--}}
        {{--                (Buyurtmachi)--}}
        {{--            </td>--}}
        {{--            <td class="align-top">--}}
        {{--                <span>{{ $claimGov->legal_name }}</span>--}}
        {{--            </td>--}}
        {{--            <td class="align-top" style="width: 100px;">--}}
        {{--                <div class="imzo-item">--}}
        {{--                    <span class="imzo-inside">--}}
        {{--                        &#10003;--}}
        {{--                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAiIGhlaWdodD0iMTAiIHZpZXdCb3g9IjAgMCAxMCAxMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTMuNzUgNS40MTY2N0MzLjc1IDUuNDE2NjcgNC4xNjY2NyA1LjQxNjY3IDQuNTgzMzMgNi4yNUM0LjU4MzMzIDYuMjUgNS45MDY4NyA0LjE2NjY3IDcuMDgzMzMgMy43NSIgc3Ryb2tlPSIjMDk5MTIyIiBzdHJva2Utd2lkdGg9IjAuOCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIi8+CjxwYXRoIGQ9Ik04Ljc1IDQuNjYwMzZWMy40NTA3N0M4Ljc1IDIuNzY3NDQgOC43NSAyLjQyNTc3IDguNTgxNjIgMi4yMDI4NkM4LjQxMzI1IDEuOTc5OTQgOC4wMzI1NCAxLjg3MTcyIDcuMjcxMTMgMS42NTUyOEM2Ljc1MDkyIDEuNTA3NCA2LjI5MjMzIDEuMzI5MjUgNS45MjU5NiAxLjE2NjYxQzUuNDI2NDIgMC45NDQ4NTkgNS4xNzY2NyAwLjgzMzk4NCA1IDAuODMzOTg0QzQuODIzMzMgMC44MzM5ODQgNC41NzM1OCAwLjk0NDg1OSA0LjA3NDA1IDEuMTY2NjFDMy43MDc2NiAxLjMyOTI1IDMuMjQ5MSAxLjUwNzQgMi43Mjg4OSAxLjY1NTI4QzEuOTY3NDcgMS44NzE3MiAxLjU4Njc2IDEuOTc5OTQgMS40MTgzOCAyLjIwMjg2QzEuMjUgMi40MjU3NyAxLjI1IDIuNzY3NDQgMS4yNSAzLjQ1MDc3VjQuNjYwMzZDMS4yNSA3LjAwNDE5IDMuMzU5NDkgOC40MTA0NCA0LjQxNDE3IDguOTY3MDdDNC42NjcxMiA5LjEwMDU3IDQuNzkzNTggOS4xNjczMiA1IDkuMTY3MzJDNS4yMDY0MiA5LjE2NzMyIDUuMzMyODcgOS4xMDA1NyA1LjU4NTgzIDguOTY3MDdDNi42NDA1IDguNDEwNDQgOC43NSA3LjAwNDE5IDguNzUgNC42NjAzNloiIHN0cm9rZT0iIzA5OTEyMiIgc3Ryb2tlLXdpZHRoPSIwLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIvPgo8L3N2Zz4K" alt="">--}}
        {{--                        Elektron imzo orqali tasdiqlandi--}}
        {{--                    </span>--}}
        {{--                    <span class="imzo-date">{{ $claimGov?->reviews->where('organization_id', \App\Enums\UserRoleEnum::BUYURTMACHI->value)?->first()->answered_at }}</span>--}}
        {{--                </div>--}}
        {{--            </td>--}}

        {{--        </tr>--}}
        <tr>
            <td colspan="3"><span class="font-700">Komissiya raisi</span></td>
        </tr>
        <tr>
            @if(!empty($signed_info))
                <td class="align-top justify" style="max-width: 50%; min-width: 50%; width: 50%;">{{ $signed_info["O"] }}</td>
                <td class="align-top" style="max-width: 30%; min-width: 30%; width: 30%;">
                    <span>{{ $signed_info["CN"] }}</span>
                </td>
                <td class="align-top">
                    <div class="imzo-item">
                        <span class="imzo-inside">
                            &#10003;
{{--                            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAiIGhlaWdodD0iMTAiIHZpZXdCb3g9IjAgMCAxMCAxMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTMuNzUgNS40MTY2N0MzLjc1IDUuNDE2NjcgNC4xNjY2NyA1LjQxNjY3IDQuNTgzMzMgNi4yNUM0LjU4MzMzIDYuMjUgNS45MDY4NyA0LjE2NjY3IDcuMDgzMzMgMy43NSIgc3Ryb2tlPSIjMDk5MTIyIiBzdHJva2Utd2lkdGg9IjAuOCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIi8+CjxwYXRoIGQ9Ik04Ljc1IDQuNjYwMzZWMy40NTA3N0M4Ljc1IDIuNzY3NDQgOC43NSAyLjQyNTc3IDguNTgxNjIgMi4yMDI4NkM4LjQxMzI1IDEuOTc5OTQgOC4wMzI1NCAxLjg3MTcyIDcuMjcxMTMgMS42NTUyOEM2Ljc1MDkyIDEuNTA3NCA2LjI5MjMzIDEuMzI5MjUgNS45MjU5NiAxLjE2NjYxQzUuNDI2NDIgMC45NDQ4NTkgNS4xNzY2NyAwLjgzMzk4NCA1IDAuODMzOTg0QzQuODIzMzMgMC44MzM5ODQgNC41NzM1OCAwLjk0NDg1OSA0LjA3NDA1IDEuMTY2NjFDMy43MDc2NiAxLjMyOTI1IDMuMjQ5MSAxLjUwNzQgMi43Mjg4OSAxLjY1NTI4QzEuOTY3NDcgMS44NzE3MiAxLjU4Njc2IDEuOTc5OTQgMS40MTgzOCAyLjIwMjg2QzEuMjUgMi40MjU3NyAxLjI1IDIuNzY3NDQgMS4yNSAzLjQ1MDc3VjQuNjYwMzZDMS4yNSA3LjAwNDE5IDMuMzU5NDkgOC40MTA0NCA0LjQxNDE3IDguOTY3MDdDNC42NjcxMiA5LjEwMDU3IDQuNzkzNTggOS4xNjczMiA1IDkuMTY3MzJDNS4yMDY0MiA5LjE2NzMyIDUuMzMyODcgOS4xMDA1NyA1LjU4NTgzIDguOTY3MDdDNi42NDA1IDguNDEwNDQgOC43NSA3LjAwNDE5IDguNzUgNC42NjAzNloiIHN0cm9rZT0iIzA5OTEyMiIgc3Ryb2tlLXdpZHRoPSIwLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIvPgo8L3N2Zz4K" alt="">--}}
                            Elektron raqamli imzo orqali tasdiqlandi
                        </span>
                        <span class="imzo-date">{{ Carbon::parse($signed_info["sign_date"])->format('Y-m-d H:i:s')}}</span>
                    </div>
                </td>
            @endif
        </tr>
        <tr>
            <td colspan="3"><span class="font-700">Komissiya a'zolari</span></td>
        </tr>
        @foreach($claimGov->reviews as $review)
            <tr>
                <td class="align-top justify" >{{ $review->organization->description }}</td>
                <td class="align-top" >
                    <span>{{ $review->user->full_name ?? $review?->user?->organization_name }}</span>
                </td>
                <td class="align-top">
                    <div class="imzo-item">
                        <span class="imzo-inside">
                            &#10003;
{{--                            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAiIGhlaWdodD0iMTAiIHZpZXdCb3g9IjAgMCAxMCAxMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTMuNzUgNS40MTY2N0MzLjc1IDUuNDE2NjcgNC4xNjY2NyA1LjQxNjY3IDQuNTgzMzMgNi4yNUM0LjU4MzMzIDYuMjUgNS45MDY4NyA0LjE2NjY3IDcuMDgzMzMgMy43NSIgc3Ryb2tlPSIjMDk5MTIyIiBzdHJva2Utd2lkdGg9IjAuOCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIi8+CjxwYXRoIGQ9Ik04Ljc1IDQuNjYwMzZWMy40NTA3N0M4Ljc1IDIuNzY3NDQgOC43NSAyLjQyNTc3IDguNTgxNjIgMi4yMDI4NkM4LjQxMzI1IDEuOTc5OTQgOC4wMzI1NCAxLjg3MTcyIDcuMjcxMTMgMS42NTUyOEM2Ljc1MDkyIDEuNTA3NCA2LjI5MjMzIDEuMzI5MjUgNS45MjU5NiAxLjE2NjYxQzUuNDI2NDIgMC45NDQ4NTkgNS4xNzY2NyAwLjgzMzk4NCA1IDAuODMzOTg0QzQuODIzMzMgMC44MzM5ODQgNC41NzM1OCAwLjk0NDg1OSA0LjA3NDA1IDEuMTY2NjFDMy43MDc2NiAxLjMyOTI1IDMuMjQ5MSAxLjUwNzQgMi43Mjg4OSAxLjY1NTI4QzEuOTY3NDcgMS44NzE3MiAxLjU4Njc2IDEuOTc5OTQgMS40MTgzOCAyLjIwMjg2QzEuMjUgMi40MjU3NyAxLjI1IDIuNzY3NDQgMS4yNSAzLjQ1MDc3VjQuNjYwMzZDMS4yNSA3LjAwNDE5IDMuMzU5NDkgOC40MTA0NCA0LjQxNDE3IDguOTY3MDdDNC42NjcxMiA5LjEwMDU3IDQuNzkzNTggOS4xNjczMiA1IDkuMTY3MzJDNS4yMDY0MiA5LjE2NzMyIDUuMzMyODcgOS4xMDA1NyA1LjU4NTgzIDguOTY3MDdDNi42NDA1IDguNDEwNDQgOC43NSA3LjAwNDE5IDguNzUgNC42NjAzNloiIHN0cm9rZT0iIzA5OTEyMiIgc3Ryb2tlLXdpZHRoPSIwLjgiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIvPgo8L3N2Zz4K" alt="">--}}
                            Elektron raqamli imzo orqali tasdiqlandi
                        </span>
                        <span class="imzo-date">{{ $review->answered_at }}</span>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
</body>
</html>
