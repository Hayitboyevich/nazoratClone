<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileEditRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileController extends BaseController
{
    public function profile()
    {
        $user = Auth::guard('api')->user();

        return $this->sendSuccess(
            [
                ...UserResource::make($user)->resolve(),
                'role_id' => $this->roleId,
            ],
            'User found.'
        );
    }

    public function edit(ProfileEditRequest $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        if ($request->post('phone')){
            $user->phone = $request->post('phone');
            $user->save();
        }
        if ($request->post('test_result')){
            $user->test_result = $request->post('test_result');
            $user->save();
        }
        if ($request->has('test_data')) {
            $actionData = collect($user->test_data ?? [])
                ->concat($request->post('test_data'))
                ->keyBy('id')
                ->values()
                ->all();

            $user->update([
                'test_data' => $actionData,
            ]);
        }
        if ($request->hasFile('image')){
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }

            $imagePath = $request->file('image')->store('users', 'public');
            $user->image = $imagePath;
            $user->save();
        }


        return $this->sendSuccess(new UserResource($user), 'User updated successfully.');
    }
}
