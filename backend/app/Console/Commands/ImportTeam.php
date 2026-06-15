<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Seed team members (founders / advisors) from a JSON file.
 *
 * Create-only and keyed on name, so re-runs never duplicate or clobber edits
 * made in the admin dashboard. Members are created as DRAFTS — an admin
 * reviews and publishes them from the dashboard before they appear publicly.
 *
 * Usage: php artisan waais:import-team database/data/team.json
 */
class ImportTeam extends Command
{
    protected $signature = 'waais:import-team {path} {--update : Overwrite members that already exist}';

    protected $description = 'Seed founders/advisors from a JSON file (create-only, drafts, keyed on name).';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('Could not parse JSON (expected an array of team members).');

            return self::FAILURE;
        }

        $creator = User::query()
            ->where('email', mb_strtolower((string) config('services.super_admin_email')))
            ->first()
            ?? User::superAdmins()->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $creator) {
            $this->error('No user found to own the imported team members.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            if (empty($row['name'])) {
                $this->error("Row {$i}: missing required field 'name'. Skipping.");

                continue;
            }

            $attributes = [
                'created_by' => $creator->id,
                'content_status' => ContentStatus::from($row['content_status'] ?? 'draft'),
                'published_at' => (($row['content_status'] ?? 'draft') === 'published') ? now() : null,
                'visibility' => ContentVisibility::from($row['visibility'] ?? 'public'),
                'name' => $row['name'],
                'role_title' => $row['role_title'] ?? null,
                'member_group' => in_array($row['member_group'] ?? 'advisor', ['founder', 'advisor'], true) ? $row['member_group'] : 'advisor',
                'bio' => $row['bio'] ?? null,
                'photo_url' => $row['photo_url'] ?? null,
                'linkedin_url' => $row['linkedin_url'] ?? null,
                'sort_order' => $row['sort_order'] ?? 0,
            ];

            $existing = TeamMember::query()->where('name', $row['name'])->first();

            // Allow renaming an existing member: match the old name if the new
            // one isn't found yet (idempotent — a second run matches the new name).
            if (! $existing && ! empty($row['match_name'])) {
                $existing = TeamMember::query()->where('name', $row['match_name'])->first();
            }

            if ($existing) {
                if ($this->option('update')) {
                    $existing->fill($attributes)->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                TeamMember::create($attributes);
                $created++;
            }
        }

        $this->info("Imported team members: {$created} created, {$updated} updated, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
