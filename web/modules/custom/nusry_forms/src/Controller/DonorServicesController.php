<?php

namespace Drupal\nusry_forms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\nusry_forms\Service\CsvSafety;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorized donor-services demo lookup and formula-injection-safe CSV export.
 */
final class DonorServicesController extends ControllerBase {

  /**
   * Logger channel.
   */
  private readonly LoggerInterface $logger;

  /**
   * Constructs a DonorServicesController instance.
   */
  public function __construct(
    private readonly Connection $database,
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
      $container->get('logger.factory'),
    );
  }

  /**
   * Displays simulated donor records to authorized donor_services staff.
   */
  public function dashboard(Request $request): array {
    $queryText = trim((string) $request->query->get('q', ''));
    $rows = $this->queryDonations($queryText, 50);

    $this->logger->notice('Authorized donor services lookup executed (records: @count).', [
      '@count' => count($rows),
    ]);

    $tableRows = [];
    foreach ($rows as $row) {
      $amount = number_format(((int) $row['amount_minor']) / 100, 2) . ' ' . $row['currency'];
      $tableRows[] = [
        $row['reference'],
        $row['donor_name'],
        $amount,
        $row['frequency'],
        $row['campaign_id'],
        $row['status'],
        date('Y-m-d', (int) $row['created']),
      ];
    }

    $newsletterCount = (int) $this->database->select('nusry_newsletter_subscriptions', 'n')
      ->condition('status', 'confirmed')
      ->countQuery()
      ->execute()
      ->fetchField();

    $contactCount = (int) $this->database->select('nusry_contact_submissions', 'c')
      ->countQuery()
      ->execute()
      ->fetchField();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['site-main--inner']],
      'banner' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['demo-notice-banner']],
        'pill' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['demo-pill']],
          '#value' => $this->t('Restricted demo workspace — authorized staff only'),
        ],
        'stats' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Confirmed demo newsletter records: @nl · Stored demo contact messages: @ct.', [
            '@nl' => $newsletterCount,
            '@ct' => $contactCount,
          ]),
        ],
      ],
      'controls' => [
        '#type' => 'inline_template',
        '#template' => '
          <form class="nusry-donor-search" method="get" action="/donor-services">
            <label for="ds-q">Filter by reference, donor name, or campaign</label>
            <div class="nusry-donor-search__row">
              <input id="ds-q" name="q" type="search" value="{{ q }}" placeholder="DEMO-..." />
              <button class="btn btn--primary" type="submit">Filter records</button>
              <a class="btn btn--outline-dark" href="/donor-services/export{% if q %}?q={{ q|url_encode }}{% endif %}">Export CSV (Formula-Safe)</a>
            </div>
          </form>',
        '#context' => ['q' => $queryText],
      ],
      'table_wrap' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['table-scroll']],
        'table' => [
          '#type' => 'table',
          '#attributes' => ['class' => ['data-table']],
          '#header' => [
            $this->t('Reference'),
            $this->t('Donor (demo)'),
            $this->t('Amount'),
            $this->t('Frequency'),
            $this->t('Campaign'),
            $this->t('Status'),
            $this->t('Date'),
          ],
          '#rows' => $tableRows,
          '#empty' => $this->t('No simulated donation records matched your filter.'),
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Streams formula-injection-safe CSV export of simulated gifts.
   */
  public function exportCsv(Request $request): Response {
    $queryText = trim((string) $request->query->get('q', ''));
    $rows = $this->queryDonations($queryText, 500);

    $this->logger->notice('Authorized donor services CSV export executed (records: @count).', [
      '@count' => count($rows),
    ]);

    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, [
      'Reference',
      'Donor Name',
      'Amount',
      'Currency',
      'Frequency',
      'Campaign',
      'Status',
      'Tribute Type',
      'Tribute Name',
      'Employer Match',
      'Created',
    ], ',', '"', '\\');

    foreach ($rows as $row) {
      fputcsv($handle, [
        CsvSafety::escapeFormula((string) $row['reference']),
        CsvSafety::escapeFormula((string) $row['donor_name']),
        number_format(((int) $row['amount_minor']) / 100, 2, '.', ''),
        CsvSafety::escapeFormula((string) $row['currency']),
        CsvSafety::escapeFormula((string) $row['frequency']),
        CsvSafety::escapeFormula((string) $row['campaign_id']),
        CsvSafety::escapeFormula((string) $row['status']),
        CsvSafety::escapeFormula((string) $row['tribute_type']),
        CsvSafety::escapeFormula((string) $row['tribute_name']),
        ((int) $row['employer_match'] === 1) ? 'yes' : 'no',
        gmdate('Y-m-d\TH:i:s\Z', (int) $row['created']),
      ], ',', '"', '\\');
    }

    rewind($handle);
    $csv = (string) stream_get_contents($handle);
    fclose($handle);

    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="nusry-demo-donations.csv"');
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    return $response;
  }

  /**
   * Queries simulated donations with safe parameterized filtering.
   *
   * @return array<int, array<string, mixed>>
   *   Matched donation rows.
   */
  private function queryDonations(string $queryText, int $limit): array {
    $query = $this->database->select('nusry_donations', 'd')
      ->fields('d')
      ->orderBy('created', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, $limit);

    if ($queryText !== '') {
      $escaped = '%' . $this->database->escapeLike($queryText) . '%';
      $or = $query->orConditionGroup()
        ->condition('reference', $escaped, 'LIKE')
        ->condition('donor_name', $escaped, 'LIKE')
        ->condition('campaign_id', $escaped, 'LIKE');
      $query->condition($or);
    }

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
