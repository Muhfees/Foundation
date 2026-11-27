<?php

namespace Drupal\nusry_donations\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\nusry_donations\Service\DonationGatewayInterface;
use Drupal\nusry_donations\Service\MockDonationGateway;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interactive Phase 6 simulated donation form (no real payments collected).
 */
final class DonationForm extends FormBase {

  /**
   * Preset donation options in minor units.
   */
  private const PRESETS = [
    '3500' => '$35',
    '7500' => '$75',
    '15000' => '$150',
    '30000' => '$300',
    'custom' => 'Custom amount',
  ];

  /**
   * Constructs a DonationForm instance.
   */
  public function __construct(
    protected DonationGatewayInterface $gateway,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nusry_donations.gateway'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nusry_donations_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->getRequest();
    $campaigns = $this->gateway instanceof MockDonationGateway
      ? $this->gateway->getAllowlistedCampaigns()
      : ['DEMO-MAT-2025' => 'Emergency Maternal Health Fund (Demo)'];

    $queryCampaign = (string) $request->query->get('campaign', '');
    $defaultCampaign = isset($campaigns[$queryCampaign]) ? $queryCampaign : (string) array_key_first($campaigns);

    $queryFrequency = (string) $request->query->get('frequency', 'monthly');
    $defaultFrequency = in_array($queryFrequency, ['once', 'monthly'], TRUE) ? $queryFrequency : 'monthly';

    $queryAmountMinor = (int) $request->query->get('amount', 7500);
    if ($queryAmountMinor < MockDonationGateway::MIN_MINOR || $queryAmountMinor > MockDonationGateway::MAX_MINOR) {
      $queryAmountMinor = 7500;
    }
    $queryAmountKey = (string) $queryAmountMinor;
    $defaultPreset = isset(self::PRESETS[$queryAmountKey]) ? $queryAmountKey : 'custom';
    $defaultCustom = $defaultPreset === 'custom' ? number_format($queryAmountMinor / 100, 2, '.', '') : '';

    $idempotencyKey = (string) $form_state->get('idempotency_key');
    if ($idempotencyKey === '') {
      $idempotencyKey = Crypt::randomBytesBase64(16);
      $form_state->set('idempotency_key', $idempotencyKey);
    }

    $currentUser = $this->currentUser();
    $defaultName = $currentUser->isAuthenticated() ? (string) $currentUser->getDisplayName() : '';
    $defaultEmail = $currentUser->isAuthenticated() ? (string) ($currentUser->getEmail() ?? '') : '';

    $form['#prefix'] = '<div class="site-main--inner nusry-donate-page">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'][] = 'nusry-donate-form';
    $form['#attributes']['data-analytics-form'] = 'donation';
    $form['#cache'] = ['contexts' => ['url.query_args', 'user'], 'max-age' => 0];

    $form['demo_banner'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['demo-notice-banner'], 'role' => 'note'],
      'pill' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['demo-pill']],
        '#value' => $this->t('Demo only — no charge made'),
      ],
      'text' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This interactive checkout simulates gift flows and sample receipts for a fictional organization. Do not enter real payment details or sensitive personal information.'),
      ],
    ];

    if ($outcomeNotice = $form_state->get('outcome_notice')) {
      $form['outcome_notice'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nusry-alert', 'nusry-alert--warning'],
          'role' => 'status',
          'aria-live' => 'polite',
        ],
        'message' => [
          '#markup' => '<p>' . htmlspecialchars((string) $outcomeNotice, ENT_QUOTES, 'UTF-8') . '</p>',
        ],
      ];
    }

    $form['idempotency_key'] = [
      '#type' => 'hidden',
      '#value' => $idempotencyKey,
    ];

    $form['gift_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['nusry-form-card']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('1. Choose your simulated gift'),
      ],
    ];

    $form['gift_card']['frequency'] = [
      '#type' => 'radios',
      '#title' => $this->t('Gift frequency'),
      '#options' => [
        'monthly' => $this->t('Monthly (simulated recurring)'),
        'once' => $this->t('One-time (simulated)'),
      ],
      '#default_value' => $defaultFrequency,
      '#required' => TRUE,
    ];

    $form['gift_card']['currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Demo currency'),
      '#options' => [
        'USD' => $this->t('USD ($)'),
        'EUR' => $this->t('EUR (€)'),
        'GBP' => $this->t('GBP (£)'),
      ],
      '#default_value' => 'USD',
      '#required' => TRUE,
    ];

    $form['gift_card']['preset_amount'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select amount'),
      '#options' => [
        '3500' => $this->t('$35'),
        '7500' => $this->t('$75'),
        '15000' => $this->t('$150'),
        '30000' => $this->t('$300'),
        'custom' => $this->t('Custom amount'),
      ],
      '#default_value' => $defaultPreset,
      '#required' => TRUE,
    ];

    $form['gift_card']['custom_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom amount (1.00 to 100,000.00)'),
      '#min' => 1,
      '#max' => 100000,
      '#step' => '0.01',
      '#default_value' => $defaultCustom,
      '#states' => [
        'visible' => [
          ':input[name="preset_amount"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $form['gift_card']['campaign_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Designated programme (demo)'),
      '#options' => $campaigns,
      '#default_value' => $defaultCampaign,
      '#required' => TRUE,
    ];

    $form['gift_card']['tribute_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Dedication (optional)'),
      '#options' => [
        'none' => $this->t('No dedication'),
        'honor' => $this->t('In honor of someone'),
        'memory' => $this->t('In memory of someone'),
      ],
      '#default_value' => 'none',
    ];

    $form['gift_card']['tribute_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Honoree name (demo)'),
      '#maxlength' => 120,
      '#states' => [
        'visible' => [
          ':input[name="tribute_type"]' => [
            ['value' => 'honor'],
            ['value' => 'memory'],
          ],
        ],
      ],
    ];

    $form['gift_card']['employer_match'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Simulate employer matching gift inquiry (fictional demo)'),
      '#default_value' => 0,
    ];

    $form['supporter_card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['nusry-form-card']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('2. Demo supporter details & payment method'),
      ],
    ];

    $form['supporter_card']['donor_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full name (demo)'),
      '#required' => TRUE,
      '#maxlength' => 120,
      '#default_value' => $defaultName,
      '#autocomplete' => 'name',
    ];

    $form['supporter_card']['donor_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email for sample receipt (use @example.test in demos)'),
      '#required' => TRUE,
      '#maxlength' => 254,
      '#default_value' => $defaultEmail,
      '#autocomplete' => 'email',
    ];

    $form['supporter_card']['payment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Simulated payment method (no real card fields collected)'),
      '#options' => MockDonationGateway::ALLOWED_METHODS,
      '#default_value' => 'demo_card',
      '#required' => TRUE,
      '#description' => $this->t('No card numbers, CVV codes, or bank account credentials are accepted or stored on this demo site.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['nusry-donate-actions']],
    ];

    $form['actions']['complete'] = [
      '#type' => 'submit',
      '#name' => 'complete',
      '#value' => $this->t('Complete Demo Donation'),
      '#attributes' => ['class' => ['btn', 'btn--primary']],
    ];

    $form['actions']['simulate_failure'] = [
      '#type' => 'submit',
      '#name' => 'simulate_failure',
      '#value' => $this->t('Simulate Payment Failure'),
      '#attributes' => ['class' => ['btn', 'btn--outline-dark']],
    ];

    $form['actions']['cancel_checkout'] = [
      '#type' => 'submit',
      '#name' => 'cancel_checkout',
      '#value' => $this->t('Cancel Demo Checkout'),
      '#attributes' => ['class' => ['btn', 'btn--outline-dark']],
      '#limit_validation_errors' => [],
      '#submit' => ['::handleCancelSubmit'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? 'complete');
    if ($trigger === 'cancel_checkout') {
      return;
    }

    $amountMinor = $this->resolveAmountMinor($form_state);
    if ($amountMinor === NULL) {
      $form_state->setErrorByName('custom_amount', $this->t('Enter a valid demo amount between 1.00 and 100,000.00.'));
    }

    $tributeType = (string) $form_state->getValue('tribute_type', 'none');
    $tributeName = trim((string) $form_state->getValue('tribute_name', ''));
    if ($tributeType !== 'none' && $tributeName === '') {
      $form_state->setErrorByName('tribute_name', $this->t('Enter an honoree name for a dedication gift.'));
    }

    $email = trim((string) $form_state->getValue('donor_email', ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('donor_email', $this->t('Enter a valid email address for the sample receipt.'));
    }
  }

  /**
   * Handles explicit checkout cancellation without requiring field validation.
   */
  public function handleCancelSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->set('idempotency_key', Crypt::randomBytesBase64(16));
    $form_state->set('outcome_notice', $this->t('Demo checkout cancelled. No charge was made. You can adjust your demo choices and try again anytime.'));
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? 'complete');
    $outcome = $trigger === 'simulate_failure' ? 'failed' : 'completed';

    $amountMinor = $this->resolveAmountMinor($form_state) ?? 7500;
    try {
      $result = $this->gateway->process([
        'idempotency_key' => (string) $form_state->getValue('idempotency_key'),
        'uid' => (int) $this->currentUser()->id(),
        'donor_name' => (string) $form_state->getValue('donor_name'),
        'donor_email' => (string) $form_state->getValue('donor_email'),
        'amount_minor' => $amountMinor,
        'currency' => (string) $form_state->getValue('currency'),
        'frequency' => (string) $form_state->getValue('frequency'),
        'campaign_id' => (string) $form_state->getValue('campaign_id'),
        'tribute_type' => (string) $form_state->getValue('tribute_type', 'none'),
        'tribute_name' => (string) $form_state->getValue('tribute_name', ''),
        'employer_match' => (bool) $form_state->getValue('employer_match', FALSE),
        'payment_method' => (string) $form_state->getValue('payment_method', 'demo_card'),
        'outcome' => $outcome,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $form_state->setErrorByName('', $this->t('@message', ['@message' => $e->getMessage()]));
      return;
    }

    if ($result['status'] !== 'completed') {
      $form_state->set('idempotency_key', Crypt::randomBytesBase64(16));
      $form_state->set('outcome_notice', $this->t('Simulated payment failed (reference @ref). No charge was made. Review your details and click Complete Demo Donation to retry.', [
        '@ref' => $result['reference'],
      ]));
      $form_state->setRebuild(TRUE);
      return;
    }

    // Bind the receipt reference to the current session so anonymous visitors
    // can inspect their own receipt while other visitors receive 403.
    $session = $this->getRequest()->getSession();
    $owned = $session->get('nusry_donations_refs', []);
    if (!in_array($result['reference'], $owned, TRUE)) {
      $owned[] = $result['reference'];
      $session->set('nusry_donations_refs', array_slice($owned, -20));
    }

    $form_state->setRedirect('nusry_donations.receipt', ['reference' => $result['reference']]);
  }

  /**
   * Resolves validated integer minor units from preset or custom input.
   */
  private function resolveAmountMinor(FormStateInterface $form_state): ?int {
    $preset = (string) $form_state->getValue('preset_amount', '7500');
    if ($preset !== 'custom') {
      $minor = (int) $preset;
      return ($minor >= MockDonationGateway::MIN_MINOR && $minor <= MockDonationGateway::MAX_MINOR) ? $minor : NULL;
    }

    $rawCustom = trim((string) $form_state->getValue('custom_amount', ''));
    if ($rawCustom === '' || !is_numeric($rawCustom)) {
      return NULL;
    }
    $minor = (int) round(((float) $rawCustom) * 100);
    if ($minor < MockDonationGateway::MIN_MINOR || $minor > MockDonationGateway::MAX_MINOR) {
      return NULL;
    }
    return $minor;
  }

}
