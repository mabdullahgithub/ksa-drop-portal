<?php

namespace App\Services\Chat;

use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /** Validation for a recorded voice message: the `voice` field and its length. */
    public static function voiceRules(): array
    {
        return [
            'voice' => [
                'nullable',
                'file',
                'max:' . config('chat.voice.max_kb'),
                'mimetypes:' . implode(',', array_keys(config('chat.voice.types'))),
            ],
            'voice_duration' => ['required_with:voice', 'integer', 'min:1', 'max:' . config('chat.voice.max_seconds')],
            'voice_waveform' => ['nullable', 'string', 'max:400'],
        ];
    }

    /**
     * Store a voice message. It is only ever served as audio, whatever
     * container the browser recorded into.
     *
     * @return array<string, mixed> the row to create
     */
    public static function putVoice(ChatConversation $conversation, UploadedFile $file, int $seconds, ?string $waveform = null): array
    {
        $disk = config('chat.attachments.disk');
        [$mime, $extension] = config('chat.voice.types')[(string) $file->getMimeType()] ?? ['audio/mp4', 'm4a'];

        return [
            'conversation_id' => $conversation->id,
            'disk' => $disk,
            'path' => $file->storeAs("chat/{$conversation->id}", Str::uuid() . '.' . $extension, $disk),
            'original_name' => "voice-message.{$extension}",
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'is_voice' => true,
            'duration_seconds' => $seconds,
            'waveform' => self::bars($waveform),
        ];
    }

    /** The recorder's bar heights: up to 32 whole numbers from 0 to 100, or nothing. */
    private static function bars(?string $json): ?array
    {
        $bars = $json === null ? null : json_decode($json, true);

        if (! is_array($bars) || $bars === [] || count($bars) > 32 || array_filter($bars, fn ($bar) => ! is_int($bar))) {
            return null;
        }

        return array_values(array_map(fn (int $bar) => max(0, min(100, $bar)), $bars));
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
     * Send one file. Images display and voice messages play inline; anything
     * else downloads, so a file can never be opened as a page on our domain.
     *
     * A BinaryFileResponse, because it answers range requests: Safari will
     * not play audio without them. Private, never a shared cache.
     */
    public static function response(ChatAttachment $attachment): BinaryFileResponse
    {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        $response = new BinaryFileResponse($disk->path($attachment->path), 200, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
        ], public: false);

        $response->headers->set('Cache-Control', 'private, max-age=86400');
        $response->setContentDisposition(
            $attachment->isImage() || $attachment->is_voice ? 'inline' : 'attachment',
            str_replace(['/', '\\'], '-', $attachment->original_name),
            preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($attachment->original_name)) ?: 'file',
        );

        return $response;
    }
}
