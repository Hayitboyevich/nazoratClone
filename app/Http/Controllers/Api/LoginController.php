<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Resources\DistrictResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserStatusResource;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\EimzoService;
use App\Services\InvoiceService;
use App\Services\ShaffofIdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends BaseController
{
    protected UserRepository $userRepository;

    public function __construct(
        protected EimzoService $imzoService,
        UserRepository $userRepository,
        protected InvoiceService $invoiceService,
        protected ShaffofIdService $shaffofIdService
    )
    {
        $this->userRepository = $userRepository;
        parent::__construct();
    }

    public function login(Request $request): JsonResponse
    {
        try {
            if ($request->has('pkcs7')) {
                $pkcs7 = $request->pkcs7;
                $data = $this->imzoService->getUserInfo($pkcs7);
                if (is_string($data)) return response()->json([
                    'success' => false,
                    'message' => $data
                ], 403);
                $identification_number = $data['identification_number'] ?? false;
                if (!$identification_number) return response()->json([
                    'success' => false,
                    'message' => "Noma'lum xato. Qayta urinib ko'ring."
                ], 404);
                if (preg_match('/^\d{14}$/', $identification_number) || preg_match('/^\d{9}$/', $identification_number)) {
                    $pinfl = $identification_number;
                    $user = User::query()->where('pinfl', $pinfl)->first();
                    if (!$user) {
                        $new_user = $this->userRepository->createOrUpdate($data['person_data']);
                        $this->userRepository->attachRole($new_user, UserRoleEnum::BUYURTMACHI->value);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => "Noma'lum xato. Qayta urinib ko'ring."
                    ], 404);
                }
            } else {
                $encodedData = request('token');
                $decodedData = base64_decode($encodedData);
                list($pinfl, $accessToken) = explode(':', $decodedData);
            }

            $user = User::query()->where('pinfl', $pinfl)->first();
            if ($user) {
                Auth::login($user);
                $user = Auth::user();
                if ($user->user_status_id != UserStatusEnum::ACTIVE) return $this->sendError('Kirish huquqi mavjud emas', code: 401);
                $roleId = request('role_id');
                $role = Role::query()->find($roleId);
                $token = JWTAuth::claims(['role_id' => $roleId])->fromUser($user);
                if (is_null($user->director_full_name) and preg_match('/^\d{9}$/', (int)$pinfl)) {
                    $get_company_info = $this->invoiceService->getCompanyInfo($pinfl);
                    if ($get_company_info) {
                        $user_data = ['director_full_name' => $get_company_info['director'], 'pinfl' => $pinfl];
                        $this->userRepository->createOrUpdate($user_data);
                    }
                }

                $success['token'] = $token;
                $success['id'] = $user->id;
                if (preg_match('/^\d{9}$/', (int)$pinfl)){
                    $success['full_name'] = $user->organization_name;
                } else {
                    $success['full_name'] = $user->full_name;
                }
                $success['pinfl'] = strlen($user->pinfl ?? '') === 14
                    ? substr($user->pinfl, 0, 4) . '******' . substr($user->pinfl, -4)
                    : $user->pinfl;
                $success['role'] = new RoleResource($role);
                $success['status'] = new UserStatusResource($user->status);
                $success['region'] = $user->region_id ? new RegionResource($user->region) : null;
                $success['district'] = $user->district_id ? new DistrictResource($user->district) : null;
                $success['image'] = $user->image ? Storage::disk('public')->url($user->image) : null;
                return $this->sendSuccess($success, 'User logged in successfully.');
            } else {
                return $this->sendError('Kirish huquqi mavjud emas', code: 401);
            }
        }catch (\Exception $exception){
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }

    }

    public function logout(): JsonResponse
    {
        $this->shaffofIdService->logout($this->user);
        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->sendSuccess(null, 'Logged out successfully.');
    }

    public function auth(): JsonResponse
    {
        if (Auth::attempt(['login' => request('username'), 'password' => request('password')])) {
            $user = Auth::user();
            if ($user->user_status_id != UserStatusEnum::ACTIVE) return $this->sendError('Kirish huquqi mavjud emas', code: 401);
            $roleId = request('role_id');
            $role = Role::query()->find($roleId);
            $token = JWTAuth::claims(['role_id' => $roleId])->fromUser($user);

            if (\request('app_id')) {
                $user->update([
                    'notification_app_id' => request('app_id'),
                ]);
            }

            $success['token'] = $token;
            if (preg_match('/^\d{9}$/', (int)$user->pinfl)){
                $success['full_name'] = $user->organization_name;
            } else {
                $success['full_name'] = $user->full_name;
            }
            $success['role'] = $role ? new RoleResource($role) : null;
            $success['status'] = new UserStatusResource($user->status);
            $success['region'] = $user->region_id ? new RegionResource($user->region) : null;
            $success['district'] = $user->district_id ? new DistrictResource($user->district) : null;
            $success['image'] = $user->image ? Storage::disk('public')->url($user->image) : null;

            return $this->sendSuccess($success, 'User logged in successfully.');
        } else {
            return $this->sendError('Unauthorised.', code: 401);
        }
    }

    public function checkRole(Request $request): JsonResponse
    {
        try {
            $data = $this->imzoService->getUserInfo($request->pkcs7);
            if (is_string($data)) return $this->sendError($data);
            $identification_number = $data['identification_number'] ?? false;
            if (!$identification_number) return $this->sendError("Noma'lum xato. Qayta urinib ko'ring.");

            $user = User::query()->where('pinfl', $identification_number)->first();
            if (!$user) return $this->sendError('Foydalanuvchi topilmadi');

            $token = JWTAuth::fromUser($user);

            return $this->sendSuccess([
                'roles' => RoleResource::collection($user->roles),
                'token' => $token,
            ], 'User logged in successfully.');

        }catch (\Exception $e){
            return $this->sendError("Ma'lumot olishda xatolik", $e->getMessage());
        }
    }

    public function loginUsb(Request $request): JsonResponse
    {
        try {

            $user = JWTAuth::setToken($request->token)->authenticate();

            if (!$user) {
                return $this->sendError('Foydalanuvchi topilmadi');
            }
            if ($user->user_status_id != UserStatusEnum::ACTIVE) return $this->sendError('Kirish huquqi mavjud emas', code: 401);

            $role = Role::query()->find($request->role_id);

            $token = JWTAuth::claims(['role_id' => $request->role_id])->fromUser($user);

            $success['token'] = $token;
            $success['id'] = $user->id;
            $success['full_name'] = $user->full_name;
            $success['pinfl'] = $user->pinfl;
            $success['role'] = new RoleResource($role);
            $success['status'] = new UserStatusResource($user->status);
            $success['region'] = $user->region_id ? new RegionResource($user->region) : null;
            $success['district'] = $user->district_id ? new DistrictResource($user->district) : null;
            $success['image'] = $user->image ? Storage::disk('public')->url($user->image) : null;
            return $this->sendSuccess($success, 'User logged in successfully.');

        }catch (\Exception $e){
            return $this->sendError("Xatolik aniqlandi", $e->getMessage());
        }
    }
    public function challenge(Request $request): JsonResponse
    {

        $user = User::query()->where('pinfl', $request->pinfl)->first();
        $data = $this->imzoService->getChallenge();
        if ($user) {
            try {

                $info['challenge'] = $data;
                $info['roles'] = RoleResource::collection($user->roles);
                return $this->sendSuccess($info, 'data');
            } catch (\Exception $exception) {
                return $this->sendError($exception->getMessage(), code: 404);
            }
        } else {
            $get_role = Role::query()->find(UserRoleEnum::BUYURTMACHI->value);
            $role = new RoleResource($get_role);
            return $this->sendSuccess(['challenge' => $data, "roles" => [$role]], 'data');
        }
    }

    public function refresh(): JsonResponse
    {
        $user = Auth::user();
        \request()->user()->token()->revoke();

        $success['token'] = $user->createToken('MyApp')->accessToken;
        $success['name'] = $user->name;

        return $this->sendSuccess($success, 'User refreshed successfully.');
    }
}
