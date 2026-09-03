<?php

namespace App\Http\Requests\BoardChat;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('board'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
        ];
    }
}
