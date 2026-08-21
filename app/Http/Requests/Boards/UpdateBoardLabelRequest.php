<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('label')->board);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['sometimes', 'required', 'string', 'max:32'],
        ];
    }
}
