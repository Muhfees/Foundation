<?php

namespace Drupal\nusry_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Displays the fictional foundation's introductory page.
 */
final class WelcomeController extends ControllerBase {

  /**
   * Builds the demo introduction.
   */
  public function page(): array {
    return [
      '#type' => 'container',
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Nusry Foundation is a fictional international medical humanitarian organization, imagined to bring care to people affected by conflict, disease outbreaks and disasters.'),
      ],
      'disclosure' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This demonstration represents no real programmes, patients or fundraising activities.'),
      ],
      '#cache' => ['max-age' => 3600],
    ];
  }

}
