<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardLabelRequest extends FormRequest
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
            'color' => ['required', 'string', 'max:32'],
        ];
    }
}
