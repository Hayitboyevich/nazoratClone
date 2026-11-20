<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HistoryCommand extends Command
{

    protected $signature = 'app:history-command';

    protected $description = 'Command description';

    public function handle()
    {
        try{

        }catch (\Exception $exception){
            $this->error($exception->getMessage());
        }
    }
}
