<?php

namespace App\Console\Commands;

use App\Models\Monitoring;
use Illuminate\Console\Command;

class MonitoringUpdate extends Command
{

    protected $signature = 'app:monitoring-update-list';


    protected $description = 'Command description';


    public function handle()
    {
        Monitoring::query()
            ->whereHas('checklists', function ($query) {
                $query->whereIn('question_id', [64, 65, 73]);
            })
            ->with(['checklists' => function ($query) {
                $query->whereIn('question_id', [64, 65, 73]);
            }])
            ->whereNull('constant_checklist')
            ->chunk(100, function ($monitorings) {
                foreach ($monitorings as $monitoring) {
                    $meta = [];
                    foreach ($monitoring->checklists as $checklist) {
                        $meta[$checklist->question_id] = $checklist->status;
                    }
                    $monitoring->update([
                        'constant_checklist' => json_encode($meta),
                    ]);
                }
            });
    }
}
