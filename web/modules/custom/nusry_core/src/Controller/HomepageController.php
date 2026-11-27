<?php

namespace Drupal\nusry_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\nusry_core\Service\EditorialSettings;
use Drupal\nusry_core\Service\StorySelector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Renders the Phase 4 homepage and preview donation/search routes.
 */
final class HomepageController extends ControllerBase {

  /**
   * Constructs a HomepageController instance.
   */
  public function __construct(
    private readonly EditorialSettings $editorialSettings,
    private readonly StorySelector $storySelector,
    private readonly ThemeExtensionList $themeExtensionList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nusry_core.editorial_settings'),
      $container->get('nusry_core.story_selector'),
      $container->get('extension.list.theme'),
    );
  }

  /**
   * Builds the reference-structured fictional homepage.
   */
  public function home(): array {
    $themeBase = '/' . $this->themeExtensionList->getPath('nusry');
    $selection = $this->storySelector->getFeaturedAndSupporting(4);
    $featured = $selection['featured'];
    $supporting = $selection['supporting'];

    $formatStory = static function ($node, string $imageUrl): array {
      $kind = strtoupper((string) ($node->get('field_story_kind')->value ?: 'story'));
      $rawDate = (string) ($node->get('field_date')->value ?? '');
      $ts = $rawDate !== '' ? strtotime($rawDate) : FALSE;
      return [
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
        'kind' => $kind,
        'date' => $ts !== FALSE ? strtoupper(date('M j, Y', $ts)) : '',
        'summary' => (string) ($node->get('field_summary')->value ?? ''),
        'image' => $imageUrl,
      ];
    };

    $featuredData = $featured
      ? $formatStory($featured, $themeBase . '/assets/images/news-featured.webp')
      : [
        'title' => 'Mobile clinics expand maternal care (Demo)',
        'url' => '/what-we-do',
        'kind' => 'NEWS',
        'date' => 'OCT 15, 2026',
        'summary' => 'Fictional demonstration story.',
        'image' => $themeBase . '/assets/images/news-featured.webp',
      ];

    $thumbs = [
      $themeBase . '/assets/images/thumb-1.webp',
      $themeBase . '/assets/images/thumb-2.webp',
      $themeBase . '/assets/images/thumb-3.webp',
      $themeBase . '/assets/images/thumb-4.webp',
    ];
    $supportingData = [];
    foreach ($supporting as $index => $storyNode) {
      $supportingData[] = $formatStory($storyNode, $thumbs[$index % count($thumbs)]);
    }

    $campaigns = $this->entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'campaign',
      'status' => 1,
      'field_campaign_status' => 'active',
    ]);
    $campaign = $campaigns ? reset($campaigns) : NULL;
    $presets = [3500, 7500, 15000, 30000];
    $campaignId = 'DEMO-MAT-2025';
    $campaignTitle = 'Emergency Maternal Health Fund (Demo)';
    $campaignFrequency = 'monthly';
    if ($campaign) {
      $campaignTitle = $campaign->label();
      $campaignId = (string) ($campaign->get('field_campaign_id')->value ?: $campaignId);
      $campaignFrequency = (string) ($campaign->get('field_frequency')->value ?: $campaignFrequency);
      $rawAmounts = array_map(
        static fn($item) => (int) $item['value'],
        $campaign->get('field_amounts')->getValue()
      );
      if (count($rawAmounts) >= 4) {
        $presets = array_slice($rawAmounts, 0, 4);
      }
    }

    return [
      '#theme' => 'nusry_homepage',
      '#theme_base' => $themeBase,
      '#hero' => [
        'title' => 'Care begins with showing up.',
        'summary' => 'Nusry Foundation is a fictional medical humanitarian organization, imagined to bring timely care to communities affected by conflict, outbreaks and disasters.',
        'learn_url' => '/what-we-do',
        'learn_label' => 'Learn more',
        'cta_url' => '/ways-to-give',
        'cta_label' => 'Support our demo work',
        'credit' => 'Illustrative demo photography · Nusry Foundation Demo Studio',
      ],
      '#campaign' => [
        'id' => $campaignId,
        'title' => $campaignTitle,
        'subtitle' => 'Choose a simulated amount to explore',
        'frequency' => $campaignFrequency,
        'presets' => array_map(static fn(int $minor) => [
          'minor' => $minor,
          'label' => '$' . number_format($minor / 100),
        ], $presets),
        'disclaimer' => 'Fictional demonstration only. No real funds are collected, matched, or transferred. Simulated gifts support imaginary programmes.',
      ],
      '#facts' => $this->editorialSettings->getOrganizationFacts(),
      '#allocation' => $this->editorialSettings->getFundAllocation(),
      '#featured_story' => $featuredData,
      '#supporting_stories' => $supportingData,
      '#social_links' => $this->editorialSettings->getSocialLinks(),
      '#principles' => [
        'We are independent, impartial, and guided by need.',
        'We uphold medical ethics and human dignity.',
        'We speak honestly about what communities face.',
        'We are transparent and accountable in our demo reporting.',
        'We commit to equitable, accessible care for all.',
      ],
      '#cache' => [
        'tags' => [
          'config:nusry_core.settings',
          'node_list:story',
          'node_list:campaign',
        ],
      ],
    ];
  }

  /**
   * Renders a safe published-only Phase 4 search preview route.
   */
  public function searchPreview(Request $request): array {
    $queryText = trim((string) $request->query->get('q', ''));
    $items = [];
    if ($queryText !== '') {
      $storage = $this->entityTypeManager()->getStorage('node');
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->range(0, 12);
      $or = $query->orConditionGroup()
        ->condition('title', $queryText, 'CONTAINS')
        ->condition('field_summary', $queryText, 'CONTAINS');
      $query->condition($or);
      $nids = $query->execute();
      foreach ($storage->loadMultiple($nids) as $node) {
        $items[] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['preview-panel__item']],
          'link' => [
            '#type' => 'link',
            '#title' => $node->label(),
            '#url' => $node->toUrl(),
          ],
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => (string) ($node->get('field_summary')->value ?? ''),
          ],
        ];
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['site-main--inner']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $queryText !== ''
          ? $this->t('Results for "@query"', ['@query' => $queryText])
          : $this->t('Search Nusry Foundation (Demo)'),
      ],
      'results' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['preview-panel']],
        'status' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $queryText === ''
            ? $this->t('Enter a keyword using the header search modal to search published demo content.')
            : $this->t('Showing @count published demo results.', ['@count' => count($items)]),
        ],
        'list' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['preview-panel__list']],
        ] + $items,
      ],
      '#cache' => [
        'contexts' => ['url.query_args:q'],
        'tags' => ['node_list'],
      ],
    ];
  }

}
