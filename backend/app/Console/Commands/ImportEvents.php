<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Bulk-import legacy / past events from a JSON file.
 *
 * Each record is created (or updated, keyed by external_ref so re-runs are
 * idempotent) as a published event with the given visibility. Past dates make
 * them render automatically as recap/past events on the public calendar.
 *
 * Usage: php artisan waais:import-events database/data/legacy_events.json
 */
class ImportEvents extends Command
{
    protected $signature = 'waais:import-events {path} {--update : Overwrite events that already exist} {--dry-run : Parse and report without writing}';

    protected $description = 'Bulk-import past events from a JSON file (idempotent on external_ref).';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('Could not parse JSON (expected an array of events).');

            return self::FAILURE;
        }

        // Imported events are owned by the super admin (events require a creator).
        $creator = User::query()
            ->where('email', mb_strtolower((string) config('services.super_admin_email')))
            ->first()
            ?? User::superAdmins()->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $creator && ! $this->option('dry-run')) {
            $this->error('No user found to own the imported events. Create a super admin first.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            foreach (['title', 'summary', 'description', 'starts_at'] as $required) {
                if (empty($row[$required])) {
                    $this->error("Row {$i}: missing required field '{$required}'. Skipping.");

                    continue 2;
                }
            }

            $attributes = [
                'created_by' => $creator?->id,
                'title' => $row['title'],
                'summary' => $row['summary'],
                'description' => $row['description'],
                'starts_at' => Carbon::parse($row['starts_at']),
                'ends_at' => ! empty($row['ends_at']) ? Carbon::parse($row['ends_at']) : null,
                'location' => $row['location'] ?? null,
                'format' => $row['format'] ?? null,
                'image_url' => $row['image_url'] ?? null,
                'registration_url' => $row['registration_url'] ?? null,
                'recap_content' => $row['recap_content'] ?? null,
                'visibility' => ContentVisibility::from($row['visibility'] ?? 'public'),
                'content_status' => ContentStatus::Published,
                'published_at' => now(),
            ];

            $ref = $row['external_ref'] ?? null;

            if ($this->option('dry-run')) {
                $this->line(($ref ? "[{$ref}] " : '').$row['title'].' — '.$attributes['starts_at']->toDayDateTimeString());

                continue;
            }

            $existing = $ref ? Event::query()->where('external_ref', $ref)->first() : null;

            if ($existing) {
                // Create-only by default so re-runs (e.g. on every deploy) never
                // clobber edits an admin made in the dashboard.
                if ($this->option('update')) {
                    $existing->fill($attributes)->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                $event = new Event($attributes);
                $event->external_ref = $ref;
                $event->save();
                $created++;
            }
        }

        $this->info($this->option('dry-run')
            ? 'Dry run complete.'
            : "Imported events: {$created} created, {$updated} updated, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
