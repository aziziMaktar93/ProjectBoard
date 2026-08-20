<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('workspace'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'background_color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
