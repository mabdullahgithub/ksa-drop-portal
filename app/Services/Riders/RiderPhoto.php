<?php

namespace App\Services\Riders;

use App\Models\Rider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A rider's profile photo. Set by the admin on the Riders page or by the
 * rider from the app. Kept on the private disk — it's personal data — and
 * streamed only to signed-in staff and to the rider themselves.
 */
class RiderPhoto
{
    public const DISK = 'local';

    /** Validation for either upload path. Both apps shrink the picture to a JPEG before sending. */
    public const RULES = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    public static function replace(Rider $rider, UploadedFile $file): void
    {
        $old = $rider->photo_path;

        $path = $file->storeAs(
            'riders/' . $rider->id,
            Str::uuid() . '.' . ($file->guessExtension() ?: 'jpg'),
            self::DISK,
        );

        $rider->forceFill(['photo_path' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    public static function remove(Rider $rider): void
    {
        if ($rider->photo_path) {
            Storage::disk(self::DISK)->delete($rider->photo_path);
            $rider->forceFill(['photo_path' => null])->save();
        }
    }

    public static function response(Rider $rider): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);

        abort_unless($rider->photo_path && $disk->exists($rider->photo_path), 404);

        // The URL carries a version (?v=…), so a changed photo is a new URL.
        return $disk->response($rider->photo_path, null, ['Cache-Control' => 'private, max-age=604800']);
    }

    /**
     * Version for the photo URL, so a new photo isn't served from cache.
     */
    public static function version(Rider $rider): string
    {
        return substr(md5((string) $rider->photo_path), 0, 10);
    }
}
