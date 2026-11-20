<?php

namespace App\Console\Commands;

use App\Enums\LogType;
use App\Helpers\ClaimStatuses;
use App\Models\Claim;
use App\Services\ClaimService;
use App\Services\HistoryService;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendToMinstroyCommand extends Command
{
    const URl = "https://oldmy.gov.uz/completed-buildings-registration-cadastral-v2/rest-api/";
    protected $signature = 'app:send-to-minstroy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(
        ClaimService   $claimService,
    )
    {
        parent::__construct();
        $this->claimService = $claimService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $historyService = new HistoryService('claim_histories');

        $claims = Claim::query()
            ->where('is_minstroy', true)
            ->where('status', ClaimStatuses::TASK_STATUS_ACCEPTANCE)
            ->orderBy('id')
            ->limit(10)
            ->get();


        $client = new Client();
        $apiCredentials = config('app.passport.login') . ':' . config('app.passport.password');

        foreach ($claims as $claim) {
            try {
                $resClient = $client->post(
                    'https://api.shaffofqurilish.uz/api/v1/request/ccnis-dxa-watcher-type',
                    [
                        'headers' => [
                            'Authorization' => 'Basic ' . base64_encode($apiCredentials),
                        ],
                        'query' => [
                            'conclusion' => $claim->number_conclusion_project,
                        ],
                        'timeout' => 5,
                        'connect_timeout' => 5,
                    ]
                );

                $response = json_decode($resClient->getBody(), true);

                if (
                    empty($response['result']['data']['success']) ||
                    empty($response['result']['data']['conclusions']) ||
                    count($response['result']['data']['conclusions']) !== 1
                ) {
                    continue;
                }

                $conclusion = $response['result']['data']['conclusions'][0];

                if ((int)$conclusion['watcher_type'] !== 1) {
                    continue;
                }

                $comment = "Tuman (shahar) qurilish va uy-joy kommunal xo`jaligi bo‘limlari qurilish-montaj ishlari tugallangan ikki qavatdan yuqori bo‘lmagan (sokolni hisobga olmagan holda), balandligi yer
                                yuzasidan 12 metrdan va (yoki) umumiy maydoni 500 kvadrat metrdan ortiq bo‘lmagan yakka tartibdagi uy-joylar va 300 metr kubdan ortiq bo‘lmagan noturar bino va inshootlardan (keyingi o‘rinlarda — I toifa obyektlar)
                                foydalanish uchun ruxsatnoma beradilar";

                DB::transaction(function () use ($claim, $historyService, $comment) {
                    $dataArray = [
                        'SendObjectToMinstroyV2FormCompletedBuildingsRegistrationCadastral' => [
                            'comment_to_send_minstroy' => $comment,
                        ]
                    ];

                    $response = $this->PostRequest(
                        "update/id/{$claim->guid}/action/send-object-to-minstroy",
                        $dataArray
                    );

                    if (!$response->successful()) {
                        throw new \Exception(
                            json_encode([
                                'status' => $response->status(),
                                'json'   => $response->json(),
                            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                        );
                    }

                    $claim->update([
                        'status' => ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG,
                        'end_date' => now(),
                    ]);




                    $historyService->createHistory(
                        guId: $claim->guid,
                        status: ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG,
                        type: LogType::TASK_HISTORY,
                        date: null,
                        comment: $comment
                    );

//                    telegramNotification("Boshqarmaga yuborildi", $claim->guid, $response->json());

                });

            } catch (\Throwable $e) {
                telegramNotification("Boshqarmaga yuborishda xatolik", $claim->guid, $e->getMessage());
                $claim->update(['is_minstroy' => false]);
                Log::error('Minstroy send failed', [
                    'claim_id' => $claim->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }


    private function PostRequest($url, $data)
    {
        $response = Http::withBasicAuth(config('app.mygov.login'), config('app.mygov.password'))->post(self::URl . $url, $data);

        return $response;
    }
}
