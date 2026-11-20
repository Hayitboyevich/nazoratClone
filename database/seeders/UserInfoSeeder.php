<?php

namespace Database\Seeders;

use App\Models\UserInfo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UserInfoSeeder extends Seeder
{
const SPECIAL = [
    1 => 'Loyiha bosh arxitektori',
    2 => 'Loyiha bosh muhandisi',
    3 => 'Qurilish uchastka rahbari',
    4 => 'Qurilish-pudrat tashkiloti bosh muhandisi',
    5 => 'Qurilish-pudrat tashkiloti rahbari',
];
    public function run(): void
    {
//        $usersPath = storage_path() . "/json/users.json";
//        $users = json_decode(file_get_contents($usersPath), true);
//        foreach ($users as $user) {
//            $fioParts = preg_split('/\s+/', trim($user['fio']));
//
//            $surname = $fioParts[0] ?? null;
//            $name = $fioParts[1] ?? null;
//            $middleName = count($fioParts) > 2
//                ? implode(' ', array_slice($fioParts, 2))
//                : null;
//            DB::table('user_infos')->insert([
//                'name' => $name,
//                'surname' => $surname,
//                'middle_name' => $middleName,
//                'pin' => $user['pinfl'],
//                'company_inn' => $user['inn'],
//                'company_name' => $user['name'],
//                'position' => $user['position_name'],
//                'specialist_id' => $user['specialists_id'],
//                'specialist_name' => self::SPECIAL[$user['specialists_id']],
//            ]);
//        }

        $infos = UserInfo::query()
            ->whereNull('mehnat_integration')
            ->limit(500)
            ->get();
        $total = $infos->count();
        $bar = $this->command->getOutput()->createProgressBar($total);
        $bar->start();

        foreach ($infos as $info) {
            $response = Http::timeout(10)->withBasicAuth('dev@gasn', 'EkN`9?@{3v0j')
                ->post(
                    'https://api.shaffofqurilish.uz/api/v1/request/labour-service-citizenhistory',
                    ['pin' => $info->pin]
                );

            if (!$response->successful()) {
                $info->update(['mehnat_integration' => false]);
                $bar->advance();
                continue;
            }

            $data = $response->json();
            $experiences = $data['result']['data']['experiences'] ?? [];

            if (empty($experiences)) {
                $info->update(['mehnat_integration' => false]);
                $bar->advance();
                continue;
            }

            $first = reset($experiences);
            $info->update([
                'mehnat_integration' => true,
                'start_date' => $first['start_date'] ?? null,
            ]);
            $bar->advance();
            sleep(1);
        }
        $bar->finish();
        $this->command->info("\nSeeder tugadi!");

    }
}
