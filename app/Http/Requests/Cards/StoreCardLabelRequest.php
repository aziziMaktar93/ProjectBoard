<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardLabelRequest extends FormRequest
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
            'label_id' => [
                'required',
                'integer',
                Rule::exists('labels', 'id')->where('board_id', $board->id),
            ],
        ];
    }
}
