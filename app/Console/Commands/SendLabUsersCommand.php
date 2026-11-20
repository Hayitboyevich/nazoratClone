<?php

namespace App\Console\Commands;

use App\Enums\UserRoleEnum;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendLabUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-lab-users-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::query()
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->leftJoin('regions', 'users.region_id', '=', 'regions.id')
            ->leftJoin('districts', 'users.district_id', '=', 'districts.id')
            ->where('user_roles.role_id', UserRoleEnum::REG_LAB->value)
            ->select([
                'users.pinfl as jshshir',
                'users.pinfl as inn',
                'users.name as name',
                DB::raw("CONCAT(users.surname, ' ', users.name, ' ', users.middle_name) as fullname"),
                'users.phone as phone',
                'regions.soato as region_id',
                'districts.soato as district_id',
                'users.active as active'
            ])->get();
        $base_url = config('app.xulosalar.url');
        $login = config('app.xulosalar.user_name');
        $password = config('app.xulosalar.password');
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($login . ':' . $password),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
        $client = new Client();
        try {
            $request = $client->request('POST', $base_url.'/api/users/push', [
                'headers' => $headers,
                'timeout' => 10,
                'connect_timeout' => 5,
                'json' => [
                    'users' => $users->toArray(),
                ]
            ]);
            $response = $request->getBody()->getContents();
            logger($response);
        } catch (GuzzleException $e) {
            Log::error('Error sending lab users to Xulosalar: ' . $e->getMessage());
        }
    }
}
