<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repair connector secrets that were stored in plain text while flagged as
 * encrypted.
 *
 * ConnectorSetting used to encrypt inside a `value` mutator, which reads
 * `is_encrypted` — an attribute mass assignment sets *after* `value`. On the
 * first save of any key the flag was therefore still false, so the secret went
 * to the database unencrypted and was then marked encrypted. Every subsequent
 * read tried to decrypt plain text, failed, and returned null, so the caller
 * silently fell back to its config default: the wrong credentials were used
 * with no error anywhere.
 *
 * The model now encrypts in a `saving` hook (order-independent). This backfills
 * the rows written before that fix. Rows that already decrypt cleanly are left
 * untouched, so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $repaired = 0;

        foreach (DB::table('connector_settings')->where('is_encrypted', true)->get() as $row) {
            $value = (string) ($row->value ?? '');

            if ($value === '') {
                continue;
            }

            // Already encrypted — leave it alone.
            try {
                Crypt::decryptString($value);

                continue;
            } catch (\Throwable $e) {
                // Falls through: plain text needing encryption.
            }

            DB::table('connector_settings')
                ->where('id', $row->id)
                ->update(['value' => Crypt::encryptString($value)]);

            $repaired++;
        }

        if ($repaired > 0) {
            Log::info("Encrypted {$repaired} connector setting(s) that were stored in plain text.");
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: decrypting these back to plain text would
        // reintroduce the vulnerability this migration exists to close.
    }
};
