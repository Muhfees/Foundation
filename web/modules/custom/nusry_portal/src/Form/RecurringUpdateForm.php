<?php

namespace Drupal\nusry_portal\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\nusry_donations\Service\DonationRepository;
use Drupal\nusry_donations\Service\MockDonationGateway;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Allows the owning demo donor to adjust or pause a simulated recurring gift.
 */
final class RecurringUpdateForm extends FormBase {

  /**
   * Constructs a RecurringUpdateForm instance.
   */
  public function __construct(
    protected DonationRepository $repository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nusry_donations.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nusry_portal_recurring_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $reference = ''): array {
    $record = $this->loadOwnedRecord($reference);
    $form_state->set('reference', $reference);

    $form['#prefix'] = '<div class="site-main--inner">';
    $form['#suffix'] = '</div>';
    $form['#cache'] = ['max-age' => 0];

    $form['notice'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['demo-notice-banner']],
      'pill' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['demo-pill']],
        '#value' => $this->t('Demo recurring management — no real billing'),
      ],
      'text' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Managing simulated plan @ref for programme @camp.', [
          '@ref' => $record['reference'],
          '@camp' => $record['campaign_id'],
        ]),
      ],
    ];

    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Simulated amount (@currency)', ['@currency' => $record['currency']]),
      '#min' => 1,
      '#max' => 100000,
      '#step' => '0.01',
      '#default_value' => number_format(((int) $record['amount_minor']) / 100, 2, '.', ''),
      '#required' => TRUE,
    ];

    $form['frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Frequency'),
      '#options' => [
        'monthly' => $this->t('Monthly (simulated)'),
        'once' => $this->t('Convert to completed one-time record'),
      ],
      '#default_value' => $record['frequency'],
      '#required' => TRUE,
    ];

    $form['recurring_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep simulated monthly plan active'),
      '#default_value' => (int) $record['recurring_active'] === 1,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save simulated plan changes'),
      '#attributes' => ['class' => ['btn', 'btn--primary']],
    ];
    $form['actions']['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to portal'),
      '#url' => Url::fromRoute('nusry_portal.dashboard'),
      '#attributes' => ['class' => ['btn', 'btn--outline-dark']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $minor = (int) round(((float) $form_state->getValue('amount', 0)) * 100);
    if ($minor < MockDonationGateway::MIN_MINOR || $minor > MockDonationGateway::MAX_MINOR) {
      $form_state->setErrorByName('amount', $this->t('Amount must be between 1.00 and 100,000.00.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $reference = (string) $form_state->get('reference');
    $this->loadOwnedRecord($reference);
    $uid = (int) $this->currentUser()->id();
    $amountMinor = (int) round(((float) $form_state->getValue('amount')) * 100);
    $frequency = (string) $form_state->getValue('frequency', 'monthly');
    $active = $frequency === 'monthly' && (bool) $form_state->getValue('recurring_active', FALSE);

    $this->repository->updateRecurring($reference, $uid, $amountMinor, $frequency, $active);
    $this->messenger()->addStatus($this->t('Simulated recurring gift @ref updated (no real billing change made).', [
      '@ref' => $reference,
    ]));
    $form_state->setRedirect('nusry_portal.dashboard');
  }

  /**
   * Loads a gift record after strict ownership verification.
   *
   * @return array<string, mixed>
   *   Owned donation record.
   */
  private function loadOwnedRecord(string $reference): array {
    $record = $this->repository->findByReference($reference);
    if ($record === NULL) {
      throw new NotFoundHttpException();
    }
    $uid = (int) $this->currentUser()->id();
    if ($uid < 1 || (int) $record['uid'] !== $uid) {
      throw new AccessDeniedHttpException('You are not authorized to modify this simulated gift.');
    }
    return $record;
  }

}
