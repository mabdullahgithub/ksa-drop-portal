<?php

namespace App\Observers;

use App\Models\Rider;
use App\Observers\Concerns\RecordsDeletionAudit;
use App\Services\Riders\RiderPhoto;
use Illuminate\Support\Facades\Storage;

class RiderObserver
{
    use RecordsDeletionAudit;

    /**
     * Devices and activation links go with the row (FK cascade). The profile
     * photo is on the private disk, so remove it here.
     */
    public function forceDeleting(Rider $rider): void
    {
        Storage::disk(RiderPhoto::DISK)->deleteDirectory("riders/{$rider->id}");
    }
}
