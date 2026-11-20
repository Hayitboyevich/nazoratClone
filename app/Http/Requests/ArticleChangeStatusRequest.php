<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleChangeStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => 'sometimes',
            'guid' => 'sometimes',
            'object_id' => 'required|exists:articles,id',
            'status' => 'required|integer',
            'comment' => 'required|string',
            'deadline' => 'sometimes|string',
            'blocks' => 'sometimes|array',
            'file' => 'sometimes',
        ];
    }
}
