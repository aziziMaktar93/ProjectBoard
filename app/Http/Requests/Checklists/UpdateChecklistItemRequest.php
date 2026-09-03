<?php

namespace App\Http\Requests\Checklists;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $board = $this->route('checklistItem')->checklist->card->boardList->board;

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
            'is_checked' => ['sometimes', 'boolean'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
