<?php

namespace Drupal\nusry_forms\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Protected demo contact form stored safely without public exposure or log PII.
 */
final class ContactForm extends FormBase {

  /**
   * Flood limit per window.
   */
  public const FLOOD_LIMIT = 5;

  /**
   * Flood window in seconds.
   */
  public const FLOOD_WINDOW = 900;

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a ContactForm instance.
   */
  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
    protected FloodInterface $flood,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('nusry_forms');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('flood'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nusry_forms_contact_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'nusry-contact-form';
    $form['#cache'] = ['max-age' => 0];

    if ($submittedNotice = $form_state->get('contact_submitted')) {
      $form['notice'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['nusry-alert', 'nusry-alert--info'], 'role' => 'status'],
        'text' => [
          '#markup' => '<p>' . htmlspecialchars((string) $submittedNotice, ENT_QUOTES, 'UTF-8') . '</p>',
        ],
      ];
    }

    $form['sender_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your name (demo)'),
      '#required' => TRUE,
      '#maxlength' => 120,
      '#autocomplete' => 'name',
    ];

    $form['sender_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address (demo)'),
      '#required' => TRUE,
      '#maxlength' => 254,
      '#autocomplete' => 'email',
    ];

    $form['topic'] = [
      '#type' => 'select',
      '#title' => $this->t('Inquiry topic'),
      '#options' => [
        'general' => $this->t('General demonstration inquiry'),
        'programs' => $this->t('Programmes & field briefings'),
        'donor_support' => $this->t('Demo donor services question'),
        'media' => $this->t('Media & communications'),
      ],
      '#default_value' => 'general',
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message (do not include real sensitive data)'),
      '#required' => TRUE,
      '#rows' => 4,
      '#maxlength' => 2000,
    ];

    $form['consent_ack'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand this is a fictional demonstration form and no real outreach is performed.'),
      '#required' => TRUE,
    ];

    // Honeypot decoy field hidden visually and from assistive tech.
    $form['website'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Leave this field blank'),
      '#wrapper_attributes' => ['class' => ['visually-hidden'], 'aria-hidden' => 'true'],
      '#attributes' => ['tabindex' => '-1', 'autocomplete' => 'off'],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Demo Message'),
      '#attributes' => ['class' => ['btn', 'btn--primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('website', '')) !== '') {
      $this->logger->notice('Honeypot blocked automated contact submission.');
      $form_state->setErrorByName('', $this->t('Unable to accept submission.'));
      return;
    }

    $ip = (string) ($this->getRequest()->getClientIp() ?? '127.0.0.1');
    if (!$this->flood->isAllowed('nusry_forms.contact_submit', self::FLOOD_LIMIT, self::FLOOD_WINDOW, $ip)) {
      $form_state->setErrorByName('', $this->t('Too many demo messages submitted recently. Please try again later.'));
      return;
    }

    $email = trim((string) $form_state->getValue('sender_email', ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('sender_email', $this->t('Enter a valid email address.'));
    }

    $message = trim((string) $form_state->getValue('message', ''));
    if (mb_strlen($message) < 10) {
      $form_state->setErrorByName('message', $this->t('Please enter a message of at least 10 characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ip = (string) ($this->getRequest()->getClientIp() ?? '127.0.0.1');
    $this->flood->register('nusry_forms.contact_submit', self::FLOOD_WINDOW, $ip);

    $this->database->insert('nusry_contact_submissions')
      ->fields([
        'sender_name' => mb_substr(trim((string) $form_state->getValue('sender_name')), 0, 120),
        'sender_email' => mb_strtolower(trim((string) $form_state->getValue('sender_email'))),
        'topic' => (string) $form_state->getValue('topic', 'general'),
        'message' => trim((string) $form_state->getValue('message')),
        'consent_ack' => 1,
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();

    // Never log PII (names, emails, or message bodies).
    $this->logger->notice('Demo contact inquiry stored.');

    $form_state->set('contact_submitted', $this->t('Demo inquiry recorded (no email sent). Thank you for testing the fictional Nusry Foundation contact workflow.'));
    $form_state->setRebuild(FALSE);
    $this->messenger()->addStatus($this->t('Demo inquiry recorded (no email sent).'));
  }

}
