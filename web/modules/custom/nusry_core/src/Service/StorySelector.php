<?php

namespace Drupal\nusry_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Queries published editorial stories deterministically with deduplication.
 */
final class StorySelector {

  /**
   * Constructs a StorySelector service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns published stories sorted newest-first, excluding specified IDs.
   *
   * @param int $limit
   *   Maximum number of stories to return.
   * @param int[] $excludeNids
   *   Node IDs to exclude from results.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Ordered list of published story nodes.
   */
  public function getPublishedStories(int $limit = 4, array $excludeNids = []): array {
    if ($limit < 1) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'story')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_date', 'DESC')
      ->sort('nid', 'DESC')
      ->range(0, $limit);
    $cleanExcludes = array_values(array_unique(array_filter(array_map('intval', $excludeNids))));
    if ($cleanExcludes !== []) {
      $query->condition('nid', $cleanExcludes, 'NOT IN');
    }
    $nids = $query->execute();
    if ($nids === []) {
      return [];
    }
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    return array_values($nodes);
  }

  /**
   * Selects one featured story plus deduplicated supporting stories.
   *
   * @return array{featured: ?\Drupal\node\NodeInterface, supporting: \Drupal\node\NodeInterface[]}
   *   Featured and supporting published stories.
   */
  public function getFeaturedAndSupporting(int $supportingCount = 4, ?int $curatedFeaturedNid = NULL): array {
    $featured = NULL;
    $storage = $this->entityTypeManager->getStorage('node');
    if ($curatedFeaturedNid !== NULL) {
      $candidate = $storage->load($curatedFeaturedNid);
      if ($candidate instanceof NodeInterface && $candidate->bundle() === 'story' && $candidate->isPublished() && $candidate->access('view')) {
        $featured = $candidate;
      }
    }
    if ($featured === NULL) {
      $first = $this->getPublishedStories(1);
      $featured = $first[0] ?? NULL;
    }
    $exclude = $featured instanceof NodeInterface ? [(int) $featured->id()] : [];
    return [
      'featured' => $featured,
      'supporting' => $this->getPublishedStories($supportingCount, $exclude),
    ];
  }

}
