<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('card')->boardList->board);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'cover_attachment_id' => [
                'sometimes',
                'nullable',
                Rule::exists('attachments', 'id')
                    ->where('card_id', $this->route('card')->id)
                    ->where(fn ($query) => $query->where('mime_type', 'like', 'image/%')),
            ],
        ];
    }
}
