<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Models\StartupListing;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Bulk-import startup listings from a JSON file.
 *
 * Each record is created (idempotent, keyed by website_url so re-runs are
 * safe) as an approved + published listing with the given visibility, owned
 * by the super admin. This is used for a one-time seed of alumni-founded
 * startups; afterwards they are managed in the admin dashboard.
 *
 * Usage: php artisan waais:import-startups database/data/startups.json
 */
class ImportStartups extends Command
{
    protected $signature = 'waais:import-startups {path} {--update : Overwrite listings that already exist} {--dry-run : Parse and report without writing}';

    protected $description = 'Bulk-import startup listings from a JSON file (idempotent on website_url).';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('Could not parse JSON (expected an array of startups).');

            return self::FAILURE;
        }

        // Imported listings are owned by the super admin (listings require an owner).
        $owner = User::query()
            ->where('email', mb_strtolower((string) config('services.super_admin_email')))
            ->first()
            ?? User::superAdmins()->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $owner && ! $this->option('dry-run')) {
            $this->error('No user found to own the imported startups. Create a super admin first.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            foreach (['name', 'description', 'website_url'] as $required) {
                if (empty($row[$required])) {
                    $this->error("Row {$i}: missing required field '{$required}'. Skipping.");

                    continue 2;
                }
            }

            $attributes = [
                'owner_id' => $owner?->id,
                'approval_status' => ApprovalStatus::Approved,
                'content_status' => ContentStatus::Published,
                'visibility' => ContentVisibility::from($row['visibility'] ?? 'public'),
                'name' => $row['name'],
                'tagline' => $row['tagline'] ?? null,
                'description' => $row['description'],
                'website_url' => $row['website_url'],
                'logo_url' => $row['logo_url'] ?? null,
                'industry' => $row['industry'] ?? null,
                'stage' => $row['stage'] ?? null,
                'location' => $row['location'] ?? null,
                'founders' => $row['founders'] ?? null,
                'submitter_role' => $row['submitter_role'] ?? null,
                'linkedin_url' => $row['linkedin_url'] ?? null,
                'submitted_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => $owner?->id,
                'approved_at' => now(),
            ];

            if ($this->option('dry-run')) {
                $this->line("[{$row['website_url']}] ".$row['name']);

                continue;
            }

            $existing = StartupListing::query()->where('website_url', $row['website_url'])->first();

            if ($existing) {
                // Create-only by default so re-runs never clobber dashboard edits.
                if ($this->option('update')) {
                    $existing->fill($attributes)->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                StartupListing::create($attributes);
                $created++;
            }
        }

        $this->info($this->option('dry-run')
            ? 'Dry run complete.'
            : "Imported startups: {$created} created, {$updated} updated, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
