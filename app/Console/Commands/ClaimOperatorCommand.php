<?php

namespace App\Console\Commands;

use App\Enums\LogType;
use App\Models\Claim;
use App\Models\ClaimMonitoring;
use App\Models\ClaimOperator;
use App\Models\Monitoring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ClaimOperatorCommand extends Command
{
    protected $signature = 'app:claim-operator-command';

    protected $description = 'Command description';


    public function handle()
    {
        try {
            $claim = Claim::query()->where('guid', 232093718)->firstOrFail();
            $monitoring = $claim->monitoring;

            $answers = $monitoring->operator_field;
//            dd($answers);

            $answers = array_map(function ($item) {
                unset($item['apartment_count']);
                $item['type'] = 1;
                return $item;
            }, $answers);


            $monitoring->update([
                'operator_answer' => base64_encode(
                    gzcompress(json_encode($answers), 9)
                ),
            ]);

            ClaimOperator::query()->updateOrCreate(
                ['claim_id' => $claim->id],
                ['operator_answer' => json_encode($answers)],
            );

//            $claimHistories =  DB::table('claim_histories')
//                ->where('type', LogType::TASK_HISTORY)
//                ->where("content->status", 5)
//                ->where('integration', false)
//                ->limit(100)->get([
//                    'id',
//                    'gu_id',
//                    'content',
//                    'created_at',
//                    'integration'
//                ]);
//
//
//            foreach ($claimHistories as $claimHistory) {
//                $claim = Claim::query()->where("guid", $claimHistory->gu_id)->first();
//                if ($claim) {
//                    $claim->update([
//                        'expiry_date' => deadline(4, $claimHistory->created_at)
//                    ]);
//
//                }
//
//                DB::table('claim_histories')
//                    ->where('id', $claimHistory->id)
//                    ->update(['integration' => true]);
//
//            }
        }catch (\Exception $exception){
            Log::error($exception->getMessage());
        }

    }

}
