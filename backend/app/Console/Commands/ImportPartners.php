<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Bulk-import partner organizations from a JSON file.
 *
 * Each record is created (idempotent, keyed by website_url so re-runs are
 * safe) as a published partner with the given visibility, owned by the super
 * admin. Used for a one-time seed; afterwards partners are managed in the
 * admin dashboard.
 *
 * Usage: php artisan waais:import-partners database/data/partners.json
 */
class ImportPartners extends Command
{
    protected $signature = 'waais:import-partners {path} {--update : Overwrite partners that already exist} {--dry-run : Parse and report without writing}';

    protected $description = 'Bulk-import partner organizations from a JSON file (idempotent on website_url).';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('Could not parse JSON (expected an array of partners).');

            return self::FAILURE;
        }

        // Imported partners are owned by the super admin (partners require a creator).
        $creator = User::query()
            ->where('email', mb_strtolower((string) config('services.super_admin_email')))
            ->first()
            ?? User::superAdmins()->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $creator && ! $this->option('dry-run')) {
            $this->error('No user found to own the imported partners. Create a super admin first.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            foreach (['name', 'website_url'] as $required) {
                if (empty($row[$required])) {
                    $this->error("Row {$i}: missing required field '{$required}'. Skipping.");

                    continue 2;
                }
            }

            $attributes = [
                'created_by' => $creator?->id,
                'content_status' => ContentStatus::Published,
                'visibility' => ContentVisibility::from($row['visibility'] ?? 'public'),
                'published_at' => now(),
                'name' => $row['name'],
                'partner_type' => $row['partner_type'] ?? null,
                'summary' => $row['summary'] ?? null,
                'description' => $row['description'] ?? null,
                'website_url' => $row['website_url'],
                'logo_url' => $row['logo_url'] ?? null,
                'sort_order' => $row['sort_order'] ?? 0,
            ];

            if ($this->option('dry-run')) {
                $this->line("[{$row['website_url']}] ".$row['name']);

                continue;
            }

            $existing = Partner::query()->where('website_url', $row['website_url'])->first();

            if ($existing) {
                // Create-only by default so re-runs never clobber dashboard edits.
                if ($this->option('update')) {
                    $existing->fill($attributes)->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                Partner::create($attributes);
                $created++;
            }
        }

        $this->info($this->option('dry-run')
            ? 'Dry run complete.'
            : "Imported partners: {$created} created, {$updated} updated, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
