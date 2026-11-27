<?php

namespace App\Services\Chat;

use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chat files: kept on the private disk under chat/{conversation}/, named by a
 * random id (never the client's file name), and only ever streamed back
 * through a route that has checked who is asking.
 */
class ChatAttachmentStore
{
    /** Validation for the `files` field of a send. */
    public static function rules(): array
    {
        return [
            'files' => ['nullable', 'array', 'max:' . config('chat.attachments.max_files')],
            'files.*' => [
                File::types(config('chat.attachments.mimes'))->max(config('chat.attachments.max_kb')),
            ],
        ];
    }

    /**
     * Put the files on disk. Done before the message row is written, so a
     * failed write leaves nothing half-saved; forget() cleans up when the
     * row is not written after all.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array<string, mixed>> the rows to create
     */
    public static function put(ChatConversation $conversation, array $files): array
    {
        $disk = config('chat.attachments.disk');
        $rows = [];

        foreach ($files as $file) {
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
            $path = $file->storeAs("chat/{$conversation->id}", Str::uuid() . '.' . $extension, $disk);
            $mime = (string) $file->getMimeType();
            $size = in_array($mime, config('chat.attachments.images'), true) ? @getimagesize($file->getRealPath()) : false;

            $rows[] = [
                'conversation_id' => $conversation->id,
                'disk' => $disk,
                'path' => $path,
                // Kept for the download name only; never used as a path.
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                'mime' => $mime,
                'size' => (int) $file->getSize(),
                'width' => $size ? $size[0] : null,
                'height' => $size ? $size[1] : null,
            ];
        }

        return $rows;
    }

    /** @param  list<array<string, mixed>>  $rows */
    public static function attach(ChatMessage $message, array $rows): void
    {
        foreach ($rows as $row) {
            $message->attachments()->create($row);
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    public static function forget(array $rows): void
    {
        foreach ($rows as $row) {
            Storage::disk($row['disk'])->delete($row['path']);
        }
    }

    /**
     * Stream one file. Images display inline; anything else downloads, so a
     * file can never be opened as a page on our domain.
     */
    public static function response(ChatAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], $attachment->isImage() ? 'inline' : 'attachment');
    }
}
