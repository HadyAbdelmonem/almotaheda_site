<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function afterMount(): void
    {
        // Opening a message marks it read (admin workflow, spec §7).
        if (! $this->record->is_read) {
            $this->record->update(['is_read' => true]);
        }
    }
}
