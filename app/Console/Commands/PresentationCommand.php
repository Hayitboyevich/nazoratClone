<?php

namespace App\Console\Commands;

use App\Enums\LogType;
use App\Enums\UserRoleEnum;
use App\Helpers\PresentationStatus;
use App\Models\Presentation;
use App\Services\HistoryService;
use Illuminate\Console\Command;

class PresentationCommand extends Command
{
    private HistoryService $history;

    protected $signature = 'app:presentation';


    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
        $this->history = new HistoryService('presentation_histories');
    }


    public function handle()
    {
        $presentations = Presentation::query()
            ->where('status', PresentationStatus::CONFIRM)
            ->where(function($query) {
                $query->where('technic_answer', 0)
                    ->orWhere('internal_answer', 0);
            })
            ->where('created_at', '<=', now()->subDays(2))
            ->limit(10)
            ->get();

        foreach ($presentations as $presentation) {
            try {
                $updates = [];
                $saveHistoryActions = [];

                if (!$presentation->technic_answer) {
                    $updates['technic_answer'] = 1;
                    $saveHistoryActions[] = LogType::PRESENTATION_UPDATE_TECHNIC;
                }

                if (!$presentation->internal_answer) {
                    $updates['internal_answer'] = 1;
                    $saveHistoryActions[] = LogType::PRESENTATION_UPDATE_INTERNAL;
                }

                $technic_answer = $presentation->technic_answer ?? 0;
                $internal_answer = $presentation->internal_answer ?? 0;

                if (array_key_exists('technic_answer', $updates)) {
                    $technic_answer = 1;
                }
                if (array_key_exists('internal_answer', $updates)) {
                    $internal_answer = 1;
                }

                if ($technic_answer == 1 && $internal_answer == 1) {
                    $updates['status'] = PresentationStatus::IN_EMPLOYEE;
                } elseif (
                    in_array($technic_answer, [1, 2], true) &&
                    in_array($internal_answer, [1, 2], true)
                ) {
                    $updates['status'] = PresentationStatus::IN_INSPECTOR;
                }

                if (!empty($updates)) {
                    $presentation->update($updates);

                    foreach ($saveHistoryActions as $action) {
                        if ($action == LogType::PRESENTATION_UPDATE_TECHNIC) {
                            $this->saveHistory($presentation, UserRoleEnum::TEXNIK->value, $action);
                        }else{
                            $this->saveHistory($presentation, UserRoleEnum::ICHKI->value, $action);
                        }
                    }
                }
            } catch (\Exception $exception) {
                telegramNotification(
                    'Taqdimnoma inspeksiyaga yuborishda xatolik',
                    $presentation->number,
                    $exception->getMessage()
                );
            }
        }
    }

    private function saveHistory($model, $roleId, $action)
    {
        return $this->history->createHistory(
            guId: $model->id,
            status: $model->status,
            type: $action,
            date: null,
            comment: 'Avtomatik tasdiqlandi',
            additionalInfo: [
                'role_id' => $roleId, 'status' => 1, 'answered' => 'auto'
            ]
        );
    }

}
