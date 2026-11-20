<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleLocationChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'guid' =>'required',
            'uuid' =>'required',
            'lat' => 'required',
            'long' => 'required',
            'comment' => 'required',
            'object_id' => 'required|integer|exists:articles,id',
        ];
    }
}
