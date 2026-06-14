<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Public, anonymous AI/analytics news feed.
 *
 * Aggregates official Penn / Wharton RSS feeds, ranks AI & analytics stories
 * first, and returns title + short excerpt + source + outbound link. We never
 * republish full articles — every item links back to the original source.
 *
 * Results are cached (database cache store) for a few hours so the endpoint
 * stays fast and makes no per-request external calls. No scheduler required.
 */
class NewsController extends Controller
{
    private const CACHE_KEY = 'public_news_v1';

    private const CACHE_TTL_HOURS = 3;

    private const MAX_ITEMS = 15;

    /** Official sources: display label => RSS feed URL. */
    private const FEEDS = [
        'Knowledge at Wharton' => 'https://knowledge.wharton.upenn.edu/feed/',
        'Penn Today' => 'https://penntoday.upenn.edu/rss.xml',
    ];

    /** Multi-word phrases that mark an item as AI / analytics related. */
    private const AI_PHRASES = [
        'artificial intelligence', 'machine learning', 'deep learning',
        'generative', 'large language model', 'analytics', 'data science',
        'algorithm', 'neural', 'chatbot', 'automation', 'autonomous',
        'robot', 'predictive', 'gpt', 'copilot',
    ];

    public function index(): JsonResponse
    {
        $items = Cache::get(self::CACHE_KEY);

        if ($items === null) {
            $items = $this->buildItems();
            // Only cache a successful (non-empty) fetch so a transient feed
            // outage doesn't pin an empty list for hours.
            if (! empty($items)) {
                Cache::put(self::CACHE_KEY, $items, now()->addHours(self::CACHE_TTL_HOURS));
            }
        }

        return response()->json(['data' => $items ?? []]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(): array
    {
        $items = [];

        foreach (self::FEEDS as $source => $url) {
            try {
                $response = Http::timeout(5)->get($url);
                if (! $response->successful()) {
                    continue;
                }
                foreach ($this->parseFeed($response->body(), $source) as $item) {
                    $items[] = $item;
                }
            } catch (\Throwable) {
                // A failing feed must never break the endpoint.
                continue;
            }
        }

        // AI / analytics items first, then most recent first.
        usort($items, function (array $a, array $b): int {
            if ($a['ai'] !== $b['ai']) {
                return $b['ai'] <=> $a['ai'];
            }

            return $b['_ts'] <=> $a['_ts'];
        });

        return array_values(array_map(static function (array $item): array {
            unset($item['_ts']);

            return $item;
        }, array_slice($items, 0, self::MAX_ITEMS)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseFeed(string $body, string $source): array
    {
        $out = [];

        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            return $out;
        }

        $entries = $xml->channel->item ?? $xml->item ?? [];

        foreach ($entries as $entry) {
            $title = trim((string) ($entry->title ?? ''));
            $link = trim((string) ($entry->link ?? ''));

            if ($title === '' || $link === '') {
                continue;
            }

            $descriptionRaw = (string) ($entry->description ?? '');
            $excerpt = Str::limit(
                trim((string) preg_replace('/\s+/', ' ', strip_tags($descriptionRaw))),
                200
            );

            $pubDate = (string) ($entry->pubDate ?? '');
            $timestamp = $pubDate !== '' ? strtotime($pubDate) : false;

            $out[] = [
                'title' => $title,
                'url' => $link,
                'source' => $source,
                'excerpt' => $excerpt,
                'published_at' => $timestamp ? date(DATE_ATOM, $timestamp) : null,
                'ai' => $this->isAiRelated($title.' '.$descriptionRaw) ? 1 : 0,
                '_ts' => $timestamp ?: 0,
            ];
        }

        return $out;
    }

    private function isAiRelated(string $text): bool
    {
        // Whole-word "AI" / "A.I." / "GenAI" / "LLM", or any AI phrase.
        if (preg_match('/\b(a\.?i\.?|genai|llm|llms)\b/i', $text) === 1) {
            return true;
        }

        return Str::contains(Str::lower($text), self::AI_PHRASES);
    }
}
