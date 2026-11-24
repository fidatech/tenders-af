<?php

namespace App\Support;

/**
 * Known robots and crawlers, recognised by the name in their user agent. Used to keep them out of
 * the visitor figures and to show them apart on Admin > Growth.
 */
class Robots
{
    public const TYPES = [
        'search' => 'Search engines',
        'ai' => 'AI crawlers and assistants',
        'seo' => 'SEO and marketing tools',
        'social' => 'Link previews (social and chat apps)',
        'monitor' => 'Monitoring and speed tests',
        'script' => 'Scripts and programs',
        'other' => 'Other robots',
    ];

    /** [type, name, pattern]: the first that matches wins, so the specific ones come first. */
    private const LIST = [
        ['ai', 'OpenAI', '/GPTBot|ChatGPT-User|OAI-SearchBot/i'],
        ['ai', 'Anthropic', '/ClaudeBot|Claude-User|Claude-SearchBot|Claude-Web|anthropic-ai/i'],
        ['ai', 'Perplexity', '/PerplexityBot|Perplexity-User/i'],
        ['ai', 'Common Crawl', '/CCBot/i'],
        ['ai', 'ByteDance', '/Bytespider|TikTokSpider/i'],
        ['ai', 'Amazon', '/Amazonbot/i'],
        ['ai', 'Meta AI', '/meta-externalagent|meta-externalfetcher|FacebookBot/i'],
        ['ai', 'Google AI', '/Google-Extended|GoogleAgent|Gemini/i'],
        ['ai', 'Apple AI', '/Applebot-Extended/i'],
        ['ai', 'Cohere', '/cohere-ai|cohere-training/i'],
        ['ai', 'Mistral', '/MistralAI-User/i'],
        ['ai', 'You.com', '/YouBot/i'],
        ['ai', 'DuckDuckGo AI', '/DuckAssistBot/i'],
        ['ai', 'Diffbot', '/Diffbot/i'],
        ['search', 'Google', '/Googlebot|Google-InspectionTool|GoogleOther|Storebot-Google|AdsBot-Google|Mediapartners-Google|APIs-Google|FeedFetcher-Google|Google-Read-Aloud/i'],
        ['search', 'Bing', '/bingbot|BingPreview|msnbot|adidxbot/i'],
        ['search', 'Yandex', '/Yandex/i'],
        ['search', 'Baidu', '/Baiduspider/i'],
        ['search', 'DuckDuckGo', '/DuckDuckBot|DuckDuckGo-Favicons/i'],
        ['search', 'Apple', '/Applebot/i'],
        ['search', 'Petal (Huawei)', '/PetalBot|AspiegelBot/i'],
        ['search', 'Seznam', '/SeznamBot/i'],
        ['search', 'Sogou', '/Sogou/i'],
        ['search', 'Naver', '/Yeti\//i'],
        ['search', 'Qwant', '/Qwantbot|Qwantify/i'],
        ['search', 'Mojeek', '/MojeekBot/i'],
        ['search', 'Yahoo', '/Slurp/i'],
        ['seo', 'Ahrefs', '/AhrefsBot|AhrefsSiteAudit/i'],
        ['seo', 'Semrush', '/SemrushBot|SiteAuditBot|SplitSignalBot/i'],
        ['seo', 'Majestic', '/MJ12bot/i'],
        ['seo', 'Moz', '/DotBot|rogerbot/i'],
        ['seo', 'Screaming Frog', '/Screaming Frog/i'],
        ['seo', 'DataForSEO', '/DataForSeoBot/i'],
        ['seo', 'Serpstat', '/serpstatbot/i'],
        ['seo', 'BLEXBot', '/BLEXBot/i'],
        ['seo', 'Babbar', '/Barkrowler/i'],
        ['seo', 'Seekport', '/SeekportBot/i'],
        ['seo', 'Dataprovider', '/Dataprovider/i'],
        ['seo', 'Censys', '/CensysInspect/i'],
        ['seo', 'Expanse', '/Expanse/i'],
        ['social', 'Facebook', '/facebookexternalhit|Facebot|facebookcatalog/i'],
        ['social', 'X (Twitter)', '/Twitterbot/i'],
        ['social', 'LinkedIn', '/LinkedInBot/i'],
        ['social', 'WhatsApp', '/WhatsApp/i'],
        ['social', 'Telegram', '/TelegramBot/i'],
        ['social', 'Slack', '/Slackbot|Slack-ImgProxy/i'],
        ['social', 'Discord', '/Discordbot/i'],
        ['social', 'Pinterest', '/Pinterest/i'],
        ['social', 'Reddit', '/redditbot/i'],
        ['social', 'Viber', '/Viber/i'],
        ['social', 'Skype', '/SkypeUriPreview/i'],
        ['social', 'Microsoft Teams', '/MicrosoftPreview|Teams\//i'],
        ['social', 'Embedly', '/Embedly/i'],
        ['social', 'Iframely', '/Iframely/i'],
        ['monitor', 'Lighthouse', '/Lighthouse|Chrome-Lighthouse/i'],
        ['monitor', 'PageSpeed Insights', '/PageSpeed|PTST/i'],
        ['monitor', 'GTmetrix', '/GTmetrix/i'],
        ['monitor', 'UptimeRobot', '/UptimeRobot/i'],
        ['monitor', 'Pingdom', '/Pingdom/i'],
        ['monitor', 'StatusCake', '/StatusCake/i'],
        ['monitor', 'Site24x7', '/Site24x7/i'],
        ['monitor', 'Better Stack', '/Better ?Uptime|BetterStack/i'],
        ['monitor', 'Uptime Kuma', '/Uptime-Kuma/i'],
        ['monitor', 'HetrixTools', '/HetrixTools/i'],
        ['monitor', 'Datadog', '/Datadog/i'],
        ['monitor', 'New Relic', '/NewRelic/i'],
        ['monitor', 'W3C validator', '/W3C_Validator|W3C-checklink|Validator\.nu/i'],
        ['script', 'Headless browser', '/HeadlessChrome|PhantomJS|Puppeteer|Playwright|Selenium|Electron/i'],
        ['script', 'curl', '/^curl\//i'],
        ['script', 'Wget', '/Wget/i'],
        ['script', 'Python', '/python-requests|python-urllib|Python\/|aiohttp|httpx|Scrapy/i'],
        ['script', 'Go', '/Go-http-client|Go http/i'],
        ['script', 'Java', '/Java\/|Apache-HttpClient|okhttp/i'],
        ['script', 'Node.js', '/axios|node-fetch|undici|got \(|node\//i'],
        ['script', 'PHP', '/GuzzleHttp|PHP\//i'],
        ['script', 'Postman', '/PostmanRuntime/i'],
        ['script', 'Perl', '/libwww-perl/i'],
        ['script', 'Ruby', '/Ruby/i'],
        ['script', 'Wappalyzer', '/Wappalyzer/i'],
        ['other', 'Internet Archive', '/archive\.org_bot|ia_archiver/i'],
        // the usual signature of a crawler: a contact address, or a name like "SomethingBot/1.0"
        ['other', 'Other crawler', '/\+https?:\/\/|compatible;[^)]*(bot|crawl|spider)|(bot|spider|crawler)\/\d|crawler|spider|scraper|fetcher/i'],
    ];

    /** The robot in a user agent: ['type' => ..., 'name' => ...], or null for an ordinary browser. */
    public static function detect(?string $agent): ?array
    {
        $agent = trim((string) $agent);
        if ($agent === '') {
            return ['type' => 'script', 'name' => 'No browser name'];
        }
        foreach (self::LIST as [$type, $name, $pattern]) {
            if (preg_match($pattern, $agent)) {
                return ['type' => $type, 'name' => $name];
            }
        }
        // a browser sends a name like "Mozilla/5.0 (...)"; anything else is a program
        if (! str_starts_with($agent, 'Mozilla/') && ! str_starts_with($agent, 'Opera/')) {
            return ['type' => 'script', 'name' => 'Other program'];
        }

        return null;
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type] ?? self::TYPES['other'];
    }
}
