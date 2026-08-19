<?php

namespace App\Http\Requests\BoardLists;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('board'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
