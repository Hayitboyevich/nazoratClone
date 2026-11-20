<?php

namespace App\Services;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Helpers\ModelResolver;
use App\Http\Requests\CheckDocumentRequest;
use App\Http\Resources\RoleResource;
use App\Infrastructure\ExternalApis\ShaffofId\ShaffofIdClient;
use App\Models\DocumentSignature;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShaffofIdService
{

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected InvoiceService          $invoiceService,
        protected ShaffofIdClient          $client
    )
    {

    }

    public function handleCallback(string $code, string $codeVerifier): ?array
    {
        $tokenResponse = $this->client->getAccessToken(
            grantType: 'authorization_code',
            codeVerify: $codeVerifier,
            code: $code,
            redirect_uri: config('app.shaffofId.redirect_url')
        );

        if (!$tokenResponse) {
            return null;
        }

        $accessToken = $tokenResponse->access_token ?? null;
        $idToken = $tokenResponse->id_token;

        if (!$accessToken) {
            return null;
        }


        $userInfo = $this->client->getInfo(accessToken: $accessToken);


        if (!$userInfo) {
            throw new ModelNotFoundException('ShaffofID: userinfo so\'rovi muvaffaqiyatsiz');
        }


//        telegramNotification('User malumotlari',123, $userInfo);

        $isLegal = ($userInfo->subject_type ?? null) === 'legal';

        $tin = data_get($userInfo, 'organization.tin');

        $pin = $isLegal && !empty($tin)
            ? $tin
            : $userInfo->pinfl;


        $organizationName = $isLegal
            ? ($userInfo->organization->name
                ?? $userInfo->organization->full_name
                ?? $userInfo->organization->short_name
                ?? $userInfo->organization->title
                ?? null)
            : null;

        $phone = $userInfo->phone_number ?? null;
        $userType = $isLegal ? 'Yuridik' : 'Jismoniy';

        if (!$pin) {
            throw new ModelNotFoundException('ShaffofID: userinfo\'da pinfl ham, tin ham yo\'q');
        }


        $user = User::query()
            ->where('pinfl', $pin)
            ->where('user_status_id', UserStatusEnum::ACTIVE->value)
            ->where('active', 1)
            ->first();

        if (!$user) {
            $userData = [
                "name" => $userInfo?->given_name,
                "surname" => $userInfo?->family_name,
                "middle_name" => data_get($userInfo, 'middle_name'),
                "pinfl" => $pin,
                "login" => $isLegal ? $pin : $userInfo?->passport_number,
                "password" => Hash::make($pin),
                "active" => UserStatusEnum::ACTIVE->value,
                "user_status_id" => UserStatusEnum::ACTIVE->value,
                "phone" => $phone,
            ];
            if ($isLegal) {
                $get_company_info = $this->invoiceService->getCompanyInfo($pin);
                $userData['organization_name'] = $get_company_info['shortName'];
                $userData['director_full_name'] = $get_company_info['director'];

            }
            $new_user = $this->userRepository->createOrUpdate($userData);
            $this->userRepository->attachRole($new_user, UserRoleEnum::BUYURTMACHI->value);
        }

        if ($user->active == 0) throw new ModelNotFoundException('Foydalanuvchi faol emas');

        $combinedData = $pin . ':' . $accessToken;

        $encodedData = base64_encode($combinedData);

        $user->update(['id_token' => $idToken ?? null]);

        return [
            'roles' => RoleResource::collection($user->roles),
            'full_name' => $user->full_name ? $user->full_name : ($organizationName ?? null),
            'access_token' => $encodedData,
            'user_type' => $userType,
        ];
    }

    public function checkDocument($user, int $roleId, CheckDocumentRequest $request)
    {
        $modelSign = null;

        if ($request->filled('id')) {
            $model = ModelResolver::find(
                table: $request->table,
                id: $request->id
            );

            $modelSign = $model->signatures()
                ->where('user_id', $user->id)
                ->where('role_id', $roleId)
                ->whereNull('status')
                ->whereNull('signed_at')
                ->latest('id')
                ->first();
        }

        $token = $this->client->getAccessToken(
            grantType: 'client_credentials',
            scope: 'documents'
        );

        if ($modelSign) {
            $result = $this->client->checkSigningStatus(
                $token->access_token,
                $modelSign->uuid
            );

            $status = $result->status ?? null;

            if ($status === 'pending') {
                return [
                    'uuid' => $modelSign->uuid,
                    'guid' => $modelSign->guid,
                    'sign_url' => 'https://id.shaffofqurilish.uz/documents/' . $modelSign->uuid,
                ];
            }

            if ($status === null) {
                throw new \RuntimeException(
                    'Hujjatning imzolash holatini aniqlab bo‘lmadi.'
                );
            }

            $modelSign->update([
                'status' => $status,
                'signed_at' => $status === 'signed' ? now() : null,
            ]);
        }

        $guid = (string) Str::uuid();

        $data = $this->client->sendSigningDocument(
            user: $user,
            accessToken: $token->access_token,
            returnUrl: $request->redirect_url,
            title: $request->title,
            body: $request->body,
            uuid: $guid,
            contentType: 'application/json',
            test: $request->test,
        );

        $this->createDocumentSignature(
            guid: $guid,
            uuid: $data->uuid,
            userId: $user->id,
            roleId: $roleId,
            request: $request
        );

        $data->guid = $guid;

        return $data;
    }

    private function createDocumentSignature(
        string $guid,
        string $uuid,
        int $userId,
        int $roleId,
        CheckDocumentRequest $request
    ): DocumentSignature {
        $attributes = [
            'uuid' => $uuid,
            'user_id' => $userId,
            'role_id' => $roleId,
            'guid' => $guid,
        ];

        if (!$request->filled('id')) {
            return DocumentSignature::query()->create($attributes);
        }

        $model = ModelResolver::find(
            $request->table,
            $request->id
        );

        return $model->signatures()->updateOrCreate(
            [
                'user_id' => $userId,
                'role_id' => $roleId,
                'status' => null,
            ],
            $attributes
        );
    }


    public function logout($user)
    {
        try {
            $response = $this->client->refreshSession($user->id_token);

            if ($response) {
                return true;
            }
            return false;
        } catch (\Throwable $exception) {
            return false;
        }
    }


    public function checkDocumentSign($user, $roleId, $guid, $uuid): bool
    {
        try {
            $document = DocumentSignature::query()
                ->whereNull('status')
                ->where('user_id', $user->id)
                ->where('role_id', $roleId)
                ->where('guid', $guid)
                ->where('uuid', $uuid)
                ->first();

            if (!$document) {
                return false;
            }

            $token = $this->client->getAccessToken(
                grantType: 'client_credentials',
                scope: 'documents'
            );

            $result = $this->client->checkSigningStatus(
                $token->access_token,
                $document->uuid
            );

            if (($result->status ?? null) !== 'signed') {
                $document->update(['status' => 'failed']);
                return false;
            }

            return (bool) $document->update([
                'status' => 'signed',
                'signed_at' => now(),
            ]);

        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getInfo($uuid)
    {
        try {
            $token = $this->client->getAccessToken(
                grantType: 'client_credentials',
                scope: 'documents'
            );
            return  $this->client->checkSigningStatus(
                accessToken:$token->access_token,
                externalUuid: $uuid
            );
        }catch (\Throwable $exception) {
            return false;
        }
    }
}
