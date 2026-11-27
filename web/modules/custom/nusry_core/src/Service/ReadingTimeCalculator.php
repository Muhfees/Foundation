<?php

namespace Drupal\nusry_core\Service;

use Drupal\node\NodeInterface;

/**
 * Computes deterministic reading time estimates for editorial stories.
 */
final class ReadingTimeCalculator {

  /**
   * Estimates reading minutes from plain or HTML text.
   */
  public function calculateFromText(string $text, int $wordsPerMinute = 200): int {
    if ($wordsPerMinute < 1) {
      throw new \InvalidArgumentException('Words per minute must be positive.');
    }
    $plain = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($plain === '') {
      return 1;
    }
    $words = str_word_count($plain);
    return max(1, (int) ceil($words / $wordsPerMinute));
  }

  /**
   * Calculates reading minutes across a story node's summary, body and blocks.
   */
  public function calculateForNode(NodeInterface $node): int {
    $chunks = [(string) $node->label()];
    foreach (['field_summary', 'field_body'] as $fieldName) {
      if ($node->hasField($fieldName) && !$node->get($fieldName)->isEmpty()) {
        $chunks[] = (string) $node->get($fieldName)->value;
      }
    }
    if ($node->hasField('field_components') && !$node->get('field_components')->isEmpty()) {
      foreach ($node->get('field_components')->referencedEntities() as $paragraph) {
        foreach (['field_heading', 'field_summary', 'field_body', 'field_quote'] as $subField) {
          if ($paragraph->hasField($subField) && !$paragraph->get($subField)->isEmpty()) {
            $chunks[] = (string) $paragraph->get($subField)->value;
          }
        }
      }
    }
    return $this->calculateFromText(implode(' ', $chunks));
  }

}
