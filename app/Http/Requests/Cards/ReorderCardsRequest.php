<?php

namespace App\Http\Requests\Cards;

use App\Models\Board;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCardsRequest extends FormRequest
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
        /** @var Board $board */
        $board = $this->route('board');

        // Every referenced card must currently belong to the source or target list —
        // both of which are already confirmed to belong to this board above. Without
        // this, a caller could smuggle a card id belonging to a different user's board
        // into the payload and have it silently reassigned into this board's list.
        $listIds = array_filter([$this->integer('target_list_id'), $this->integer('source_list_id')]);

        return [
            'source_list_id' => ['nullable', 'integer', 'required_with:source_ordered_ids', Rule::exists('board_lists', 'id')->where('board_id', $board->id)],
            'target_list_id' => ['required', 'integer', Rule::exists('board_lists', 'id')->where('board_id', $board->id)],
            'target_ordered_ids' => ['required', 'array'],
            'target_ordered_ids.*' => ['integer', 'distinct', Rule::exists('cards', 'id')->whereIn('board_list_id', $listIds)],
            // `present_with` (not `required_with`) so dragging the last remaining
            // card out of a list — leaving an empty `source_ordered_ids: []` — is
            // still valid; `required_with` treats an empty array as absent.
            'source_ordered_ids' => ['array', 'present_with:source_list_id'],
            'source_ordered_ids.*' => ['integer', 'distinct', Rule::exists('cards', 'id')->whereIn('board_list_id', $listIds)],
        ];
    }
}
