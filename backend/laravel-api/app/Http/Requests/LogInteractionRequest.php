<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'post_id' => 'required|exists:posts,id',
            'type' => 'required|string|in:view,reply,reaction',
        ];
    }
}
