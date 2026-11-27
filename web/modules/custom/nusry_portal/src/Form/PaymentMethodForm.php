<?php

namespace Drupal\nusry_portal\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\nusry_donations\Service\DonationRepository;
use Drupal\nusry_donations\Service\MockDonationGateway;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simulated payment method selection inside the portal (no card fields stored).
 */
final class PaymentMethodForm extends FormBase {

  /**
   * Constructs a PaymentMethodForm instance.
   */
  public function __construct(
    protected DonationRepository $repository,
    protected UserDataInterface $userData,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nusry_donations.repository'),
      $container->get('user.data'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nusry_portal_payment_method_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUser()->id();
    $currentMethod = (string) ($this->userData->get('nusry_portal', $uid, 'default_payment_method') ?: 'demo_card');

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
        '#value' => $this->t('Simulated provider selection — zero card data collected'),
      ],
      'text' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Switch the simulated payment token associated with your demo account. No real card numbers, CVVs, or bank details are requested or stored.'),
      ],
    ];

    $form['payment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Preferred simulated payment method'),
      '#options' => MockDonationGateway::ALLOWED_METHODS,
      '#default_value' => isset(MockDonationGateway::ALLOWED_METHODS[$currentMethod]) ? $currentMethod : 'demo_card',
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save simulated payment method'),
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $this->currentUser()->id();
    $method = (string) $form_state->getValue('payment_method', 'demo_card');
    if (!isset(MockDonationGateway::ALLOWED_METHODS[$method])) {
      $method = 'demo_card';
    }

    $this->userData->set('nusry_portal', $uid, 'default_payment_method', $method);
    $this->repository->updatePaymentMethodByUser($uid, $method);

    $this->messenger()->addStatus($this->t('Simulated payment method updated (demo only, no provider charged).'));
    $form_state->setRedirect('nusry_portal.dashboard');
  }

}
