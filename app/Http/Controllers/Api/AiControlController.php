<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiControlRequest;
use App\Http\Resources\AiControlResource;
use App\Models\AiControl;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

class AiControlController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index($id = null): JsonResponse
    {
        try {
            if ($id) {
                return $this->sendSuccess(AiControlResource::make(AiControl::query()->find($id)), 'Successfully fetched.');
            }

            $controls = AiControl::query()->where('object_id', request('object_id'))->paginate(request('per_page', 15));

            return $this->sendSuccess(AiControlResource::collection($controls), 'Successfully fetched.', pagination($controls));

        }catch (\Exception $e) {
            return $this->sendError('Xatolik aniqlandi', $e->getMessage());
        }
    }

    public function change(AiControlRequest $request): JsonResponse
    {
        try {
            $control = AiControl::query()->findOrFail($request->id);
            $control->update($request->except('images'));
            foreach ($request->images as $image) {
                $path = $image->store('images/ai-control', 'public');
                $control->images()->create(['url' => $path]);
            }
            return $this->sendSuccess(AiControlResource::make($control), 'Ai control updated successfully.');
        }catch (\Exception $e) {
            return $this->sendError('Xatolik aniqlandi', $e->getMessage());
        }
    }
}
