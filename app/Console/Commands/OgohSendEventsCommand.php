<?php

namespace App\Console\Commands;

use App\Models\OgohAppealEvent;
use App\Services\OgohFuqaroService;
use Illuminate\Console\Command;

class OgohSendEventsCommand extends Command
{
    protected $signature = 'ogoh:send-events {--limit=50} {--retry-failed : FAILED bo\'lganlarni qayta navbatga qo\'yish}';

    protected $description = "Ogoh fuqaroga yuborilmagan javoblarni (callback) yuborish";

    public function handle(OgohFuqaroService $service)
    {
        if ($this->option('retry-failed')) {
            $count = OgohAppealEvent::query()
                ->where('send_status', OgohAppealEvent::SEND_FAILED)
                ->update(['send_status' => OgohAppealEvent::SEND_PENDING, 'attempts' => 0, 'next_attempt_at' => null]);
            $this->info("Qayta navbatga qo'yildi: $count");
        }

        $stats = $service->sendPending((int)$this->option('limit'));
        $this->info("Yuborildi: {$stats['sent']}, xato: {$stats['failed']}");
    }
}
