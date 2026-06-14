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
    private const CACHE_KEY = 'public_news_v3';

    private const CACHE_TTL_HOURS = 3;

    private const MAX_ITEMS = 15;

    /** Always show at least this many items, backfilling recent non-AI stories. */
    private const MIN_ITEMS = 6;

    /** Official sources: display label => RSS feed URL. */
    private const FEEDS = [
        'Knowledge at Wharton' => 'https://knowledge.wharton.upenn.edu/feed/',
        'Penn Today' => 'https://penntoday.upenn.edu/rss.xml',
    ];

    /** Phrases weighted by how strongly they signal genuine AI content. */
    private const AI_TERMS_STRONG = [
        'artificial intelligence', 'machine learning', 'deep learning',
        'large language model', 'generative ai', 'genai', 'chatbot',
        'neural network',
    ];

    private const AI_TERMS_MEDIUM = [
        'data science', 'algorithm', 'automation', 'autonomous',
        'predictive', 'generative', 'neural', 'robotics',
    ];

    private const AI_TERMS_WEAK = [
        'analytics', 'big data', 'data-driven',
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
                $response = Http::timeout(6)
                    ->withHeaders([
                        // Some feeds reject the default client agent; use a normal one.
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                        'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
                    ])
                    ->get($url);
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

        $ai = array_values(array_filter($items, static fn (array $i): bool => $i['_score'] > 0));
        $general = array_values(array_filter($items, static fn (array $i): bool => $i['_score'] === 0));

        // AI / analytics items first: strongest signal, then most recent.
        usort($ai, static fn (array $a, array $b): int => ($b['_score'] <=> $a['_score']) ?: ($b['_ts'] <=> $a['_ts']));
        usort($general, static fn (array $a, array $b): int => $b['_ts'] <=> $a['_ts']);

        // Keep the feed AI-focused, but never let it look empty: if there are
        // only a few AI stories, backfill with the most recent general items.
        $result = $ai;
        if (count($result) < self::MIN_ITEMS) {
            $result = array_merge($result, array_slice($general, 0, self::MIN_ITEMS - count($result)));
        }

        return array_values(array_map(static function (array $item): array {
            unset($item['_ts'], $item['_score']);

            return $item;
        }, array_slice($result, 0, self::MAX_ITEMS)));
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
            $score = $this->aiScore($title.' '.$descriptionRaw);

            $out[] = [
                'title' => $title,
                'url' => $link,
                'source' => $source,
                'excerpt' => $excerpt,
                'published_at' => $timestamp ? date(DATE_ATOM, $timestamp) : null,
                'ai' => $score > 0 ? 1 : 0,
                '_ts' => $timestamp ?: 0,
                '_score' => $score,
            ];
        }

        return $out;
    }

    /**
     * Weighted AI-relevance score. Strong terms (e.g. "machine learning") and
     * whole-word AI/LLM/GPT mentions count most; "analytics" alone counts least
     * so a sports-analytics piece never outranks a real AI story.
     */
    private function aiScore(string $text): int
    {
        $haystack = ' '.Str::lower($text).' ';
        $score = 0;

        if (preg_match('/\b(ai|a\.i\.|llm|llms|gpt(?:-?\d+)?|copilot)\b/i', $text) === 1) {
            $score += 3;
        }
        foreach (self::AI_TERMS_STRONG as $term) {
            if (str_contains($haystack, $term)) {
                $score += 3;
            }
        }
        foreach (self::AI_TERMS_MEDIUM as $term) {
            if (str_contains($haystack, $term)) {
                $score += 2;
            }
        }
        foreach (self::AI_TERMS_WEAK as $term) {
            if (str_contains($haystack, $term)) {
                $score += 1;
            }
        }

        return $score;
    }
}
