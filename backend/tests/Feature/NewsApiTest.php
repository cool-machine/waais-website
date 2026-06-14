<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewsApiTest extends TestCase
{
    private function fakeFeeds(): void
    {
        $kw = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <item>
    <title>Wharton launches new generative AI analytics initiative</title>
    <link>https://knowledge.wharton.upenn.edu/article/ai-analytics</link>
    <description><![CDATA[<p>A look at how <b>machine learning</b> is reshaping business.</p>]]></description>
    <pubDate>Wed, 10 Jun 2026 09:00:00 +0000</pubDate>
  </item>
</channel></rss>
XML;

        $penn = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <item>
    <title>Campus dining hall reopens after renovation</title>
    <link>https://penntoday.upenn.edu/news/dining</link>
    <description>The historic dining hall is back.</description>
    <pubDate>Thu, 11 Jun 2026 09:00:00 +0000</pubDate>
  </item>
</channel></rss>
XML;

        Http::fake([
            'knowledge.wharton.upenn.edu/*' => Http::response($kw, 200, ['Content-Type' => 'application/rss+xml']),
            'penntoday.upenn.edu/*' => Http::response($penn, 200, ['Content-Type' => 'application/rss+xml']),
        ]);
    }

    #[Test]
    public function it_returns_news_items_with_ai_stories_ranked_first(): void
    {
        $this->fakeFeeds();

        $response = $this->getJson('/api/public/news');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['title', 'url', 'source', 'excerpt', 'published_at', 'ai']]]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // The AI/analytics item must rank ahead of the general campus item,
        // even though the campus item is more recent.
        $this->assertSame(1, $data[0]['ai']);
        $this->assertStringContainsString('generative AI', $data[0]['title']);
        $this->assertSame('Knowledge at Wharton', $data[0]['source']);
        $this->assertSame(0, $data[1]['ai']);

        // Excerpt is plain text (HTML stripped).
        $this->assertStringNotContainsString('<', $data[0]['excerpt']);
    }

    #[Test]
    public function it_degrades_gracefully_when_a_feed_fails(): void
    {
        Http::fake([
            'knowledge.wharton.upenn.edu/*' => Http::response('', 500),
            'penntoday.upenn.edu/*' => Http::response(
                '<?xml version="1.0"?><rss version="2.0"><channel><item><title>AI on campus</title><link>https://penntoday.upenn.edu/x</link><description>ok</description></item></channel></rss>',
                200
            ),
        ]);

        $response = $this->getJson('/api/public/news');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Penn Today', $response->json('data.0.source'));
    }
}
