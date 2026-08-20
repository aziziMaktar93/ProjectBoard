<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardMemberRequest extends FormRequest
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
        $board = $this->route('board');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('workspace_user', 'user_id')->where('workspace_id', $board->workspace_id),
            ],
        ];
    }
}
