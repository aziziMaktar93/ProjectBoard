<?php

namespace App\Services;

use App\Models\CardActivity;
use Carbon\Carbon;

class CardActivityDescriber
{
    public function describe(CardActivity $activity): string
    {
        $cardName = $activity->card->name ?? 'a card';
        $data = $activity->data ?? [];

        return match ($activity->type) {
            'comment' => "commented on {$cardName}",
            'moved' => "moved {$cardName} from {$data['from_list']} to {$data['to_list']}",
            'checklist_item_completed' => "completed {$data['item_name']} on {$cardName}",
            'checklist_item_uncompleted' => "marked {$data['item_name']} incomplete on {$cardName}",
            'member_added' => "added {$data['member_name']} to {$cardName}",
            'member_removed' => "removed {$data['member_name']} from {$cardName}",
            'label_added' => "added the {$data['label_name']} label to {$cardName}",
            'label_removed' => "removed the {$data['label_name']} label from {$cardName}",
            'attachment_added' => "added {$data['attachment_name']} to {$cardName}",
            'attachment_removed' => "removed {$data['attachment_name']} from {$cardName}",
            'due_date_changed' => "set the due date on {$cardName} to ".Carbon::parse($data['due_date'])->format('M j, Y'),
            'due_date_removed' => "removed the due date from {$cardName}",
            'archived' => "archived {$cardName}",
            'restored' => "restored {$cardName}",
            default => "updated {$cardName}",
        };
    }
}
