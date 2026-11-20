<?php

namespace App\Http\Controllers\Api;


use App\Enums\ObjectStatusEnum;
use App\Enums\UserHistoryStatusEnum;
use App\Enums\UserHistoryTypeEnum;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Requests\PinflRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserHistoryResource;
use App\Http\Resources\UserInfoResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserResourceCollection;
use App\Http\Resources\UserStatusResource;
use App\Models\Article;
use App\Models\ArticleUser;
use App\Models\InspectorLocation;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmployee;
use App\Models\UserHistory;
use App\Models\UserInfo;
use App\Models\UserRole;
use App\Models\UserStatus;
use App\Notifications\InspectorNotification;
use App\Services\HistoryService;
use App\Services\MessageTemplate;
use App\Services\UserService;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class UserController extends BaseController
{
    private HistoryService $historyService;

    public function __construct(
        protected UserService $service,
    )
    {
        $this->middleware('auth');
        parent::__construct();
        $this->historyService = new HistoryService('user_histories');
    }

    public function users(): JsonResponse
    {
        $type = request('type', null);
        $query = $this->service->getAllUsers($this->user, $this->roleId, $type);
        $filters = request()->only(['name', 'region_id', 'district_id', 'status', 'role_id', 'search']);
        $users = $this->service->searchByUser($query, $filters)->paginate(request('per_page', 10));
        return $this->sendSuccess(new UserResourceCollection($users), 'All Users', pagination($users));
    }

    public function delete(): JsonResponse
    {
        try {
            $user = User::query()->findOrFail(request('id'));
            $objects = $user->objects()->whereNotIn('object_status_id',  [ObjectStatusEnum::SUBMITTED, ObjectStatusEnum::CANCELED]);

            if ($objects->count() > 0)  throw new \Exception('Userda faol obyektlari mavjud');

            $user->update([
                'active' => 0,
                'user_status_id' => UserStatusEnum::RELEASED,
                'phone' => null,
                'deleted_at' => now(),
                'deleted_by' => $this->user->id,
            ]);
            return $this->sendSuccess(null, 'User has been deleted');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function inspectorLocation(): JsonResponse
    {
        try {
            $lat = request('lat');
            $lng = request('long');
            $location = InspectorLocation::query()->create([
                'lat' => $lat,
                'long' => $lng,
                'inspector_id' => $this->user->id,
            ]);
            return $this->sendSuccess($location, 'Location has been created');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function inspectorStartWork()
    {
        try {
            $response = Http::retry(0, 0)
                ->timeout(5)
                ->withHeaders([
                    'accept' => 'application/json',
                    'Authorization' => 'Token a0b3aad960b54f2a5b9a4b4dde2aeee6268ec53b',
                ])
                ->post('https://api.ainazorat.uz/api/v1/facilities/conclutions/shaffof/', ['inspector' => $this->user->id, 'facility' => request('task_id')]);
            if ($response->successful()) {
                return $this->sendSuccess([], 'User has been created');
            }
            return $this->sendError('Xatolik aniqlandi', 500);
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function getInspectorLocation($userId): JsonResponse
    {
        try {
            $user = $this->service->getById($userId);
            if (!$user) {
                return $this->sendError('Foydalanuvchi topilmadi');
            }

            $location = $user->locations()->latest()->first();

            return $this->sendSuccess($location, 'Location');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function getUserInfo()
    {
        try {
            $filters = request()->only(['pin', 'inn', 'type']);
            $infos = UserInfo::query()
                ->when(!empty($filters['fish']), function ($query, $fish) {
                    $query->where('name', 'like', '%' . $fish . '%');
                })
                ->when(!empty($filters['pin']), function ($query, $pin) {
                    $query->where('pin', $pin);
                })
                ->when(!empty($filters['inn']), function ($query, $inn) {
                    $query->where('company_inn', $inn);
                })
                ->when($filters['type'], function ($query, $type) {
                    if ($type == 1) { // loyiha
                        $query->whereIn('specialist_id', [1, 2, 9]);
                    }

                    if ($type == 2) {
                        $query->whereIn('specialist_id', [3, 4, 5, 10]);
                    }
                    if ($type == 3) {
                        $query->whereIn('specialist_id', [13]);
                    }

                    if ($type == 4) {
                        $query->whereIn('specialist_id', [8]);
                    }
                })
                ->paginate(request('per_page', 10));
            return $this->sendSuccess(UserInfoResource::collection($infos), 'User Infos', pagination($infos));
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function technicStatistic($id)
    {
        try {
            $client = new Client();

            $response = $client->post(
                'https://e-inspektor.fvv.uz/api/v1/integration/shaffof-qurilish/objects',
                [
                    'headers' => [
                        'X-Api-Key' => '3289055b9409a62a323c068df8062c74db69e11f901c18c5b5acea386bf9f9de',
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'objectIds' => [$id],
                    ],
                    'timeout' => 30,
                    'connect_timeout' => 10,
                ]
            );
            dd($response->getBody());

            return json_decode($response->getBody()->getContents(), true) ?? [];

//            $user = $this->service->getById($id);
//
//            $statistics = DB::table('articles')
//                ->join('article_users', 'articles.id', '=', 'article_users.article_id')
//                ->where('article_users.user_id', $user->id)
//                ->where('article_users.role_id', UserRoleEnum::TEXNIK->value)
//                ->whereNull('articles.deleted_at')
//                ->select('articles.object_status_id')
//                ->selectRaw('COUNT(DISTINCT articles.id) as total')
//                ->groupBy('articles.object_status_id')
//                ->get();
//
//            $regulations = Regulation::query()
//                ->where('role_id', UserRoleEnum::TEXNIK->value)
//                ->where('user_id', $id);
//
//            $regStatistic = [
//                'all' => (clone $regulations)->count(),
//                'fine' => (clone $regulations)
//                    ->whereNotNull('lawyer_status_id')
//                    ->count(),
//            ];
//
//            $data = [
//                'objects' => $statistics,
//                'regulations' => $regStatistic,
//            ];
//
//
//            return $this->sendSuccess($data, 'Statistika');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function technics()
    {
        try {
            $users = User::query()
                ->select('users.id', 'users.name')
                ->whereHas('roles', function ($query) {
                    $query->where('role_id', UserRoleEnum::TEXNIK->value);
                })
                ->paginate(request('per_page', 30));

            $userIds = $users->pluck('id');

            $objectStatistics = DB::table('articles')
                ->join('article_users', 'articles.id', '=', 'article_users.article_id')
                ->whereIn('article_users.user_id', $userIds)
                ->where('article_users.role_id', UserRoleEnum::TEXNIK->value)
                ->whereNull('articles.deleted_at')
                ->select('article_users.user_id', 'articles.object_status_id')
                ->selectRaw('COUNT(DISTINCT articles.id) as total')
                ->groupBy('article_users.user_id', 'articles.object_status_id')
                ->get()
                ->groupBy('user_id');

            $regulationStatistics = Regulation::query()
                ->whereIn('user_id', $userIds)
                ->where('role_id', UserRoleEnum::TEXNIK->value)
                ->select('user_id')
                ->selectRaw('COUNT(*) as all_count')
                ->selectRaw('COUNT(lawyer_status_id) as fine_count')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $users->getCollection()->transform(function ($user) use (
                $objectStatistics,
                $regulationStatistics
            ) {
                $regulations = $regulationStatistics->get($user->id);

                $user->objects = $objectStatistics
                    ->get($user->id, collect())
                    ->map(function ($item) {
                        return [
                            'object_status_id' => $item->object_status_id,
                            'total' => (int) $item->total,
                        ];
                    })
                    ->values();

                $user->regulations = [
                    'all' => (int) ($regulations->all_count ?? 0),
                    'fine' => (int) ($regulations->fine_count ?? 0),
                ];

                return $user;
            });

            return $this->sendSuccess($users, 'Users');

        } catch (\Exception $exception) {
            return $this->sendError(
                $exception->getMessage(),
                $exception->getCode()
            );
        }
    }

    public function getUserExpertise()
    {
        try {
            $url = 'https://lic.mc.uz/api/expertise-license';
            $response = Http::withBasicAuth('apiuser', 'gGOP875c05')->get($url, request()->query());

            return  $this->sendSuccess($response->json()['data'], 'Expertise');
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function getUserGovExpertise()
    {
        try {
            $url = 'https://api.shaffofqurilish.uz/api/v1/request/ekspertiza-intg-rts-out-experts';

            $client = new Client();
            $apiCredentials = config('app.passport.login') . ':' . config('app.passport.password');
            $headers = [
                'Authorization' => 'Basic ' . base64_encode($apiCredentials),
            ];


            $response = $client->post($url, [
                'headers' => $headers,
                'verify' => false
            ]);

           return  $response->getBody()->getContents();
        }catch (\Exception $exception){
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function count(): JsonResponse
    {
        try {
            $type = request('type', null);
            return $this->sendSuccess($this->service->getCountByUsers($this->user, $this->roleId, $type), 'All users count');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function userChange(): JsonResponse
    {
        try {
            $data = request()->all();
            if ($data['old_user_id'] == $data['new_user_id']) throw new \Exception('Bu foydalanuvchi biriktirilgan');

            $meta = [
                'old_user_id' => $data['old_user_id'],
                'old_role_id' => $data['old_role_id'],
                'new_user_id' => $data['new_user_id'],
                'new_role_id' => $data['new_role_id'],
                'object_id' => $data['object_id'],
            ];

            if ($data['is_inspector']) {
                ArticleUser::query()->where('article_id', $data['object_id'])
                    ->where('role_id', UserRoleEnum::INSPECTOR->value)
                    ->update(['user_id' => $data['new_user_id']]);

                 Regulation::query()
                     ->where('object_id', $data['object_id'])
                     ->where('user_id', $data['old_user_id'])
                     ->where('role_id', UserRoleEnum::INSPECTOR->value)
                    ->update([
                        'user_id' => $data['new_user_id'],
                        'role_id' => $data['new_role_id'],
                    ]);

                Regulation::query()
                    ->where('object_id', $data['object_id'])
                    ->where('created_by_user_id', $data['old_user_id'])
                    ->where('created_by_role_id', UserRoleEnum::INSPECTOR->value)
                    ->update([
                        'created_by_user_id' => $data['new_user_id'],
                        'created_by_role_id' => $data['new_role_id'],
                    ]);

                $this->sendNotification($this->user, $data['new_user_id'], $this->roleId, $data['object_id']);

            } else {
                $meta['inspector_id'] = $data['inspector_id'] ?? null;
                $this->historyService->createHistory(
                    guId: $data['object_id'],
                    status: UserHistoryStatusEnum::ASKED->value,
                    type: UserHistoryTypeEnum::CHANGE->value,
                    date: null,
                    comment: $item['comment'] ?? "",
                    additionalInfo: $meta
                );

                $this->sendNotificationAuthor($this->user, $this->roleId, $data['new_role_id'], $data['object_id']);
            }

            return $this->sendSuccess([], 'Send Successfully');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function userChangeList(): JsonResponse
    {
        try {
            $userHistories = UserHistory::query()
                ->where('type', UserHistoryTypeEnum::CHANGE->value)
                ->where('content->additionalInfo->inspector_id', auth()->id())
                ->paginate(request('per_page', 10));
            return $this->sendSuccess(UserHistoryResource::collection($userHistories), 'All User History', pagination($userHistories));
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function acceptUserChange(): JsonResponse
    {
        DB::beginTransaction();
        try {
            $userHistory = UserHistory::query()->findOrFail(request('id'));

            $userHistory->update([
                'content->status' => UserHistoryStatusEnum::ACCEPTED->value,
                'content->comment' => request('comment') ?? 'Qabul qilindi',
            ]);

            $objectId = $userHistory->content->additionalInfo->object_id;
            $oldUserId = $userHistory->content->additionalInfo->old_user_id;
            $oldRoleId = $userHistory->content->additionalInfo->old_role_id;
            $newUserId = $userHistory->content->additionalInfo->new_user_id;
            $newRoleId = $userHistory->content->additionalInfo->new_role_id;


            ArticleUser::query()->where('article_id', $objectId)
                ->where('role_id', $oldRoleId)
                ->where('user_id', $oldUserId)
                ->update(['user_id' => $newUserId]);

            Regulation::query()
                ->where('object_id', $objectId)
                ->where('user_id', $oldUserId)
                ->where('role_id', $oldRoleId)
                ->update([
                    'user_id' => $newUserId,
                    'role_id' => $newRoleId,
                ]);

            Regulation::query()
                ->where('object_id', $objectId)
                ->where('created_by_user_id', $oldUserId)
                ->where('created_by_role_id', $oldRoleId)
                ->update([
                    'created_by_user_id' => $newUserId,
                    'created_by_role_id' => $newRoleId,
                ]);
            DB::commit();
            return $this->sendSuccess([], 'Success');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function rejectUserChange(): JsonResponse
    {
        try {
            $userHistory = UserHistory::query()->findOrFail(request('id'));
            $userHistory->update([
                'content->status' => UserHistoryStatusEnum::REJECTED->value,
                'content->comment' => request('comment') ?? 'Rad etildi',
            ]);
            return $this->sendSuccess([], 'Success');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function create(UserRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $existingUser = $this->findUserByPinfl($request->pinfl);

            if ($existingUser) {
                $roles = UserRole::query()
                    ->where('user_id', $existingUser->id)
                    ->pluck('role_id')
                    ->toArray();

                $allowed = count($roles) === 1 && $roles[0] == 8;

                if ($existingUser->user_status_id != UserStatusEnum::RELEASED && !$allowed) {
                    throw new \Exception('Bu foydalanuvchi oldin qo‘shilgan.');
                }

                $this->updateExistingUser($existingUser, $request);
                $this->updateUserRoles($existingUser, $request->role_ids);
                $this->saveFiles($existingUser, $request);

                DB::commit();
                return $this->sendSuccess(new UserResource($existingUser), 'Foydalanuvchi tahrir qilindi.');
            }

            $user = $this->createNewUser($request);
            $this->updateUserRoles($user, $request->role_ids);
            $this->saveFiles($user, $request);

            DB::commit();
            return $this->sendSuccess(new UserResource($user), 'Foydalanuvchi muvaffaqiyatli yaratildi.');

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }


    private function saveFiles(User $user, UserRequest $request)
    {
        if ($request->hasFile('files')) {
            $user->documents()->delete();
            foreach ($request->file('files') as $file) {
                $path = $file->store('user/docs', 'public');
                $user->documents()->create(['url' => $path]);
            }
        }
    }
    private function findUserByPinfl($pinfl): ?User
    {
        return User::query()->where('pinfl', $pinfl)->first();
    }

    private function updateExistingUser(User $user, UserRequest $request): void
    {
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->surname = $request->surname;
        $user->middle_name = $request->middle_name;
        $user->region_id = $request->region_id;
        $user->district_id = $request->district_id;
        $user->active = 1;
        $user->login = $request->phone;
        $user->password = Hash::make($request->phone);
        $user->user_status_id = UserStatusEnum::ACTIVE;
        $user->type = $request->type;
        $user->created_by = $request->created_by;
        $user->image = $this->saveImage($request);

        $user->save();
        if ($this->roleId == UserRoleEnum::BUYURTMACHI->value || $this->roleId == UserRoleEnum::LOYIHA->value || $this->roleId == UserRoleEnum::QURILISH->value) {
            UserEmployee::query()->create([
                'parent_id' => $this->user->id,
                'user_id' => $user->id,
            ]);
        }
    }

    private function createNewUser(UserRequest $request): User
    {
        $user = new User();
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->pinfl = $request->pinfl;
        $user->password = Hash::make($request->phone);
        $user->login = $request->phone;
        $user->user_status_id = $request->user_status_id;
        $user->surname = $request->surname;
        $user->middle_name = $request->middle_name;
        $user->region_id = $request->region_id;
        $user->district_id = $request->district_id;
        $user->created_by = $request->created_by;
        $user->type = $request->type;

        $user->image = $this->saveImage($request);

        $user->save();

        if ($this->roleId == UserRoleEnum::BUYURTMACHI->value || $this->roleId == UserRoleEnum::LOYIHA->value || $this->roleId == UserRoleEnum::QURILISH->value) {
            UserEmployee::query()->create([
                'parent_id' => $this->user->id,
                'user_id' => $user->id,
            ]);
        }


        return $user;
    }

    private function updateUserRoles(User $user, array $roleIds): void
    {
        $existingRoles = UserRole::query()
            ->where('user_id', $user->id)
            ->pluck('role_id')
            ->toArray();

        $rolesToAdd = array_diff($roleIds, $existingRoles);

        $rolesToRemove = array_diff($existingRoles, $roleIds);

        if (!empty($rolesToRemove)) {
            UserRole::query()
                ->where('user_id', $user->id)
                ->whereIn('role_id', $rolesToRemove)
                ->delete();
        }

        foreach ($rolesToAdd as $role_id) {
            UserRole::query()->create([
                'user_id' => $user->id,
                'role_id' => $role_id
            ]);
        }
    }

    private function saveImage(UserRequest $request): ?string
    {
        if ($request->hasFile('image')) {
            return $request->file('image')->store('user', 'public');
        }

        return null;
    }




    public function getInspector(): JsonResponse
    {
        try {
            $inspectors = User::query()
                ->when(request('region_id'), function ($query, $region_id) {
                    $query->where('region_id', $region_id);
                })
                ->when(request('district_id'), function ($query, $district_id) {
                    $query->where('district_id', $district_id);
                })
                ->whereHas('roles', function ($query) {
                    $query->where('roles.id', UserRoleEnum::INSPECTOR->value);
                })
                ->when(request('name'), function ($query){
                    $query->searchByFullName(request('name'));
                })
                ->paginate(request('per_page', 10));
            return $this->sendSuccess(UserResource::collection($inspectors), 'All inspectors', pagination($inspectors));
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function edit(): JsonResponse
    {

    }

    public function getPassportInfo(PinflRequest $request): JsonResponse
    {
        try {
            $data = $this->service->getInfo($request->pinfl, $request->birth_date);
            $meta = [
                'pinfl' => $data['current_pinpp'],
                'name' => $data['namelat'],
                'surname' => $data['surnamelat'],
                'middle_name' => $data['patronymlat'],
                'image' => $data['photo'],
                'passport_number' => $data['current_document']
            ];
            return $this->sendSuccess($meta, 'Passport Information Get Successfully');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function getEmployees(): JsonResponse
    {
        try {
            $user = Auth::user();
            return $this->sendSuccess(UserResource::collection($user->employees), 'All Employees');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    private function sendNotification($user, $inspectorId, $roleId, $objectId)
    {
        try {
            $role = Role::query()->find($roleId);
            $inspector = User::query()->find($inspectorId);
            $object = Article::query()->find($objectId);
            $message = MessageTemplate::createdObject($user->full_name, $object->task_id, $role->name, now());
            $data = [
                'screen' => 'objects'
            ];
            $inspector->notify(new InspectorNotification(title: "Yangi obyekt biriktirildi", message: $message, url: null, additionalInfo: $data));

        } catch (\Exception $exception) {

        }

    }
    private function sendNotificationAuthor($user, $roleId, $newRoleId, $objectId)
    {
        try {
            $role = Role::query()->find($roleId);
            $newRole = Role::query()->find($newRoleId);
            $object = Article::query()->find($objectId);
            $inspector = $object->users()->where('role_id', UserRoleEnum::INSPECTOR->value)->first();
            $message = MessageTemplate::changeUserInObject($user->full_name, $newRole->name, $object->task_id, $role->name, now());
            $data = [
                'screen' => 'employee'
            ];
            $inspector->notify(new InspectorNotification(title: "Xodim o'zgartirilmoqda", message: $message, url: null, additionalInfo: $data));

        } catch (\Exception $exception) {

        }

    }

    public function checkCertificate()
    {
        try {
            $data = $this->service->getCertificate($this->user, $this->roleId);

            if (!$data) return $this->sendError("Sertifikat topilmadi");

            return $this->sendSuccess($data, 'Sertifikat topilmadi');

        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }


    public function status(): JsonResponse
    {
        return $this->sendSuccess(UserStatusResource::collection(UserStatus::all()), 'All User status');
    }
}
