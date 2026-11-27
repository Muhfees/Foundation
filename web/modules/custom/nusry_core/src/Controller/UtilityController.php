<?php

namespace Drupal\nusry_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\node\NodeInterface;
use Drupal\nusry_core\Service\EditorialSettings;
use Drupal\nusry_core\Service\StorySelector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides custom 404, styleguide, RSS feed and XML sitemap routes.
 */
final class UtilityController extends ControllerBase {

  /**
   * Constructs a UtilityController instance.
   */
  public function __construct(
    private readonly EditorialSettings $editorialSettings,
    private readonly StorySelector $storySelector,
    private readonly ThemeExtensionList $themeExtensionList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nusry_core.editorial_settings'),
      $container->get('nusry_core.story_selector'),
      $container->get('extension.list.theme'),
    );
  }

  /**
   * Renders the accessible custom 404 recovery page.
   */
  public function notFound(): array {
    return [
      '#theme' => 'nusry_not_found',
      '#cache' => ['max-age' => 3600],
    ];
  }

  /**
   * Renders /styleguide documenting tokens, components and interactive states.
   */
  public function styleguide(): array {
    $themeBase = '/' . $this->themeExtensionList->getPath('nusry');
    return [
      '#theme' => 'nusry_styleguide',
      '#theme_base' => $themeBase,
      '#facts' => $this->editorialSettings->getOrganizationFacts(),
      '#allocation' => $this->editorialSettings->getFundAllocation(),
      '#cache' => ['tags' => ['config:nusry_core.settings']],
    ];
  }

  /**
   * Serves /rss.xml containing published stories only.
   */
  public function rss(Request $request): Response {
    $baseUrl = $request->getSchemeAndHttpHost();
    $stories = $this->storySelector->getPublishedStories(20);

    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"></rss>');
    $channel = $xml->addChild('channel');
    $channel->addChild('title', 'Nusry Foundation (Fictional Demo) — Latest Stories');
    $channel->addChild('link', $baseUrl . '/news');
    $channel->addChild('description', 'Fictional demonstration RSS feed; unpublished drafts are excluded.');
    $channel->addChild('language', 'en');

    foreach ($stories as $story) {
      if (!$story->isPublished()) {
        continue;
      }
      $item = $channel->addChild('item');
      $item->addChild('title', htmlspecialchars($story->label(), ENT_XML1 | ENT_QUOTES, 'UTF-8'));
      $url = $baseUrl . $story->toUrl()->toString();
      $item->addChild('link', htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
      $item->addChild('guid', htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
      $item->addChild('description', htmlspecialchars((string) ($story->get('field_summary')->value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8'));
      $ts = strtotime((string) ($story->get('field_date')->value ?? ''));
      if ($ts !== FALSE) {
        $item->addChild('pubDate', gmdate(DATE_RSS, $ts));
      }
    }

    return new Response((string) $xml->asXML(), 200, [
      'Content-Type' => 'application/rss+xml; charset=utf-8',
      'X-Robots-Tag' => 'noindex, nofollow',
      'Cache-Control' => 'public, max-age=300',
    ]);
  }

  /**
   * Serves /sitemap.xml containing published public routes only.
   */
  public function sitemap(Request $request): Response {
    $baseUrl = $request->getSchemeAndHttpHost();
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

    $staticPaths = ['/', '/news', '/magazine', '/events', '/campaigns', '/search', '/donate', '/styleguide'];
    foreach ($staticPaths as $path) {
      $urlNode = $xml->addChild('url');
      $urlNode->addChild('loc', htmlspecialchars($baseUrl . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('changed', 'DESC')
      ->execute();
    foreach ($storage->loadMultiple($nids) as $node) {
      if (!$node->isPublished() || !$node->access('view')) {
        continue;
      }
      $urlNode = $xml->addChild('url');
      $urlNode->addChild('loc', htmlspecialchars($baseUrl . $node->toUrl()->toString(), ENT_XML1 | ENT_QUOTES, 'UTF-8'));
      $urlNode->addChild('lastmod', gmdate('Y-m-d', (int) $node->getChangedTime()));
    }

    return new Response((string) $xml->asXML(), 200, [
      'Content-Type' => 'application/xml; charset=utf-8',
      'X-Robots-Tag' => 'noindex, nofollow',
      'Cache-Control' => 'public, max-age=300',
    ]);
  }

}
