<?php

namespace Drupal\Tests\osu_cas_multisite\Unit;

use Drupal\Component\Utility\UrlHelper;
use Drupal\osu_cas_multisite\Plugin\Filter\CasSafeHtml;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\osu_cas_multisite\Plugin\Filter\CasSafeHtml
 * @group osu_cas_multisite
 */
class CasSafeHtmlTest extends UnitTestCase {

  /**
   * The filter under test.
   */
  protected CasSafeHtml $filter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    UrlHelper::setAllowedProtocols(['http', 'https', 'mailto', 'tel']);
    $this->filter = new CasSafeHtml([], 'osu_cas_safe_html', ['provider' => 'osu_cas_multisite']);
  }

  /**
   * Runs the filter.
   */
  protected function filter(string $html): string {
    return $this->filter->process($html, 'en')->getProcessedText();
  }

  /**
   * Markup with nothing to remove comes back byte for byte.
   *
   * The sample is what profiles hold: inline styles, a table, an image, a
   * media embed, links with targets, and Word/Google paste leftovers.
   */
  public function testUntouchedMarkupIsUnchanged(): void {
    $html = "<p style=\"font-family: Arial; margin-left: 40px; text-align: center;\" class=\"x\">Hi <a href=\"https://oregonstate.edu/\" target=\"_blank\">OSU</a> <a href=\"mailto:a@b.edu\">mail</a></p>\n"
      . "<table border=1 cellpadding=\"5\"><tbody><tr><td width=50%>A<sup>2</sup></td></tr></tbody></table>\n"
      . "<img src=\"/sites/agsci.oregonstate.edu/files/x.jpg\" alt=\"\" width=\"200\" height=\"100\" />\n"
      . "<drupal-media data-entity-type=\"media\" data-entity-uuid=\"c5a7983b-4756-4a68-8a09-491099018299\" data-view-mode=\"default\"></drupal-media>\n"
      . "<div jscontroller=\"x\" data-crx=\"y\"><o:p></o:p><scribe-shadow style=\"position: fixed; z-index: 99\"></scribe-shadow></div>";
    $this->assertSame($html, $this->filter($html));
    $this->assertSame('plain text', $this->filter('plain text'));
    $this->assertSame('', $this->filter(''));
  }

  /**
   * Active elements are removed along with their contents.
   *
   * @dataProvider removedElementProvider
   */
  public function testRemovesElements(string $html, string $gone): void {
    $out = $this->filter("<p>keep</p>$html<p>after</p>");
    $this->assertStringContainsString('keep', $out);
    $this->assertStringContainsString('after', $out);
    $this->assertStringNotContainsStringIgnoringCase($gone, $out);
  }

  /**
   * Elements and the content that must leave with them.
   */
  public static function removedElementProvider(): array {
    return [
      'script' => ['<script>alert(1)</script>', 'alert'],
      'script with src' => ['<script src="https://evil.example/x.js"></script>', 'evil'],
      'uppercase script' => ['<SCRIPT>alert(1)</SCRIPT>', 'alert'],
      'style block' => ['<style>body{display:none}</style>', 'display'],
      'noscript' => ['<noscript><img src="https://t.example/p.gif"></noscript>', 't.example'],
      'template' => ['<template><script>x()</script></template>', 'x()'],
      'iframe' => ['<iframe src="https://www.youtube.com/embed/abc"></iframe>', 'youtube'],
      'object' => ['<object data="x.swf"><param name="movie" value="x.swf"></object>', 'swf'],
      'embed' => ['<embed src="x.swf">', 'swf'],
      'applet' => ['<applet code="X.class"></applet>', 'applet'],
      'form' => ['<form action="https://evil.example/steal"><input name="p"><button>Go</button></form>', 'steal'],
      'bare input' => ['<input type="text" name="x">', '<input'],
      'select' => ['<select><option>a</option></select>', '<option'],
      'textarea' => ['<textarea>t</textarea>', 'textarea'],
      'button' => ['<button>b</button>', 'button'],
      'meta' => ['<meta http-equiv="refresh" content="0;url=https://evil.example">', 'refresh'],
      'link' => ['<link rel="stylesheet" href="https://evil.example/x.css">', 'stylesheet'],
      'base' => ['<base href="https://evil.example/">', 'base'],
      'svg animate' => ['<svg><a href="#"><animate attributeName="href" to="javascript:alert(1)"></animate></a></svg>', 'animate'],
      'svg set' => ['<svg><set attributeName="href" to="javascript:alert(1)"></set></svg>', '<set'],
    ];
  }

  /**
   * Event-handler attributes go, whatever the case, and others stay.
   */
  public function testRemovesEventHandlers(): void {
    $out = $this->filter('<p onclick="x()" ONMOUSEOVER="y()" onfocus="z()" class="k" title="t">a</p><img src="/a.png" onerror="z()" alt="pic">');
    $this->assertSame('<p class="k" title="t">a</p><img src="/a.png" alt="pic">', $out);
  }

  /**
   * Script URLs lose their protocol; web, mail and phone links are kept.
   *
   * @dataProvider urlProvider
   */
  public function testUrls(string $attribute, string $value, bool $kept): void {
    $tag = $attribute === 'src' ? 'img' : 'a';
    $out = $this->filter("<$tag $attribute=\"$value\">x</$tag>");
    if ($kept) {
      $this->assertStringContainsString("$attribute=\"$value\"", $out);
    }
    else {
      $this->assertStringNotContainsStringIgnoringCase('script', $out);
    }
  }

  /**
   * URL attribute values and whether they survive.
   */
  public static function urlProvider(): array {
    return [
      'https' => ['href', 'https://oregonstate.edu/', TRUE],
      'http' => ['href', 'http://example.org/?a=1&amp;b=2', TRUE],
      'mailto' => ['href', 'mailto:someone@oregonstate.edu', TRUE],
      'tel' => ['href', 'tel:+15417371234', TRUE],
      'relative' => ['href', '/people/x', TRUE],
      'anchor' => ['href', '#top', TRUE],
      'image' => ['src', '/sites/agsci.oregonstate.edu/files/x.jpg', TRUE],
      'javascript href' => ['href', 'javascript:alert(1)', FALSE],
      'uppercase' => ['href', 'JAVASCRIPT:alert(1)', FALSE],
      'padded' => ['href', ' javascript:alert(1)', FALSE],
      'entity-encoded' => ['href', 'java&#9;script:alert(1)', FALSE],
      'vbscript' => ['href', 'vbscript:MsgBox(1)', FALSE],
      'data' => ['href', 'data:text/html;base64,PHNjcmlwdD4=', FALSE],
      'image src script' => ['src', 'javascript:alert(1)', FALSE],
    ];
  }

  /**
   * An SVG link's xlink:href is a URL too.
   */
  public function testSvgXlinkHref(): void {
    $out = $this->filter('<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>');
    $this->assertStringNotContainsStringIgnoringCase('javascript', $out);
    $this->assertStringContainsString('<text>x</text>', $out);
  }

  /**
   * The style attribute is left exactly as written, even when odd.
   */
  public function testStyleAttributeIsKept(): void {
    $html = '<p style="position: fixed; top: 0; width: 100%; color: red !important;">x</p>';
    $this->assertSame($html, $this->filter($html));
  }

  /**
   * A value that needs one change keeps everything around it.
   */
  public function testOnlyTheOffendingPartChanges(): void {
    $out = $this->filter('<p style="margin: 0 1em"><strong>Bio</strong> text</p><script>x()</script><ul><li>a</li></ul>');
    $this->assertSame('<p style="margin: 0 1em"><strong>Bio</strong> text</p><ul><li>a</li></ul>', $out);
  }

}
