<?php

namespace Drupal\nusry_core\Plugin\Validation\Constraint;

use Drupal\media\MediaInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that image media has alt text unless explicitly decorative.
 */
final class MediaAltOrDecorativeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof MediaInterface || !$constraint instanceof MediaAltOrDecorativeConstraint) {
      return;
    }
    if ($value->bundle() !== 'image' || !$value->hasField('field_media_image')) {
      return;
    }
    $decorative = $value->hasField('field_decorative') && (bool) $value->get('field_decorative')->value;
    if ($decorative) {
      return;
    }
    $alt = trim((string) ($value->get('field_media_image')->alt ?? ''));
    if ($alt === '') {
      $this->context->buildViolation($constraint->message)
        ->atPath('field_media_image')
        ->addViolation();
    }
  }

}
