<?php

namespace App\Console\Commands;

use App\Exports\ClaimTaskExcel;
use App\Helpers\ClaimStatuses;
use App\Models\Article;
use App\Models\Block;
use App\Models\CheckListAnswer;
use App\Models\Claim;
use App\Models\ClaimOrganizationReview;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;

class TestCommand extends Command
{

    protected $signature = 'app:test';


    protected $description = 'Command description';

//    public function handle()
//    {
//        $query = User::query()
//            ->whereRaw('char_length(pinfl) = 14')
//            ->whereNull('mehnat_integration')
//            ->limit(300);
//
//        $total = $query->count();
//
//        if ($total === 0) {
//            $this->info('Ma\'lumot topilmadi.');
//            return self::SUCCESS;
//        }
//
//        $client = new Client([
//            'base_uri' => 'https://lic.mc.uz',
//            'timeout' => 10,
//            'connect_timeout' => 5,
//            'verify' => false,
//        ]);
//
//        $bar = $this->output->createProgressBar($total);
//        $bar->start();
//
//        $query->chunkById(100, function ($users) use ($client, $bar) {
//
//            $requests = function () use ($users, $client) {
//                foreach ($users as $user) {
//                    yield $user->id => function () use ($client, $user) {
//                        return $client->getAsync(
//                            '/bachelor-degree-fake',
//                            [
//                                'query' => [
//                                    'pinfl' => $user->pinfl,
//                                ],
//                            ]
//                        );
//                    };
//                }
//            };
//
//            $pool = new Pool($client, $requests(), [
//                'concurrency' => 20,
//
//                'fulfilled' => function ($response, $userId) use ($bar) {
//
//                    try {
//                        $user = User::find($userId);
//
//                        if (!$user) {
//                            $bar->advance();
//                            return;
//                        }
//
//                        $data = json_decode(
//                            $response->getBody()->getContents(),
//                            true
//                        );
//
//                        if (
//                            empty($data) ||
//                            isset($data['message']) ||
//                            !isset($data[0])
//                        ) {
//                            $user->update([
//                                'mehnat_integration' => false,
//                            ]);
//
//                            $bar->advance();
//                            return;
//                        }
//
//                        $user->update([
//                            'institution_type_name' => $data[0]['institution_type_name'] ?? null,
//                            'degree_name'           => $data[0]['degree_name'] ?? null,
//                            'institution_name'      => $data[0]['institution_name'] ?? null,
//                            'speciality_name'       => $data[0]['speciality_name'] ?? null,
//                            'edu_finishing_date'    => $data[0]['edu_finishing_date'] ?? null,
//                            'mehnat_integration'    => true,
//                        ]);
//                    } catch (\Throwable $e) {
//                        User::where('id', $userId)
//                            ->update([
//                                'mehnat_integration' => false,
//                            ]);
//                    }
//
//                    $bar->advance();
//                },
//
//                'rejected' => function ($reason, $userId) use ($bar) {
//
//                    User::where('id', $userId)
//                        ->update([
//                            'mehnat_integration' => false,
//                        ]);
//
//                    $bar->advance();
//                },
//            ]);
//
//            $pool->promise()->wait();
//        });
//
//        $bar->finish();
//
//        $this->newLine(2);
//        $this->info('Tugadi.');
//
//        return self::SUCCESS;
//    }


    public function handle()
    {
        $total = Claim::whereNull('elastic')->where('status', '<>', ClaimStatuses::TASK_STATUS_ANOTHER)->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $fields = [
            'ariza_raqami',
            'obyekt_nomi',
            'obyekt_raqami',
            'region_name',
            'region_id',
            'district_name',
            'cadaster',
            'customer',
            'customer_inn',
            'count_apartments',
            'priyomka_jami_block',
            'priyomka_noturar',
            'priyomka_turar',
            'priyomka_yakka',
            'umumiy_maydon',
            'umumiy_noturar',
            'umumiy_turar',
            'umumiy_yakka',
            'foydalanish_maydon',
            'foydalanish_noturar',
            'foydalanish_turar',
            'foydalanish_yakka',
            'yashash_maydon',
            'yashash_noturar',
            'yashash_turar',
            'yashash_yakka',
            'qurilish_osti_maydoni',
            'qurilish_osti_noturar',
            'qurilish_osti_turar',
            'qurilish_osti_yakka',
            'inskepsiya_kelgan_vaqt',
            'claim_id',
            'tashkilotga_yuborilgan_sana',
            'oxirgi_tashkilot_sanasi',
            'inspektor_hulosa_vaqti',
            'inspektor',
            'yuridik_jismoniy',
            'manzil',
            'obyekt_turi',
            'toifasi',
            'obyekt_created_at',
            'direktorga_yuborilgan_vaqt',
            'tugagan_sana',
            'status',
            'end_role_name',
            'reject_reason',
            'dastur_nomi',
            'quyosh_panel',
            'konstruktiv_checklist',
            'seysmik_checklist'
        ];

        try {
            $claims = Claim::query()->where('claims.status', '<>', ClaimStatuses::TASK_STATUS_ANOTHER)
                ->whereNull('claims.elastic')
                ->chunk(50, function ($claims) use ($bar, $fields) {
                    foreach ($claims as $claim) {
                        $data = $this->excelTask($claim->guid);

                        $data[0]['claim_id'] = $claim->id;
                        $data[0]['created_at'] = $claim->created_at;


                        DB::table('claim_excel')->upsert(
                            $data[0],
                            ['claim_id'],
                           $fields
                        );

                        $claim->update(['elastic' => true]);


                        $bar->advance();
                    }
                });

        } catch (\Exception $exception) {
            dd($exception->getMessage(), $exception->getLine());
        }
        $bar->finish();
        $this->info("\nTugadi ✅");
    }

    public function excelTask($taskId)
    {

        $claims = Claim::query()
            ->leftJoin('articles as a', 'a.id', '=', 'claims.object_id')
            ->leftJoin('article_users as ar', function ($leftJoin) {
                $leftJoin
                    ->on('ar.article_id', '=', 'a.id')
                    ->where('ar.role_id', '=', 3);
            })
            ->leftJoin('users as u', function ($leftJoin) {
                $leftJoin
                    ->on('u.id', '=', 'ar.user_id');
            })
            ->leftJoin('regions as r', 'r.soato', '=', 'claims.region')
            ->leftJoin('districts as d', 'd.soato', '=', 'claims.district')
            ->where('claims.status', '<>', ClaimStatuses::TASK_STATUS_ANOTHER)
            ->select([
                'claims.guid as ariza_raqami',
                'claims.building_cadastral as cadaster',
                DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_name ELSE claims.ind_name END) as customer"),
                DB::raw("(CASE WHEN claims.user_type = 'J' THEN claims.legal_tin ELSE claims.ind_pinfl END) as customer_inn"),
                'a.task_id as obyekt_raqami',
                'a.created_at as object_created_at',
                'a.id as article_id',
                'claims.building_name as obyekt_nomi',
                'r.name_uz as region_name',
                'r.id as region_id',
                'd.name_uz as district_name',
                'claims.end_date as end_date',
                'claims.status',
                'claims.user_type',
                'claims.building_address',
                'claims.created_at',
                'claims.id',
                'a.difficulty_category_id',
                'a.object_type_id',
                'a.program_id',
                DB::raw("COALESCE(concat_ws(' ', u.name, u.surname)) AS user_name")
            ])
            ->with(['monitoring'])
            ->where('claims.guid', $taskId)
            ->get();

        $claimHistories = DB::table('claim_histories')
            ->whereIn('gu_id', $claims->pluck('ariza_raqami'))
            ->get()
            ->groupBy('gu_id');

        $questionAnswers = CheckListAnswer::whereIn('monitoring_id', $claims->pluck('id'))
            ->where('type', 2)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('monitoring_id');

        $articleIds = $claims->pluck('article_id')->unique();
        $blocks = Block::query()
            ->whereIn('article_id', $articleIds)
            ->get()
            ->groupBy('article_id');

        $array = [];
        $count = 1;

        $statuses = [
            ClaimStatuses::TASK_STATUS_ACCEPTANCE => 'Yangi',
            ClaimStatuses::TASK_STATUS_CONFIRMED => 'Hulosa berildi',
            ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED => 'Tashkilot tomonidan rad etildi',
            ClaimStatuses::TASK_STATUS_DIRECTOR => 'Boshliqda',
            ClaimStatuses::TASK_STATUS_OPERATOR => 'Operatorda',
            ClaimStatuses::TASK_STATUS_REJECTED => 'Inspeksiya tomonidan rad etildi',
            ClaimStatuses::TASK_STATUS_ATTACH_OBJECT => 'Obyekt biriktirish',
            ClaimStatuses::TASK_STATUS_INSPECTOR => 'Inspektorda',
            ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION => 'Tashkilotlarga yuborildi',
            ClaimStatuses::TASK_STATUS_SENT_ANOTHER_ORG => 'Boshqarmaga yuborildi',
            ClaimStatuses::TASK_STATUS_CANCELLED => 'Bekor qilindi'
        ];

        $categories = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV'
        ];

        $roles = [
            0 => '-',
            12 => 'Boshliq',
            11 => 'Operator',
            15 => 'Tashkilot tomonidan',
            16 => 'Tashkilot tomonidan',
            17 => 'Tashkilot tomonidan',
            18 => 'Tashkilot tomonidan',
            19 => 'Tashkilot tomonidan',
            34 => 'Tashkilot tomonidan',
            45 => 'Tashkilot tomonidan',
            46 => 'Tashkilot tomonidan',
        ];

        foreach ($claims as $claim) {
            $history = $claimHistories[$claim->ariza_raqami] ?? collect();
            $questions = $questionAnswers[$claim->id] ?? collect();

            $inspectorConcDate = '-';
            $inspectorSentDate = '-';
            $directorSentDate = '-';
            $historySentOrg = '-';
            $rejectReason = '-';
            $endRoleName = '-';

            $konstruktiv = '-';
            $quyosh_panel = '-';
            $seysmik = '';

            if (!in_array($claim->status, [ClaimStatuses::TASK_STATUS_ACCEPTANCE, ClaimStatuses::TASK_STATUS_ATTACH_OBJECT, ClaimStatuses::TASK_STATUS_SENT_ORGANIZATION])) {
                $historyConc = $history->first(fn($item) => data_get(json_decode($item->content, true), 'role') == 3);

                if (in_array($claim->status, [ClaimStatuses::TASK_STATUS_CONFIRMED])) {
                    $konstruktiv = $questions->where('question_id', 69)->first()?->comment;
                    $quyosh_panel = $questions->where('question_id', 68)->first()?->comment;
                    $seysmik = $questions->where('question_id', 71)->first()?->comment;
                }

                $historyReject = $history->first(fn($item) => in_array(
                    data_get(json_decode($item->content, true), 'status'),
                    [15, 9]
                ));

                $historyEnd = $history->first(fn($item) => in_array(
                    data_get(json_decode($item->content, true), 'status'),
                    [15, 9, 20, 10]
                ));

                $historySentInspector = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 5);
                $historySentOrganization = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 4);

                $historySentDirector = $history->first(fn($item) => data_get(json_decode($item->content, true), 'status') == 13);;

                if ($historyEnd)
                    $endRoleName = $roles[(int)json_decode($historyEnd->content, true)['role'] ?? 0];

                if ($historySentInspector)
                    $inspectorSentDate = $historySentInspector->created_at;

                if ($historySentDirector)
                    $directorSentDate = $historySentDirector->created_at;

                if ($historySentOrganization)
                    $historySentOrg = $historySentOrganization->created_at;

                if ($historyReject)
                    $rejectReason = json_decode($historyReject->content, true)['comment'];

                if ($historyConc)
                    $inspectorConcDate = $historyConc->created_at;
            }

            $lastOrgConcDate = '-';
            if (!in_array($claim->status, [ClaimStatuses::TASK_STATUS_ACCEPTANCE, ClaimStatuses::TASK_STATUS_ATTACH_OBJECT])) {
                $conc = ClaimOrganizationReview::query()->where('claim_id', $claim->id)->orderBy('answered_at', 'desc')->first();

                if ($conc)
                    $lastOrgConcDate = $conc->answered_at;
            }

            $claimBlocks = $blocks[$claim->article_id] ?? collect();

            $blockCounts = [
                'noturar' => $claimBlocks->where('block_type_id', 1)->count(),
                'turar' => $claimBlocks->whereNotIn('block_type_id', [1, 25])->count(),
                'yakka' => $claimBlocks->where('block_type_id', 25)->count(),
            ];

            $meta = [];
            $countTurar = 0;
            $countNoturar = 0;
            $countYakka = 0;

            $blocksData = ($claim->monitoring != null) ? json_decode($claim->monitoring->blocks, true) : null;
            if (is_array($blocksData)) {
                foreach ($blocksData as $item) {
                    $block = $claimBlocks->where('id', $item)->first();
                    if (!$block) continue;

                    if ($block->block_type_id == 1) $countTurar++;
                    elseif ($block->block_type_id == 25) $countYakka++;
                    else $countNoturar++;

                    $meta[] = [
                        'type' => optional($block->type)->name,
                        'count_apartments' => $block->count_apartments,
                    ];
                }
            }

            $areas = null;

            if ($claim->monitoring != null && $claim->monitoring->operator_answer != null) {
                $operator = base64_decode($claim->monitoring->operator_answer);
                $uncompressed = @gzuncompress($operator) ?: $operator;
                $areas = json_decode($uncompressed, true);
            }

            $areaSum = $total_area = $total_use_area = $living_area = [
                'total' => 0, 'turar' => 0, 'noturar' => 0, 'yakka' => 0
            ];


            if ($areas)
                foreach ($areas as $area) {
                    if (!in_array($area['type'], ['10','11','12','13','14', '15'])){
                        $type = match ($area['type']) {
                            '1' => 'turar',
                            '0' => 'noturar',
                            default => 'yakka',
                        };

                        $areaValue = (float) str_replace([' ', ','], ['', '.'], $area['area'] ?? 0);
                        $totalAreaValue = (float) str_replace([' ', ','], ['', '.'], $area['total_area'] ?? 0);
                        $totalUseAreaValue = (float) str_replace([' ', ','], ['', '.'], $area['total_use_area'] ?? 0);
                        $livingAreaValue = (float) str_replace([' ', ','], ['', '.'], $area['living_area'] ?? 0);

                        $areaSum[$type] += $areaValue;
                        $total_area[$type] += $totalAreaValue;
                        $total_use_area[$type] += $totalUseAreaValue;
                        $living_area[$type] += $livingAreaValue;

                        $areaSum['total'] += $areaValue;
                        $total_area['total'] += $totalAreaValue;
                        $total_use_area['total'] += $totalUseAreaValue;
                        $living_area['total'] += $livingAreaValue;
                    }

                }

            $tmpArray = [
                'ariza_raqami' => $claim->ariza_raqami,
                'obyekt_nomi' => $claim->obyekt_nomi,
                'obyekt_raqami' => $claim->obyekt_raqami,
                'region_name' => $claim->region_name,
                'region_id' => $claim->region_id,
                'district_name' => $claim->district_name,
                'cadaster' => $claim->cadaster,
                'customer' => $claim->customer,
                'customer_inn' => $claim->customer_inn,
                'jami_honadon' => (string)$claimBlocks->sum('count_apartments'),
                'jami_block' => (string)$claimBlocks->count(),
                'noturar' => (string)$blockCounts['noturar'],
                'turar' => (string)$blockCounts['turar'],
                'yakka' => (string)$blockCounts['yakka'],
                'count_apartments' => (string)array_sum(array_column($meta, 'count_apartments')),
                'priyomka_jami_block' => ($blocksData != null) ? (string)count($blocksData) : '-',
                'priyomka_noturar' => (string)$countNoturar,
                'priyomka_turar' => (string)$countTurar,
                'priyomka_yakka' => (string)$countYakka,

                'umumiy_maydon' => (string)$total_area['total'],
                'umumiy_noturar' => (string)$total_area['noturar'],
                'umumiy_turar' => (string)$total_area['turar'],
                'umumiy_yakka' => (string)$total_area['yakka'],

                'foydalanish_maydon' => (string)$total_use_area['total'],
                'foydalanish_noturar' => (string)$total_use_area['noturar'],
                'foydalanish_turar' => (string)$total_use_area['turar'],
                'foydalanish_yakka' => (string)$total_use_area['yakka'],

                'yashash_maydon' => (string)$living_area['total'],
                'yashash_noturar' => (string)$living_area['noturar'],
                'yashash_turar' => (string)$living_area['turar'],
                'yashash_yakka' => (string)$living_area['yakka'],

                'qurilish_osti_maydoni' => (string)$areaSum['total'],
                'qurilish_osti_noturar' => (string)$areaSum['noturar'],
                'qurilish_osti_turar' => (string)$areaSum['turar'],
                'qurilish_osti_yakka' => (string)$areaSum['yakka'],

                'inskepsiya_kelgan_vaqt' => $claim->created_at,
                'tashkilotga_yuborilgan_sana' => $historySentOrg,
                'oxirgi_tashkilot_sanasi' => $lastOrgConcDate,
                'inspektor_hulosa_vaqti' => $inspectorConcDate,
                'inspektor' => $claim->user_name,
                'yuridik_jismoniy' => ($claim->user_type == 'J') ? 'Yuridik' : 'Jismoniy',
                'manzil' => $claim->building_address,
                'obyekt_turi' => ($claim->object_type_id == 1) ? 'Tarmoqli' : 'Bino',
                'toifasi' => ($claim->difficulty_category_id != null) ? $categories[$claim->difficulty_category_id] : '-',
                'obyekt_created_at' => $claim->object_created_at,
                'inspektorga_yuborilgan_vaqt' => $inspectorSentDate,
                'direktorga_yuborilgan_vaqt' => $directorSentDate,
                'tugagan_sana' => $claim->end_date,
                'status' => $statuses[$claim->status],
                'end_role_name' => $endRoleName,
                'reject_reason' => $rejectReason,
                'dastur_nomi' => '-',
                'quyosh_panel' => $quyosh_panel,
                'konstruktiv_checklist' => $konstruktiv,
                'seysmik_checklist' => $seysmik
            ];

            $array[] = $tmpArray;
            $count++;
        }
        return $array;
    }
}
