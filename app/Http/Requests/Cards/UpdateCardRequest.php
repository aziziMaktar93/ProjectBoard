<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $board = $this->route('card')->boardList->board;

        if (! $this->user()->can('update', $board)) {
            return false;
        }

        if ($this->has('due_date') && ! $this->user()->can('manageDueDates', $board)) {
            return false;
        }

        return true;
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
