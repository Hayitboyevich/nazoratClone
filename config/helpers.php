<?php

use App\Notifications\TelegramLogNotification;
use GuzzleHttp\Client;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

if (!function_exists('price_supervision')) {
    function price_supervision($price)
    {
        $rate = config("app.rate_supervision");
        $amount = $price * ($rate / 100);

        return number_format($amount, 2, '.', '');
    }
}

if (!function_exists('pagination')) {
    function pagination(object $model)
    {
        if ($model)
            $data = [
                'lastPage' => $model->lastPage(),
                'total' => $model->total(),
                'perPage' => $model->perPage(),
                'currentPage' => $model->currentPage(),
            ];
        else
            $data = [
                'lastPage' => 0,
                'total' => 0,
                'perPage' => 0,
                'currentPage' => 0,
            ];
        return $data;
    }
}

if (!function_exists('getData')) {
    function getData(?string $baseUrl, ?string $param = null){
        try {
            $start = microtime(true);

            $client = new Client([
                'timeout' => 10,
                'connect_timeout' => 5,
            ]);
            $apiCredentials = config('app.passport.login') . ':' . config('app.passport.password');
            $url = $param ? $baseUrl.'='.$param : $baseUrl;

            $resClient = $client->post($url,
                [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($apiCredentials),
                    ],
                    'verify' => false
                ]);
            $response = json_decode($resClient->getBody(), true);
            return $response['result'];
        }catch (\Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);

            \Log::error('External API', [
                'url' => $baseUrl,
                'duration_ms' => $duration,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

    }
}

if (!function_exists('egovAction')) {
    function egovAction(?string $baseUrl, ?string $param = null){
        try {
            $start = microtime(true);
            $client = new Client([
                'timeout' => 10,
                'connect_timeout' => 5,
            ]);
            $apiCredentials = config('app.passport.login') . ':' . config('app.passport.password');
            $url = $param ? $baseUrl.'='.$param : $baseUrl;

            $resClient = $client->post($url,
                [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($apiCredentials),
                    ]
                ]);
            $response = json_decode($resClient->getBody(), true);
            return $response['result'] ?? null;
        } catch (\Throwable $e) {

            $duration = round((microtime(true) - $start) * 1000, 2);

            \Log::error('External API', [
                'url' => $baseUrl,
                'duration_ms' => $duration,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
if (!function_exists('getRegionName')) {
    function getRegionName(?int $regionId){
        $array = [
            1 => 'города Ташкент',
            2 => 'Ташкентской области',
            3 => 'Сырдарьинской области',
            4 => 'Джизакской области',
            5 => 'Самаркандской области',
            6 => 'Ферганской области',
            7 => 'Наманганской области',
            8 => 'Андижанской области',
            9 => 'Кашкадарьинской области',
            10 => 'Сурхандарьинской области',
            11 => 'Бухарской области',
            12 => 'Навоийской области',
            13 => 'Хорезмской области',
            14 => 'Республики Каракалпакстан',
        ];


        return $array[$regionId] ?? null;
    }
}

if(!function_exists('deadline')){
    function deadline(?int $days, ?string $startTime = null)
    {
//        $date = Carbon::now();
//        $addedDays = 0;
//
//        while ($addedDays < $days) {
//            $date->addDay();
//
//            if ($date->isWeekend() || Holiday::whereDate('day', $date->toDateString())->exists()) {
//                continue;
//            }
//
//            $addedDays++;
//        }
//
        $date = $startTime ? Carbon::parse($startTime) : Carbon::now();
        $currentYear = $date->year;

        while (
            $date->isWeekend() ||
            Holiday::whereYear('day', $currentYear)->whereDate('day', $date->toDateString())->exists()
        ) {
            $date->addDay()->startOfDay();
        }

        $addedDays = 0;

        while ($addedDays < $days) {
            $date->addDay();

            if (
                $date->isWeekend() ||
                Holiday::whereYear('day', $currentYear)->whereDate('day', $date->toDateString())->exists()
            ) {
                continue;
            }

            $addedDays++;
        }

        return $date->toDateTimeString();

    }
}

if (!function_exists('getMonthName')) {
    function getMonthName(?string $number){
        $array = [
            '1' => 'Yanvar',
            '2' => 'Fevral',
            '3' => 'Mart',
            '4' => 'Aprel',
            '5' => 'May',
            '6' => 'Iyun',
            '7' => 'Iyul',
            '8' => 'Avgust',
            '9' => 'Sentabr',
            '10' => 'Oktabr',
            '11' => 'Noyabr',
            '12' => 'Dekabr',
        ];


        return $array[$number] ?? null;
    }
}

if (!function_exists('numberToUzbekWords')){
    function numberToUzbekWords($amount): string
    {
        $amount = str_replace(',', '.', $amount);

        if (!is_numeric($amount)) {
            return false;
        }

        $normalized = number_format((float)$amount, 2, '.', '');
        list($som_str, $tiyn_str) = explode('.', $normalized);

        $som = (int)$som_str;
        $tiyn = (int)$tiyn_str;

        $words = numberToUzbekWordsInt($som);

        if ($tiyn > 0) {
            $words .= ' ' . numberToUzbekWordsInt($tiyn) . ' tiyin';
        }

        return $words;
    }
}


if (!function_exists('numberToUzbekWordsInt')) {
    function numberToUzbekWordsInt($number)
    {
        $ones = [
            0 => 'nol', 1 => 'bir', 2 => 'ikki', 3 => 'uch', 4 => 'to‘rt',
            5 => 'besh', 6 => 'olti', 7 => 'yetti', 8 => 'sakkiz', 9 => 'to‘qqiz'
        ];
        $tens = [
            10 => 'o‘n', 20 => 'yigirma', 30 => 'o‘ttiz', 40 => 'qirq',
            50 => 'ellik', 60 => 'oltmish', 70 => 'yetmish', 80 => 'sakson', 90 => 'to‘qson'
        ];

        if ($number < 10) {
            return $ones[$number];
        }

        if ($number < 100) {
            $t = intval($number / 10) * 10;
            $o = $number % 10;
            return $tens[$t] . ($o ? ' ' . $ones[$o] : ' so\'m');
        }

        if ($number < 1000) {
            $h = intval($number / 100);
            $r = $number % 100;
            return $ones[$h] . ' yuz' . ($r ? ' ' . numberToUzbekWords($r) : ' so\'m');
        }

        if ($number < 1000000) { // ming
            $k = intval($number / 1000);
            $r = $number % 1000;
            return numberToUzbekWords($k) . ' ming' . ($r ? ' ' . numberToUzbekWords($r) : ' so\'m');
        }

        if ($number < 1000000000) { // million
            $m = intval($number / 1000000);
            $r = $number % 1000000;
            return numberToUzbekWords($m) . ' million' . ($r ? ' ' . numberToUzbekWords($r) : ' so\'m');
        }

        if ($number < 1000000000000) { // milliard
            $b = intval($number / 1000000000);
            $r = $number % 1000000000;
            return numberToUzbekWords($b) . ' milliard' . ($r ? ' ' . numberToUzbekWords($r) : ' so\'m');
        }

        return $number;
    }
}

if (!function_exists('getRealData')) {
    function getRealData(Request $request = null): array
    {
        $request = $request ?: request();

        return [
            'ip'        => $request->ip(),
            'method'    => $request->method(),
            'path'      => $request->path(),
            'url'       => $request->fullUrl(),
            'user_agent'=> $request->userAgent(),
            'request_id'=> $request->header('X-Request-Id'),
            'timestamp' => now()->toIso8601String(),
        ];
    }


}

if (!function_exists('telegramNotification')) {
    function telegramNotification($action = null, $id = null,  $message = null)
    {
        $text = '';

        if ($action) {
            $text .= "<b>❗️ Action:</b> <i>{$action}</i>" . PHP_EOL;
        }

        if ($id) {
            $text .= "<b>ID:</b> <code>{$id}</code>" . PHP_EOL;
        }

        if ($message) {
            if (is_array($message) || is_object($message)) {
                $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }

            $text .= "<pre>" . htmlspecialchars((string)$message) . "</pre>";
        }

        Notification::route('telegram', config('services.telegram.chat_id'))
            ->notify(new TelegramLogNotification($text));
    }
}



