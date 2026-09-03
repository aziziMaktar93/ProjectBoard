<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $board = $this->route('board');

        if ($this->input('role') === 'hod') {
            return $this->user()->id === $board->user_id;
        }

        return $this->user()->can('update', $board);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:editor,viewer,hod'],
        ];
    }
}
