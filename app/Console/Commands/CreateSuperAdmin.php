<?php

namespace App\Console\Commands;

use App\Enums\UserStatusEnum;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{

    protected $signature = 'app:admin';


    protected $description = 'Command description';


    public function handle()
    {
        User::query()->create([
            'login' => 'shaffof',
            'password' => '$h@ffof2024',
            'user_status_id' => UserStatusEnum::ACTIVE,
        ]);
    }
}
