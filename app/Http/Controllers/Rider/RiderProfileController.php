<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Services\Riders\RiderPhoto;
use App\Services\Riders\RiderPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The rider's own profile photo, from the app.
 */
class RiderProfileController extends Controller
{
    public function photo(Request $request): StreamedResponse
    {
        return RiderPhoto::response($this->rider($request));
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => RiderPhoto::RULES]);

        $rider = $this->rider($request);
        RiderPhoto::replace($rider, $request->file('photo'));

        return response()->json([
            'photo_url' => route('rider.api.photo', ['v' => RiderPhoto::version($rider)]),
        ]);
    }

    private function rider(Request $request): Rider
    {
        return $request->attributes->get('rider');
    }

    /**
     * The app's once-a-minute check-in while it's on screen.
     */
    public function presence(Request $request): Response
    {
        RiderPresence::checkIn($request->attributes->get('rider'));

        return response()->noContent();
    }
}
