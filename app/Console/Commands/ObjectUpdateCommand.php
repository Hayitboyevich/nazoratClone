<?php

namespace App\Console\Commands;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Article;
use App\Models\User;
use App\Models\UserRole;
use App\Services\DxaBuildingResponseService;
use App\Services\DxaLinearResponseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ObjectUpdateCommand extends Command
{
   public function __construct(
       protected DxaLinearResponseService $linearService,
       protected DxaBuildingResponseService $buildingService
   )
   {
       parent::__construct();
   }

    protected $signature = 'app:object-update-command';

    protected $description = 'Command description';

    public function handle()
    {
        $query = Article::query()
            ->whereDoesntHave('roles', function ($q) {
                $q->where('roles.id', UserRoleEnum::ARIZACHI->value);
            })
            ->whereNotNull('task_id')
            ->whereNull('is_change')
            ->whereNotNull('object_type_id');

//        $total = (clone $query)->count();

//        $bar = $this->output->createProgressBar($total);
//        $bar->start();
        $articles = $query->limit(50)->get();
        foreach ($articles as $article) {
            try {

                $userData = [];

                if ($article->object_type_id == 1) {

                    $response = $this->linearService->fetchTaskData($article->task_id);
                    $data = $this->linearService->parseResponse($response);

                    if (!empty($data['legal_tin']['real_value'])) {

                        $userData = [
                            'pinfl' => $data['legal_tin']['real_value'],
                            'phone' => $data['legal_phone']['real_value'] ?? null,
                            'organization_name' => $data['legal_name']['real_value'] ?? null,
                        ];

                    } elseif (!empty($data['ind_pinfl']['real_value'])) {

                        $userData = [
                            'pinfl' => $data['ind_pinfl']['real_value'],
                            'phone' => $data['ind_phone']['real_value'] ?? null,
                            'organization_name' => $data['ind_fullname']['real_value'] ?? null,
                        ];
                    }

                } else {

                    $response = $this->buildingService->fetchTaskData($article->task_id);
                    $data = $this->buildingService->parseResponse($response);

                    if (!empty($data['legal_entity_tin']['real_value'])) {

                        $userData = [
                            'pinfl' => $data['legal_entity_tin']['real_value'],
                            'phone' => $data['legal_entity_phone_number']['real_value'] ?? null,
                            'organization_name' => $data['legal_entity_name']['real_value'] ?? null,
                        ];

                    } elseif (!empty($data['ind_pinfl']['real_value'])) {

                        $userData = [
                            'pinfl' => $data['ind_pinfl']['real_value'],
                            'phone' => $data['phone']['real_value'] ?? null,
                            'organization_name' => $data['full_name']['real_value'] ?? null,
                        ];
                    }
                }

                if (empty($userData['pinfl'])) {
                    $article->update(['is_change' => false]);
//                    $bar->advance();
                    continue;
                }

                $user = User::query()->firstOrCreate(
                    ['pinfl' => $userData['pinfl']],
                    [
                        'phone' => $userData['phone'],
                        'login' => $userData['pinfl'],
                        'organization_name' => $userData['organization_name'],
                        'password' => bcrypt($userData['pinfl']),
                        'user_status_id' => UserStatusEnum::ACTIVE,
                    ]
                );

                if (!UserRole::query()
                    ->where('user_id', $user->id)
                    ->where('role_id', UserRoleEnum::ARIZACHI->value)
                    ->exists()) {

                    UserRole::query()->create([
                        'user_id' => $user->id,
                        'role_id' => UserRoleEnum::ARIZACHI->value,
                    ]);
                }

                if (!$article->users()
                    ->where('users.id', $user->id)
                    ->wherePivot('role_id', UserRoleEnum::ARIZACHI->value)
                    ->exists()) {

                    $article->users()->attach($user->id, [
                        'role_id' => UserRoleEnum::ARIZACHI->value,
                    ]);
                }

                $article->update([
                    'is_change' => true,
                ]);
                Log::info('Article ID: ' . $article->id . ' Updated');

            }
            catch (\Throwable $exception) {

                $article->update([
                    'is_change' => false,
                ]);

                Log::error(
                    'Article ID: ' . $article->id .
                    ', Error: ' . $exception->getMessage()
                );
            }
        }


//        $query->orderByDesc('id')
//            ->chunkById(50, function ($articles) use ($bar) {
//
//                foreach ($articles as $article) {
//
//                    try {
//
//                        $userData = [];
//
//                        if ($article->object_type_id == 1) {
//
//                            $response = $this->linearService->fetchTaskData($article->task_id);
//                            $data = $this->linearService->parseResponse($response);
//
//                            if (!empty($data['legal_tin']['real_value'])) {
//
//                                $userData = [
//                                    'pinfl' => $data['legal_tin']['real_value'],
//                                    'phone' => $data['legal_phone']['real_value'] ?? null,
//                                    'organization_name' => $data['legal_name']['real_value'] ?? null,
//                                ];
//
//                            } elseif (!empty($data['ind_pinfl']['real_value'])) {
//
//                                $userData = [
//                                    'pinfl' => $data['ind_pinfl']['real_value'],
//                                    'phone' => $data['ind_phone']['real_value'] ?? null,
//                                    'organization_name' => $data['ind_fullname']['real_value'] ?? null,
//                                ];
//                            }
//
//                        } else {
//
//                            $response = $this->buildingService->fetchTaskData($article->task_id);
//                            $data = $this->buildingService->parseResponse($response);
//
//                            if (!empty($data['legal_entity_tin']['real_value'])) {
//
//                                $userData = [
//                                    'pinfl' => $data['legal_entity_tin']['real_value'],
//                                    'phone' => $data['legal_entity_phone_number']['real_value'] ?? null,
//                                    'organization_name' => $data['legal_entity_name']['real_value'] ?? null,
//                                ];
//
//                            } elseif (!empty($data['ind_pinfl']['real_value'])) {
//
//                                $userData = [
//                                    'pinfl' => $data['ind_pinfl']['real_value'],
//                                    'phone' => $data['phone']['real_value'] ?? null,
//                                    'organization_name' => $data['full_name']['real_value'] ?? null,
//                                ];
//                            }
//                        }
//
//                        if (empty($userData['pinfl'])) {
//                            $article->update(['is_change' => false]);
//                            $bar->advance();
//                            continue;
//                        }
//
//                        $user = User::query()->firstOrCreate(
//                            ['pinfl' => $userData['pinfl']],
//                            [
//                                'phone' => $userData['phone'],
//                                'login' => $userData['pinfl'],
//                                'organization_name' => $userData['organization_name'],
//                                'password' => bcrypt($userData['pinfl']),
//                                'user_status_id' => UserStatusEnum::ACTIVE,
//                            ]
//                        );
//
//                        if (!UserRole::query()
//                            ->where('user_id', $user->id)
//                            ->where('role_id', UserRoleEnum::ARIZACHI->value)
//                            ->exists()) {
//
//                            UserRole::query()->create([
//                                'user_id' => $user->id,
//                                'role_id' => UserRoleEnum::ARIZACHI->value,
//                            ]);
//                        }
//
//                        if (!$article->users()
//                            ->where('users.id', $user->id)
//                            ->wherePivot('role_id', UserRoleEnum::ARIZACHI->value)
//                            ->exists()) {
//
//                            $article->users()->attach($user->id, [
//                                'role_id' => UserRoleEnum::ARIZACHI->value,
//                            ]);
//                        }
//
//                        $article->update([
//                            'is_change' => true,
//                        ]);
//
//                    }
//                    catch (\Throwable $exception) {
//
//                        $article->update([
//                            'is_change' => false,
//                        ]);
//
//                        Log::error(
//                            'Article ID: ' . $article->id .
//                            ', Error: ' . $exception->getMessage()
//                        );
//                    }
//
//                    $bar->advance();
//                }
//            });
//
//        $bar->finish();
//
//        $this->info("\nTugadi ✅");
    }

}
