<?php

namespace Drupal\nusry_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\node\NodeInterface;
use Drupal\nusry_core\Service\ContentSearchService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides editorial listing, calendar and Search API results routes.
 */
final class ListingController extends ControllerBase {

  /**
   * Constructs a ListingController instance.
   */
  public function __construct(
    private readonly ContentSearchService $searchService,
    private readonly ThemeExtensionList $themeExtensionList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nusry_core.content_search'),
      $container->get('extension.list.theme'),
    );
  }

  /**
   * Renders /news with kind, country, medical issue, sort and pagination.
   */
  public function news(Request $request): array {
    $q = trim((string) $request->query->get('q', ''));
    $kind = (string) $request->query->get('kind', '');
    $country = (string) $request->query->get('country', '');
    $issue = (string) $request->query->get('issue', '');
    $sort = $request->query->get('sort') === 'oldest' ? 'oldest' : 'recent';
    $page = max(0, (int) $request->query->get('page', 0));
    $perPage = 6;

    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'story')
      ->condition('status', NodeInterface::PUBLISHED);

    if ($q !== '') {
      $or = $query->orConditionGroup()
        ->condition('title', $q, 'CONTAINS')
        ->condition('field_summary', $q, 'CONTAINS');
      $query->condition($or);
    }
    if (in_array($kind, ['news', 'story'], TRUE)) {
      $query->condition('field_story_kind', $kind);
    }
    if (ctype_digit($country)) {
      $query->condition('field_countries', (int) $country);
    }
    if (ctype_digit($issue)) {
      $query->condition('field_issues', (int) $issue);
    }

    $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
    $countQuery = clone $query;
    $total = (int) $countQuery->count()->execute();
    $nids = $query->sort('field_date', $direction)
      ->sort('nid', $direction)
      ->range($page * $perPage, $perPage)
      ->execute();

    $thumbs = $this->themeThumbs();
    $items = [];
    foreach (array_values($storage->loadMultiple($nids)) as $i => $node) {
      $ts = strtotime((string) ($node->get('field_date')->value ?? ''));
      $items[] = [
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
        'meta' => strtoupper((string) ($node->get('field_story_kind')->value ?: 'story')) . ($ts !== FALSE ? ' | ' . strtoupper(date('M j, Y', $ts)) : ''),
        'reading_time' => (int) ($node->get('field_reading_time')->value ?: 1),
        'summary' => (string) ($node->get('field_summary')->value ?? ''),
        'image' => $thumbs[$i % count($thumbs)],
      ];
    }

    $options = $this->searchService->getFilterOptions();
    $activeFilters = [];
    if ($q !== '') {
      $activeFilters[] = 'Keyword: ' . $q;
    }
    if (in_array($kind, ['news', 'story'], TRUE)) {
      $activeFilters[] = 'Type: ' . ucfirst($kind);
    }
    if (ctype_digit($country) && isset($options['countries'][(int) $country])) {
      $activeFilters[] = 'Country: ' . $options['countries'][(int) $country];
    }
    if (ctype_digit($issue) && isset($options['issues'][(int) $issue])) {
      $activeFilters[] = 'Medical issue: ' . $options['issues'][(int) $issue];
    }

    return [
      '#theme' => 'nusry_listing',
      '#intro' => 'Browse fictional field dispatches, medical updates and reports. Only published revisions appear here.',
      '#route_path' => '/news',
      '#mode' => 'news',
      '#filters' => [
        'q' => $q,
        'kind' => $kind,
        'country' => $country,
        'issue' => $issue,
        'sort' => $sort,
      ],
      '#options' => $options,
      '#active_filters' => $activeFilters,
      '#total' => $total,
      '#items' => $items,
      '#page' => $page,
      '#pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
      '#empty_message' => 'No published stories match the selected filters.',
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => ['node_list:story'],
      ],
    ];
  }

  /**
   * Renders /magazine filtered to Magazine-category stories and sample reports.
   */
  public function magazine(): array {
    $termStorage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $magTerms = $termStorage->loadByProperties(['vid' => 'story_categories', 'name' => 'Magazine']);
    $magTerm = $magTerms ? reset($magTerms) : NULL;

    $nodeStorage = $this->entityTypeManager()->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'story')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_date', 'DESC');
    if ($magTerm) {
      $query->condition('field_categories', $magTerm->id());
    }
    $thumbs = $this->themeThumbs();
    $items = [];
    foreach (array_values($nodeStorage->loadMultiple($query->execute())) as $i => $node) {
      $ts = strtotime((string) ($node->get('field_date')->value ?? ''));
      $items[] = [
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
        'meta' => 'MAGAZINE' . ($ts !== FALSE ? ' | ' . strtoupper(date('M j, Y', $ts)) : ''),
        'reading_time' => (int) ($node->get('field_reading_time')->value ?: 1),
        'summary' => (string) ($node->get('field_summary')->value ?? ''),
        'image' => $thumbs[$i % count($thumbs)],
      ];
    }

    return [
      '#theme' => 'nusry_listing',
      '#intro' => 'Long-form fictional features and resilient field pharmacy dispatches from the Nusry Magazine collection.',
      '#route_path' => '/magazine',
      '#mode' => 'magazine',
      '#filters' => [],
      '#options' => [],
      '#active_filters' => ['Collection: Magazine'],
      '#total' => count($items),
      '#items' => $items,
      '#page' => 0,
      '#pages' => 1,
      '#empty_message' => 'No published Magazine features are available yet.',
      '#cache' => ['tags' => ['node_list:story']],
    ];
  }

  /**
   * Renders /events with format, virtual/in-person and timeframe filters.
   */
  public function events(Request $request): array {
    $format = (string) $request->query->get('format', '');
    $mode = (string) $request->query->get('mode', '');
    $timeframe = (string) $request->query->get('timeframe', 'all');

    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'event')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_start', 'ASC');

    if (ctype_digit($format)) {
      $query->condition('field_format', (int) $format);
    }
    $nowIso = gmdate('Y-m-d\TH:i:s');
    if ($timeframe === 'upcoming') {
      $query->condition('field_end', $nowIso, '>=');
    }
    elseif ($timeframe === 'past') {
      $query->condition('field_end', $nowIso, '<');
    }

    $items = [];
    foreach ($storage->loadMultiple($query->execute()) as $node) {
      $hasVirtual = !$node->get('field_virtual_url')->isEmpty();
      $hasVenue = !$node->get('field_venue')->isEmpty();
      if ($mode === 'virtual' && !$hasVirtual) {
        continue;
      }
      if ($mode === 'in-person' && !$hasVenue) {
        continue;
      }
      $startTs = strtotime((string) $node->get('field_start')->value);
      $tz = (string) ($node->get('field_timezone')->value ?: 'UTC');
      $formatTerm = $node->get('field_format')->entity;
      $items[] = [
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
        'meta' => ($formatTerm ? strtoupper($formatTerm->label()) : 'EVENT') . ($startTs !== FALSE ? ' | ' . date('M j, Y H:i', $startTs) . ' ' . $tz : ''),
        'location' => $hasVirtual ? 'Virtual briefing' : (string) $node->get('field_venue')->value,
        'accessibility' => (string) $node->get('field_accessibility')->value,
        'summary' => (string) $node->get('field_summary')->value,
      ];
    }

    $formats = [];
    foreach ($this->entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'event_formats']) as $term) {
      $formats[(int) $term->id()] = $term->label();
    }
    asort($formats);

    $activeFilters = [];
    if (ctype_digit($format) && isset($formats[(int) $format])) {
      $activeFilters[] = 'Format: ' . $formats[(int) $format];
    }
    if (in_array($mode, ['virtual', 'in-person'], TRUE)) {
      $activeFilters[] = 'Attendance: ' . ucfirst($mode);
    }
    if (in_array($timeframe, ['upcoming', 'past'], TRUE)) {
      $activeFilters[] = 'When: ' . ucfirst($timeframe);
    }

    return [
      '#theme' => 'nusry_listing',
      '#intro' => 'Accessible list calendar of fictional briefings, community transparency forums and student workshops.',
      '#route_path' => '/events',
      '#mode' => 'events',
      '#filters' => [
        'format' => $format,
        'mode' => $mode,
        'timeframe' => $timeframe,
      ],
      '#options' => ['formats' => $formats],
      '#active_filters' => $activeFilters,
      '#total' => count($items),
      '#items' => $items,
      '#page' => 0,
      '#pages' => 1,
      '#empty_message' => 'No published events match your filter selection.',
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => ['node_list:event'],
      ],
    ];
  }

  /**
   * Renders /campaigns with status filter and direct simulated donate links.
   */
  public function campaigns(Request $request): array {
    $statusFilter = (string) $request->query->get('status', '');
    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'campaign')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_start', 'DESC');
    if (in_array($statusFilter, ['active', 'planned', 'closed'], TRUE)) {
      $query->condition('field_campaign_status', $statusFilter);
    }

    $thumbs = $this->themeThumbs();
    $items = [];
    foreach (array_values($storage->loadMultiple($query->execute())) as $i => $node) {
      $campStatus = strtoupper((string) ($node->get('field_campaign_status')->value ?: 'active'));
      $campId = (string) ($node->get('field_campaign_id')->value ?: 'DEMO');
      $items[] = [
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
        'meta' => "$campStatus | $campId",
        'summary' => (string) ($node->get('field_summary')->value ?? ''),
        'donate_url' => '/donate?campaign=' . rawurlencode($campId),
        'image' => $thumbs[$i % count($thumbs)],
      ];
    }

    $activeFilters = [];
    if (in_array($statusFilter, ['active', 'planned', 'closed'], TRUE)) {
      $activeFilters[] = 'Status: ' . ucfirst($statusFilter);
    }

    return [
      '#theme' => 'nusry_listing',
      '#intro' => 'Explore fictional humanitarian fundraising campaigns. No real donations or matching funds are collected.',
      '#route_path' => '/campaigns',
      '#mode' => 'campaigns',
      '#filters' => ['status' => $statusFilter],
      '#options' => [],
      '#active_filters' => $activeFilters,
      '#total' => count($items),
      '#items' => $items,
      '#page' => 0,
      '#pages' => 1,
      '#empty_message' => 'No published campaigns match that status.',
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => ['node_list:campaign'],
      ],
    ];
  }

  /**
   * Renders /search powered by Search API with facets, excerpts and states.
   */
  public function search(Request $request): array {
    $q = (string) $request->query->get('q', '');
    $type = (string) $request->query->get('type', '');
    $country = (string) $request->query->get('country', '');
    $issue = (string) $request->query->get('issue', '');
    $sort = (string) $request->query->get('sort', trim($q) !== '' ? 'relevance' : 'recent');
    $page = max(0, (int) $request->query->get('page', 0));

    $result = $this->searchService->search($q, [
      'type' => $type,
      'country' => $country,
      'issue' => $issue,
      'sort' => $sort,
    ], $page, 8);

    $options = $this->searchService->getFilterOptions();
    $options['types'] = ContentSearchService::ALLOWED_TYPES;

    $activeFilters = [];
    if (trim($q) !== '') {
      $activeFilters[] = 'Keyword: ' . trim($q);
    }
    if ($type !== '' && isset(ContentSearchService::ALLOWED_TYPES[$type])) {
      $activeFilters[] = 'Type: ' . ContentSearchService::ALLOWED_TYPES[$type];
    }
    if (ctype_digit($country) && isset($options['countries'][(int) $country])) {
      $activeFilters[] = 'Country: ' . $options['countries'][(int) $country];
    }
    if (ctype_digit($issue) && isset($options['issues'][(int) $issue])) {
      $activeFilters[] = 'Medical issue: ' . $options['issues'][(int) $issue];
    }

    return [
      '#theme' => 'nusry_search',
      '#query' => trim($q),
      '#filters' => [
        'type' => $type,
        'country' => $country,
        'issue' => $issue,
        'sort' => $sort,
      ],
      '#options' => $options,
      '#active_filters' => $activeFilters,
      '#status' => $result['status'],
      '#error' => $result['error'],
      '#total' => $result['total'],
      '#items' => $result['items'],
      '#page' => $result['page'],
      '#pages' => $result['pages'],
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => ['node_list', 'search_api_list:editorial_content'],
      ],
    ];
  }

  /**
   * Returns theme editorial thumbnail paths for listing cards.
   *
   * @return string[]
   *   Theme-relative image paths.
   */
  private function themeThumbs(): array {
    $base = '/' . $this->themeExtensionList->getPath('nusry') . '/assets/images/';
    return [
      $base . 'thumb-1.webp',
      $base . 'thumb-2.webp',
      $base . 'thumb-3.webp',
      $base . 'thumb-4.webp',
    ];
  }

}
