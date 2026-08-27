<?php

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkLabelCardsRequest extends FormRequest
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
            'card_ids' => ['required', 'array', 'min:1'],
            'card_ids.*' => ['integer'],
            'label_id' => [
                'required',
                'integer',
                Rule::exists('labels', 'id')->where('board_id', $this->route('board')->id),
            ],
        ];
    }
}
