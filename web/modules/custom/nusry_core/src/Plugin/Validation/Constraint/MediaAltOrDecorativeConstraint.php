<?php

namespace Drupal\nusry_core\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Requires alternative text on non-decorative image media.
 */
#[Constraint(
  id: 'NusryMediaAltOrDecorative',
  label: new TranslatableMarkup('Non-decorative images require alt text', [], ['context' => 'Validation']),
  type: 'entity',
)]
final class MediaAltOrDecorativeConstraint extends SymfonyConstraint {

  /**
   * Violation message when non-decorative image lacks alt text.
   *
   * @var string
   */
  public string $message = 'Provide descriptive alternative text for this image, or mark it as decorative.';

}
