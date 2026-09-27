<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShipmentEvent;
use App\Services\Shipping\ShipmentEventRecorder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipmentEventController extends Controller
{
    /**
     * A rider's proof-of-delivery photo. Kept on the private disk and only
     * streamed to signed-in staff.
     */
    public function photo(ShipmentEvent $event): StreamedResponse
    {
        $disk = Storage::disk(ShipmentEventRecorder::PHOTO_DISK);

        abort_unless($event->photo_path && $disk->exists($event->photo_path), 404);

        return $disk->response($event->photo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
