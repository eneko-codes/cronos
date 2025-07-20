<?php

namespace App\Observers;

use App\Models\TimeEntry;
use Illuminate\Support\Facades\Log;

class TimeEntryObserver
{
    public function created(TimeEntry $entry)
    {
        Log::debug('TimeEntry created', [
            'proofhub_time_entry_id' => $entry->proofhub_time_entry_id,
            'attributes' => $entry->getAttributes(),
        ]);
    }

    public function updated(TimeEntry $entry)
    {
        $changes = $entry->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $entry->getOriginal($field);
            }
            Log::debug('TimeEntry updated', [
                'proofhub_time_entry_id' => $entry->proofhub_time_entry_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted(TimeEntry $entry)
    {
        Log::debug('TimeEntry deleted', [
            'proofhub_time_entry_id' => $entry->proofhub_time_entry_id,
            'attributes' => $entry->getOriginal(),
        ]);
    }
}
