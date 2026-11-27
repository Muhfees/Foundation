<?php

namespace Drupal\nusry_donations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\nusry_donations\Service\DonationRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders server-verified private sample receipts for simulated donations.
 */
final class ReceiptController extends ControllerBase {

  /**
   * Constructs a ReceiptController instance.
   */
  public function __construct(
    private readonly DonationRepository $repository,
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
   * Displays or downloads a sample receipt only for authorized owners.
   */
  public function view(string $reference, Request $request): array|Response {
    $record = $this->repository->findByReference($reference);
    if (!$record) {
      throw new NotFoundHttpException();
    }

    $uid = (int) $this->currentUser()->id();
    $sessionRefs = $request->getSession()->get('nusry_donations_refs', []);
    $isOwner = ($uid > 0 && (int) $record['uid'] === $uid)
      || in_array($reference, $sessionRefs, TRUE)
      || $this->currentUser()->hasPermission('access nusry donor services');

    if (!$isOwner) {
      throw new AccessDeniedHttpException('You are not authorized to view this sample receipt.');
    }

    if ($record['status'] !== 'completed') {
      throw new AccessDeniedHttpException('No sample receipt is available because this demo donation was not completed.');
    }

    $symbol = match ($record['currency']) {
      'EUR' => '€',
      'GBP' => '£',
      default => '$',
    };
    $formattedAmount = $symbol . number_format(((int) $record['amount_minor']) / 100, 2) . ' ' . $record['currency'];
    $createdFormatted = date('Y-m-d H:i:s T', (int) $record['created']);

    if ((string) $request->query->get('download', '') === '1') {
      $lines = [
        'NUSRY FOUNDATION — SAMPLE DEMO RECEIPT',
        'Demo complete—no charge made.',
        'Sample receipt—not valid for tax purposes.',
        '----------------------------------------',
        'Reference: ' . $record['reference'],
        'Date: ' . $createdFormatted,
        'Amount: ' . $formattedAmount,
        'Frequency: ' . $record['frequency'],
        'Programme: ' . $record['campaign_id'],
        'Payment Method: ' . $record['payment_method'],
        'Employer Match Inquiry: ' . ((int) $record['employer_match'] === 1 ? 'Requested (demo)' : 'No'),
      ];
      if ($record['tribute_type'] !== 'none' && $record['tribute_name'] !== '') {
        $lines[] = 'Dedication (' . $record['tribute_type'] . '): ' . $record['tribute_name'];
      }
      $response = new Response(implode("\n", $lines) . "\n");
      $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
      $response->headers->set('Content-Disposition', 'attachment; filename="nusry-demo-receipt-' . $record['reference'] . '.txt"');
      $response->headers->set('Cache-Control', 'private, no-store');
      $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
      return $response;
    }

    return [
      '#theme' => 'nusry_donation_receipt',
      '#receipt' => [
        'reference' => $record['reference'],
        'amount_formatted' => $formattedAmount,
        'frequency' => $record['frequency'] === 'monthly' ? 'Monthly (simulated)' : 'One-time (simulated)',
        'campaign_id' => $record['campaign_id'],
        'payment_method' => $record['payment_method'],
        'tribute_type' => $record['tribute_type'],
        'tribute_name' => $record['tribute_name'],
        'employer_match' => (bool) $record['employer_match'],
        'donor_name' => $record['donor_name'],
        'created' => $createdFormatted,
        'download_url' => '/donate/receipt/' . rawurlencode($record['reference']) . '?download=1',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
