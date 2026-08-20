<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardMemberRequest extends FormRequest
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
        $board = $this->route('card')->boardList->board;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('board_user', 'user_id')->where('board_id', $board->id),
            ],
        ];
    }
}
