<?php

use App\Infrastructure\ExternalApis\ShaffofId\ShaffofIdServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Shahzod'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),






    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),


    'url' => env('APP_URL', 'http://localhost'),

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN', '8585822081:AAEWW2VpKOMiy-1Fu6SeVgNr_v8tV_YAAn4'),
        'chat_id' => env('TELEGRAM_CHAT_ID', '-1003603340822'),
        'chat_id_tender' => env('TELEGRAM_CHAT_ID_TENDER', '-1003907381044')
    ],

    'mygov' => [
        'url' => env('MY_GOV_URL', 'https://oldmy.gov.uz/notice-beginning-construction-works-v4/rest-api'),
        'url_mc' => env('MY_GOV_URL_MC', 'https://oldmy.gov.uz/construction-acceptance-permit-v1/rest-api/'),
        'linear' => env('MY_GOV_LINEAR_URL', 'https://oldmy.gov.uz/registration-start-linear-object-v1/rest-api'),
        'login' => env('MY_GOV_LOGIN', 'qurilish.sohasida.nazorat.inspeksiya.201122919'),
        'login_mc' => env('MY_GOV_LOGIN_MC', 'qurilishvazirligi.200934644.k6xkKycCL88sCq53gL9s73HFwij'),
        'password' => env('MY_GOV_PASS', 'Cx8]^]-Gk*mZK@.,S=c.g65>%[$TNRV75bYX<v+_'),
        'password_mc' => env('MY_GOV_PASS_MC', 'Jsd23s!2VFV4SNMbHdsd57jr6RAyt9O4'),
    ],

    'turarjoy' => [
        'url' => env('TURARJOY_URL', 'https://back-turarjoy.kommunal.uz/api/new-apartment-add'),
        'login' => env('TURARJOY_LOGIN', 'Baymax'),
        'password' => env('TURARJOY_PASSWORD', '7reFNkYoY2BiW7k'),
    ],

    "passport" => [
        "url" => 'https://api.shaffofqurilish.uz/api/v1/get-egov-token',
        "login" => env('BANK_USERNAME', 'dev@gasn'),
        "password" => env('BANK_PASSWORD', 'EkN`9?@{3v0j'),
    ],

    'opendata' => [
        'object' => 'https://open-data.mc.uz/api/gasn/registration',
        'claim' => 'https://open-data.mc.uz/api/gasn/complete',
        'login' => "gasnkhurshidbek",
        'password' => "bZUN'PW>8BdF!6@T",
    ],
    'apartment' => [
        'url' => 'https://api-uy.shaffofqurilish.uz/api/gasn-object',
        'login' => "api-bank",
        'password' => "STSNP6I36V6J",
    ],
    'lift' => [
        'url' => 'https://api-lift.mc.uz/api/get-lift-list',
        'login' => "GASN2026^^352",
        'password' => "yjxEG'^m{-ng$0="
    ],

    'e-invoice' => [
        'url' => 'https://back.e-invoice.uz/api/ru/',
        'phone' => '+998977338128',
        'password' => 'V2ynuDgP5drSN'
    ],

    'shaffofId' => [
        'url' => 'https://id.shaffofqurilish.uz',
        'client_id' => '01a0cda1-5861-724f-89c0-f5be47a66a05',
        'client_secret' => 'SI5RZYSyvZVWV5qN1BGRwrq3C8tkGXyAoLyS9hus',
        'redirect_url' => 'https://nazorat.mc.uz/login',
    ],


    'ekspertiza_info' => [
        'url' =>'https://api-ekspertiza.mc.uz/api/v3/intg-rts-out/get-completed-appeal',
        'login' => 'apibank@mc.uz',
        'password' => 'HkBxih2WuBwCwQI'
    ],

    'tender' => [
        'url' => 'https://apisitender.mc.uz/api/direct-contract-by-gnk_id',
        'push_url' => 'https://apisitender.mc.uz/api/complete-tender-by-dalolatnoma',
        'login' => 'gasn_dalolatnoma@gmail.com',
        'password' => '2@;H&2*2<|?C3cjm)\lj'
    ],

    "xulosalar" => [
        "url" => env('XULOSALR_URL'),
        "user_name" => env('XULOSALAR_USER_NAME'),
        "password" => env('XULOSALAR_PASSWORD'),
    ],

    'rating' => [
        'url' => 'https://japi-reyting.mc.uz/api/v1/rating/check-construction/',
        'login' => 'reyting',
        'password' => 'U0o8k8WH86kEQC0oR6ohUnbtNE5ANfC',
    ],

    'gasn' => [
        'monitoring' => 'https://api.shaffofqurilish.uz/api/v1/request/monitoring-objects?ekspertiza_number',
        'get_monitoring' => 'https://api.shaffofqurilish.uz/api/v1/request/monitoring-objects?gnk_id',
        'programs' => 'https://api.shaffofqurilish.uz/api/v1/request/monitoring-dasturlar',
        'reestr' => 'https://api.shaffofqurilish.uz/api/v1/request/ekspertiza-conc-file?reestr_number',
        'rating' => 'https://api.shaffofqurilish.uz/api/v1/request/reyting?INN',
        'conference' => 'https://api.shaffofqurilish.uz/api/v1/request/dx-kengash?conc',
        'sphere' => 'https://api.shaffofqurilish.uz/api/v1/request/monitoring-soha',
        'expertise' => 'https://api.shaffofqurilish.uz/api/v1/request/opendata-ekspertiza?reestr_number',
        'tender' => 'https://api.shaffofqurilish.uz/api/v1/request/ekspertiza-appeal-tender?conc',
        'bayonnoma' => 'https://api.shaffofqurilish.uz/api/v1/request/ccnis-dxa-watcher-type?conclusion',
        'cadastr' => 'https://api.shaffofqurilish.uz/api/v1/get-cad-info',
        'shaffof_expertise' => 'https://api.shaffofqurilish.uz/api/v1/get-shaffof-object?expertise',
        'shaffof_protocol' => 'https://api.shaffofqurilish.uz/api/v1/get-shaffof-object?project',
        'shaffof_send' => 'https://api.shaffofqurilish.uz/api/v1/push-service/expertise/shaffof',
        'shaffof_push' => 'https://api.shaffofqurilish.uz/api/v1/push-service/gasn/shaffof',
        'shaffof_push_claims' => 'https://api.shaffofqurilish.uz/api/v1/push-service/gasn_conclusion/shaffof',
        'shaffof_uy' => 'https://api-uy.shaffofqurilish.uz/api/gasn-object',
        'funding_source' => 'https://api.shaffofqurilish.uz/api/v1/request/appeal-finance-source?id'
    ],

    'sert' => [
        'url' => "https://api.shaffofqurilish.uz/api/v1/request/get-sert-info?pinfl="
    ],

    'mediamtx' => [
        'api' => env('MEDIAMTX_API'),
        'webrtc' => env('MEDIAMTX_WEBRTC'),
    ],

    'rate_supervision' => 0.2,

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'Asia/Tashkent',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store' => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        Spatie\Permission\PermissionServiceProvider::class,
        SimpleSoftwareIO\QrCode\QrCodeServiceProvider::class,
        ShaffofIdServiceProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
        'QrCode' => SimpleSoftwareIO\QrCode\Facades\QrCode::class,
    ])->toArray(),

    'holidays' => [
        '21-04-2023',
        '24-04-2023',
        '09-05-2023',
        '01-09-2023',
        '02-09-2023',
        '03-09-2023',
        '02-10-2023',
        '08-12-2023',
        '01-01-2024',
        '02-01-2024',
        '08-03-2024',
        '21-03-2024',
        '22-03-2024',
        '10-04-2024',
        '11-04-2024',
        '12-04-2024',
        '09-05-2024',
        '17-06-2024',
        '18-06-2024',
    ]
];
