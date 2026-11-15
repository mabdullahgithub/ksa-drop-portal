<?php

namespace App\Services\Partners;

use App\Models\Partner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A partner's identity document and logo. Both sit on the private disk;
 * the document is streamed only to staff who hold 'view partner documents',
 * the logo to anyone (it appears on the public referral page).
 */
class PartnerDocuments
{
    public const DISK = 'local';

    public const ID_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'];

    public const LOGO_RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];

    public static function storeId(Partner $partner, UploadedFile $file): void
    {
        $old = $partner->id_document_path;

        $path = $file->storeAs(
            'partners/' . $partner->id,
            'id-' . Str::uuid() . '.' . ($file->guessExtension() ?: 'bin'),
            self::DISK,
        );

        $partner->forceFill(['id_document_path' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    public static function storeIdBack(Partner $partner, UploadedFile $file): void
    {
        $old = $partner->id_document_back_path;

        $path = $file->storeAs(
            'partners/' . $partner->id,
            'id-back-' . Str::uuid() . '.' . ($file->guessExtension() ?: 'bin'),
            self::DISK,
        );

        $partner->forceFill(['id_document_back_path' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    public static function storeLogo(Partner $partner, UploadedFile $file): void
    {
        $old = $partner->logo_path;

        $path = $file->storeAs(
            'partners/' . $partner->id,
            'logo-' . Str::uuid() . '.' . ($file->guessExtension() ?: 'jpg'),
            self::DISK,
        );

        $partner->forceFill(['logo_path' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    public static function idResponse(Partner $partner, bool $back = false): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        $path = $back ? $partner->id_document_back_path : $partner->id_document_path;

        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    public static function logoResponse(Partner $partner): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);

        abort_unless($partner->logo_path && $disk->exists($partner->logo_path), 404);

        return $disk->response($partner->logo_path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    public static function logoUrl(Partner $partner): ?string
    {
        return $partner->logo_path
            ? route('partners.public.logo', ['code' => $partner->code, 'v' => substr(md5($partner->logo_path), 0, 8)])
            : null;
    }
}
