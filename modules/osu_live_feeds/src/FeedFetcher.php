<?php

namespace Drupal\osu_live_feeds;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Fetches and parses an external RSS/Atom feed, cached.
 *
 * The D10 counterpart of D7 live_feeds_load_feed(). D7 shipped eight parser
 * plugins but the four in real use (generic_parser, osu_wordpress, osu_news,
 * osu_announcements) are all plain RSS2/Atom differing only in which elements
 * they surfaced, so one parser covers them; field_feed_type is kept on the
 * node for fidelity, not dispatch.
 */
class FeedFetcher {

  /**
   * Successful fetches are cached this long (D7 cached comparably).
   */
  const CACHE_OK = 1800;

  /**
   * Failures are cached briefly so a downed feed retries soon without
   * hammering the source on every page view.
   */
  const CACHE_FAIL = 300;

  public function __construct(
    protected ClientInterface $httpClient,
    protected CacheBackendInterface $cache,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Returns up to $limit items for a feed URL: title, url, timestamp, summary.
   *
   * @return array[]
   *   Item arrays; empty when the feed is unreachable or unparseable.
   */
  public function fetch(string $url, int $limit): array {
    $limit = max(1, $limit);
    $cid = 'osu_live_feeds:' . md5($url) . ':' . $limit;
    if ($hit = $this->cache->get($cid)) {
      return $hit->data;
    }
    $items = [];
    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 8,
        'headers' => ['Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.1'],
      ]);
      $items = $this->parse((string) $response->getBody(), $limit);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('osu_live_feeds')->warning('Feed fetch failed for @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
    }
    $this->cache->set($cid, $items, time() + ($items ? self::CACHE_OK : self::CACHE_FAIL));
    return $items;
  }

  /**
   * Parses RSS2 / RSS1 / Atom into item arrays.
   */
  protected function parse(string $xml, int $limit): array {
    $previous = libxml_use_internal_errors(TRUE);
    $sx = simplexml_load_string($xml);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$sx) {
      return [];
    }
    if (isset($sx->channel->item)) {
      $nodes = $sx->channel->item;
    }
    elseif (isset($sx->item)) {
      $nodes = $sx->item;
    }
    elseif (isset($sx->entry)) {
      $nodes = $sx->entry;
    }
    else {
      return [];
    }
    $items = [];
    foreach ($nodes as $node) {
      if (count($items) >= $limit) {
        break;
      }
      // RSS <link> is text; Atom carries one or more <link href> elements,
      // where rel="alternate" (or no rel) is the story link.
      $link = trim((string) $node->link);
      if ($link === '' && isset($node->link)) {
        foreach ($node->link as $l) {
          $rel = (string) $l['rel'];
          if ($rel === '' || $rel === 'alternate') {
            $link = trim((string) $l['href']);
            break;
          }
        }
      }
      $date = (string) ($node->pubDate ?? $node->updated ?? $node->published ?? '');
      if ($date === '') {
        $date = (string) ($node->children('http://purl.org/dc/elements/1.1/')->date ?? '');
      }
      $raw_summary = (string) ($node->description ?? $node->summary ?? $node->children('http://purl.org/rss/1.0/modules/content/')->encoded ?? '');
      $summary = trim(html_entity_decode(strip_tags($raw_summary), ENT_QUOTES | ENT_HTML5));
      // Item image, like D7's osu_news parser: the RSS enclosure first, then
      // Media RSS (WordPress), then the first inline image in the summary.
      $image = '';
      if (isset($node->enclosure['url'])) {
        $type = (string) $node->enclosure['type'];
        if ($type === '' || str_starts_with($type, 'image/')) {
          $image = trim((string) $node->enclosure['url']);
        }
      }
      if ($image === '') {
        $media = $node->children('http://search.yahoo.com/mrss/');
        foreach (['thumbnail', 'content'] as $tag) {
          if (isset($media->{$tag}['url'])) {
            $image = trim((string) $media->{$tag}['url']);
            break;
          }
        }
      }
      if ($image === '') {
        // WordPress puts a text-only excerpt in description; the images live
        // in content:encoded. Scan both for the first inline image.
        $html_sources = $raw_summary . (string) ($node->children('http://purl.org/rss/1.0/modules/content/')->encoded ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html_sources, $m)) {
          $image = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }
      }
      $items[] = [
        'title' => trim((string) $node->title) ?: $link,
        'url' => $link,
        'timestamp' => $date !== '' ? (strtotime($date) ?: NULL) : NULL,
        'summary' => $summary !== '' ? Unicode::truncate($summary, 280, TRUE, TRUE) : '',
        'image' => str_starts_with($image, 'http') ? $image : '',
      ];
    }
    return $items;
  }

}
