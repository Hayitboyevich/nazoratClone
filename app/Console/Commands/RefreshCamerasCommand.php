<?php

namespace App\Console\Commands;

use App\Services\CameraService;
use Illuminate\Console\Command;

class RefreshCamerasCommand extends Command
{

    protected $signature = 'refresh:camera';


    protected $description = 'Refresh cameras cache in Redis';

    public function handle(CameraService $cameraService): int
    {
        try {

            $count = $cameraService->setData();
            $this->info("Successfully refreshed {$count} cameras.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            report($e);

            return self::FAILURE;
        }
    }
}
