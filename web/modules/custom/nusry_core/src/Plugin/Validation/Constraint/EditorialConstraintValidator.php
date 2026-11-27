<?php

namespace Drupal\nusry_core\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Rejects inconsistent dates, unsafe components and incomplete metadata.
 */
final class EditorialConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    if ($entity->hasField('field_demo') && !$entity->get('field_demo')->value) {
      $this->context->buildViolation('All content in this site must remain labelled as fictional.')->atPath('field_demo')->addViolation();
    }
    if ($entity->hasField('field_start') && !$entity->get('field_start')->isEmpty() && !$entity->get('field_end')->isEmpty()) {
      if ($entity->get('field_end')->value <= $entity->get('field_start')->value) {
        $this->context->buildViolation('End must be after start.')->atPath('field_end')->addViolation();
      }
    }
    if ($entity->hasField('field_timezone') && !in_array($entity->get('field_timezone')->value, \DateTimeZone::listIdentifiers(), TRUE)) {
      $this->context->buildViolation('Choose a valid IANA timezone, such as Asia/Colombo.')->atPath('field_timezone')->addViolation();
    }
    if ($entity->hasField('field_iso_code') && !preg_match('/^[A-Z]{2}$/', $entity->get('field_iso_code')->value ?? '')) {
      $this->context->buildViolation('Use an uppercase two-letter country code.')->atPath('field_iso_code')->addViolation();
    }
    if ($entity->hasField('field_components')) {
      $heroes = 0;
      foreach ($entity->get('field_components')->referencedEntities() as $paragraph) {
        $heroes += $paragraph->bundle() === 'hero' ? 1 : 0;
      }
      if ($heroes > 1) {
        $this->context->buildViolation('A page may have only one hero component.')->atPath('field_components')->addViolation();
      }
    }
    foreach (['field_hero', 'field_media'] as $name) {
      if ($entity->hasField($name) && $entity->bundle() !== 'sensitive_image') {
        foreach ($entity->get($name)->referencedEntities() as $media) {
          if ($media->hasField('field_sensitive') && $media->get('field_sensitive')->value) {
            $this->context->buildViolation('Sensitive media must use the warning-and-reveal component.')->atPath($name)->addViolation();
          }
        }
      }
    }
    if ($entity->getEntityTypeId() === 'media' && $entity->bundle() === 'remote_video' && $entity->hasField('field_captions_confirmed') && !$entity->get('field_captions_confirmed')->value) {
      $this->context->buildViolation('Confirm that this video provides captions.')->atPath('field_captions_confirmed')->addViolation();
    }
  }

}
