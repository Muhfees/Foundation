<?php

namespace Drupal\nusry_core\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;
use Drupal\search_api\SearchApiException;

/**
 * Queries the Search API editorial index with safe escaped excerpts.
 */
final class ContentSearchService {

  /**
   * Allowed public content types for search filtering.
   */
  public const ALLOWED_TYPES = [
    'story' => 'News & Stories',
    'country' => 'Country Profiles',
    'medical_issue' => 'Medical Issues',
    'event' => 'Events',
    'campaign' => 'Campaigns',
    'report' => 'Reports',
    'flexible_page' => 'Pages',
  ];

  /**
   * Constructs a ContentSearchService instance.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Executes a Search API query against the editorial_content index.
   *
   * @param string $queryText
   *   User search keywords.
   * @param array<string, string> $filters
   *   Optional type, country, issue, and sort filters.
   * @param int $page
   *   Zero-based page number.
   * @param int $perPage
   *   Items per page.
   *
   * @return array<string, mixed>
   *   Structured search outcome with status, total, items and error metadata.
   */
  public function search(string $queryText, array $filters = [], int $page = 0, int $perPage = 10): array {
    $cleanQuery = trim($queryText);
    $page = max(0, $page);
    $perPage = max(1, min(24, $perPage));

    if (mb_strlen($cleanQuery) > 200) {
      return [
        'status' => 'validation_error',
        'error' => 'Search keywords must be 200 characters or fewer.',
        'total' => 0,
        'items' => [],
        'page' => 0,
        'pages' => 0,
      ];
    }

    $typeFilter = $filters['type'] ?? '';
    if ($typeFilter !== '' && !isset(self::ALLOWED_TYPES[$typeFilter])) {
      return [
        'status' => 'validation_error',
        'error' => 'Selected content type filter is invalid.',
        'total' => 0,
        'items' => [],
        'page' => 0,
        'pages' => 0,
      ];
    }

    try {
      /** @var \Drupal\search_api\IndexInterface|null $index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load('editorial_content');
      if ($index === NULL || !$index->status()) {
        return [
          'status' => 'unavailable',
          'error' => 'The search index is temporarily unavailable. Please browse News & Stories or Events instead.',
          'total' => 0,
          'items' => [],
          'page' => 0,
          'pages' => 0,
        ];
      }

      $query = $index->query();
      if ($cleanQuery !== '') {
        $query->keys($cleanQuery);
      }
      if ($typeFilter !== '') {
        $query->addCondition('type', $typeFilter);
      }
      if (!empty($filters['country']) && ctype_digit((string) $filters['country'])) {
        $query->addCondition('field_countries', (int) $filters['country']);
      }
      if (!empty($filters['issue']) && ctype_digit((string) $filters['issue'])) {
        $query->addCondition('field_issues', (int) $filters['issue']);
      }

      $sort = $filters['sort'] ?? ($cleanQuery !== '' ? 'relevance' : 'recent');
      if ($sort === 'recent') {
        $query->sort('field_date', 'DESC')->sort('created', 'DESC');
      }
      else {
        $query->sort('search_api_relevance', 'DESC')->sort('created', 'DESC');
      }

      $query->range($page * $perPage, $perPage);
      $resultSet = $query->execute();
      $total = (int) $resultSet->getResultCount();

      $items = [];
      foreach ($resultSet->getResultItems() as $resultItem) {
        $entity = $resultItem->getOriginalObject()?->getValue();
        if (!$entity instanceof NodeInterface || !$entity->isPublished() || !$entity->access('view')) {
          continue;
        }
        $summary = (string) ($entity->get('field_summary')->value ?? '');
        $body = $entity->hasField('field_body') ? (string) ($entity->get('field_body')->value ?? '') : '';
        $date = $this->resolveDateLabel($entity);
        $items[] = [
          'nid' => (int) $entity->id(),
          'title' => $entity->label(),
          'url' => $entity->toUrl()->toString(),
          'bundle' => $entity->bundle(),
          'type_label' => self::ALLOWED_TYPES[$entity->bundle()] ?? ucfirst($entity->bundle()),
          'date' => $date,
          'excerpt' => $this->highlightExcerpt($summary !== '' ? $summary : $body, $cleanQuery),
        ];
      }

      $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
      return [
        'status' => $total === 0 ? 'empty' : 'ok',
        'error' => '',
        'total' => $total,
        'items' => $items,
        'page' => $page,
        'pages' => $pages,
      ];
    }
    catch (SearchApiException | \Throwable) {
      return [
        'status' => 'unavailable',
        'error' => 'The search index is temporarily unavailable. Please browse News & Stories or Events instead.',
        'total' => 0,
        'items' => [],
        'page' => 0,
        'pages' => 0,
      ];
    }
  }

  /**
   * Returns published countries and medical issues for filter selects.
   *
   * @return array{countries: array<int, string>, issues: array<int, string>}
   *   Keyed entity labels.
   */
  public function getFilterOptions(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $countries = [];
    foreach ($storage->loadByProperties(['type' => 'country', 'status' => 1]) as $node) {
      $countries[(int) $node->id()] = $node->label();
    }
    asort($countries);

    $issues = [];
    foreach ($storage->loadByProperties(['type' => 'medical_issue', 'status' => 1]) as $node) {
      $issues[(int) $node->id()] = $node->label();
    }
    asort($issues);

    return [
      'countries' => $countries,
      'issues' => $issues,
    ];
  }

  /**
   * Builds an HTML-escaped excerpt with safe <mark> highlights.
   */
  public function highlightExcerpt(string $rawText, string $queryText): Markup {
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($rawText)) ?? '');
    if ($plain === '') {
      return Markup::create('');
    }
    $snippet = mb_strlen($plain) > 220 ? mb_substr($plain, 0, 217) . '...' : $plain;
    $escaped = Html::escape($snippet);
    $terms = array_values(array_filter(
      preg_split('/\s+/', trim($queryText)) ?: [],
      static fn(string $t): bool => mb_strlen($t) >= 2
    ));
    if ($terms !== []) {
      $pattern = '/(' . implode('|', array_map(static fn(string $t): string => preg_quote(Html::escape($t), '/'), $terms)) . ')/iu';
      $highlighted = preg_replace($pattern, '<mark>$1</mark>', $escaped);
      if ($highlighted !== NULL) {
        $escaped = $highlighted;
      }
    }
    return Markup::create($escaped);
  }

  /**
   * Resolves a concise display date for a search result node.
   */
  private function resolveDateLabel(NodeInterface $node): string {
    foreach (['field_date', 'field_start', 'field_reviewed'] as $field) {
      if ($node->hasField($field) && !$node->get($field)->isEmpty()) {
        $ts = strtotime((string) $node->get($field)->value);
        if ($ts !== FALSE) {
          return strtoupper(date('M j, Y', $ts));
        }
      }
    }
    return '';
  }

}
