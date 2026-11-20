<?php

namespace App\Console\Commands;

use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Article;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicantCommand extends Command
{

    protected $signature = 'user:applicant';


    protected $description = 'Command description';


    public function handle()
    {
        $articles = Article::query()
            ->whereIn('object_status_id', [ObjectStatusEnum::PROGRESS, ObjectStatusEnum::FROZEN, ObjectStatusEnum::SUSPENDED])
            ->where('applicant', false)
            ->limit(20)
            ->get();
        foreach ($articles as $article) {
            $article->update(['applicant' => true]);
            DB::beginTransaction();
            try {
                if (isset($article->organization_name) && isset($article->org_stir)) {
                    $user = User::query()->where('pinfl', $article->org_stir)->first();
                    if ($user) {
                        if (!$user->roles()->where('role_id', UserRoleEnum::ARIZACHI->value)->exists())
                            UserRole::query()->create([
                                'user_id' => $user->id,
                                'role_id' => UserRoleEnum::ARIZACHI->value,
                            ]);
                        $article->users()->attach($user->id, ['role_id' => UserRoleEnum::ARIZACHI->value]);
                    } else {
                        $user = User::create([
                            'phone' => $article->phone,
                            'login' => $article->org_stir,
                            'organization_name' => $article->organization_name,
                            'password' => bcrypt($article->org_stir),
                            'user_status_id' => UserStatusEnum::ACTIVE,
                            'pinfl' => $article->org_stir,
                            'identification_number' => $article->org_stir,
                        ]);
                        $article->users()->attach($user->id, ['role_id' => UserRoleEnum::ARIZACHI->value]);
                        UserRole::query()->create([
                            'user_id' => $user->id,
                            'role_id' => UserRoleEnum::ARIZACHI->value,
                        ]);
                    }
                    DB::commit();
                }
            } catch (\Exception $exception) {
                DB::rollBack();
                Log::info('Xatolik: task_id= ' . $article->task_id . '    ' . $exception->getMessage());
                echo $exception->getMessage() . ' ' . $article->task_id;
                continue;
            }
            sleep(3);
        }

    }
}
