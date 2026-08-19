<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'background_color' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
