<?php

namespace Drupal\nusry_core\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates the fictional editorial model's cross-field rules.
 */
#[Constraint(id: 'NusryEditorial', label: new TranslatableMarkup('Nusry editorial rules'))]
final class EditorialConstraint extends SymfonyConstraint {}
