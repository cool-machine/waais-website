<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\NewsController;
use Illuminate\Console\Command;

/**
 * Proactively refreshes the public news feed cache.
 *
 * The /api/public/news endpoint already refreshes lazily (it re-fetches when
 * its short cache expires and someone visits), but on a low-traffic day the
 * feed could sit untouched. This command guarantees the feed is rebuilt on a
 * fixed cadence — it is scheduled twice daily in bootstrap/app.php — so the
 * news is current regardless of visitor traffic.
 */
class RefreshNews extends Command
{
    protected $signature = 'news:refresh';

    protected $description = 'Re-fetch the public news feeds and refresh the cached, AI-ranked list.';

    public function handle(NewsController $news): int
    {
        $count = count($news->rebuildCache());

        if ($count === 0) {
            // rebuildCache() only writes the cache on a successful, non-empty
            // fetch, so an upstream feed outage leaves the existing cache in
            // place. Exit success so the every-minute scheduler stays quiet.
            $this->warn('news:refresh fetched 0 items (feeds may be temporarily unavailable); kept the existing cache.');

            return self::SUCCESS;
        }

        $this->info(sprintf('news:refresh cached %d items.', $count));

        return self::SUCCESS;
    }
}
