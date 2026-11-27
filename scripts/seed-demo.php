<?php

/**
 * @file
 * Idempotent synthetic demo content seeder for local and CI environments.
 */

use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\File\FileSystemInterface;

if (!in_array(getenv('NUSRY_ENV'), ['local', 'ci'], TRUE)) {
  throw new RuntimeException('Demo seeding is restricted to explicit local or CI environments.');
}

$entityTypeManager = \Drupal::entityTypeManager();
$fileSystem = \Drupal::service('file_system');
$fileRepository = \Drupal::service('file.repository');

// 1. Synthetic users (example.test emails, random non-committed passwords).
$seedUsers = [
  'demo-editor' => ['mail' => 'demo-editor@example.test', 'roles' => ['editor']],
  'demo-contributor' => ['mail' => 'demo-contributor@example.test', 'roles' => ['contributor']],
  'demo-donor-services' => ['mail' => 'demo-donor-services@example.test', 'roles' => ['donor_services']],
  'demo-donor' => ['mail' => 'demo-donor@example.test', 'roles' => ['demo_donor']],
];
$users = [];
foreach ($seedUsers as $name => $info) {
  $existing = $entityTypeManager->getStorage('user')->loadByProperties(['name' => $name]);
  if ($existing) {
    $user = reset($existing);
  }
  else {
    $user = User::create([
      'name' => $name,
      'mail' => $info['mail'],
      'pass' => bin2hex(random_bytes(24)),
      'status' => 1,
      'roles' => $info['roles'],
    ]);
    $user->save();
  }
  $users[$name] = $user;
}

// 2. Taxonomy terms.
$ensureTerm = static function (string $vid, string $name) use ($entityTypeManager): Term {
  $found = $entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
  if ($found) {
    return reset($found);
  }
  $term = Term::create(['vid' => $vid, 'name' => $name]);
  $term->save();
  return $term;
};

$regions = [];
foreach (['East Africa (Demo)', 'West Africa (Demo)', 'South Asia (Demo)', 'Middle East (Demo)', 'Latin America (Demo)', 'Southeast Asia (Demo)'] as $label) {
  $regions[$label] = $ensureTerm('regions', $label);
}
$storyCategories = [];
foreach (['Field Dispatch', 'Medical Update', 'Emergency Response', 'Voices', 'Magazine'] as $label) {
  $storyCategories[$label] = $ensureTerm('story_categories', $label);
}
$eventFormats = [];
foreach (['Webinar', 'Community Briefing', 'Student Forum'] as $label) {
  $eventFormats[$label] = $ensureTerm('event_formats', $label);
}
$campaignThemes = [];
foreach (['Maternal Health', 'Emergency Relief', 'Vaccination'] as $label) {
  $campaignThemes[$label] = $ensureTerm('campaign_themes', $label);
}
$reportCategories = [];
foreach (['Annual Report', 'Financial Statement'] as $label) {
  $reportCategories[$label] = $ensureTerm('report_categories', $label);
}
foreach (['Monthly Giving', 'Tribute Gift', 'Legacy Planning', 'Donor-Advised Fund', 'Workplace Giving', 'Corporate Partnership', 'Vehicle Donation'] as $label) {
  $ensureTerm('giving_methods', $label);
}

// 3. Synthetic local media assets (private://editorial/demo).
$directory = 'private://editorial/demo';
$fileSystem->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

$writeDemoPng = static function (string $filename, array $bgRgb, array $accentRgb, int $width = 1200, int $height = 800) use ($directory, $fileRepository, $entityTypeManager): FileInterface {
  $uri = "$directory/$filename";
  $existing = $entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri]);
  if ($existing) {
    return reset($existing);
  }
  $image = imagecreatetruecolor($width, $height);
  $bg = imagecolorallocate($image, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
  $accent = imagecolorallocate($image, $accentRgb[0], $accentRgb[1], $accentRgb[2]);
  $band = imagecolorallocate($image, 35, 31, 32);
  imagefill($image, 0, 0, $bg);
  imagefilledrectangle($image, 0, (int) ($height * 0.72), $width, $height, $band);
  imagefilledrectangle($image, 0, (int) ($height * 0.70), (int) ($width * 0.35), (int) ($height * 0.72), $accent);
  ob_start();
  imagepng($image);
  $bytes = (string) ob_get_clean();
  imagedestroy($image);
  return $fileRepository->writeData($bytes, $uri, \Drupal\Core\File\FileExists::Replace);
};

$ensureImageMedia = static function (string $name, string $filename, string $alt, string $caption, array $bgRgb, array $accentRgb, bool $sensitive = FALSE) use ($entityTypeManager, $writeDemoPng, $users): Media {
  $existing = $entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  $file = $writeDemoPng($filename, $bgRgb, $accentRgb);
  $file->setPermanent();
  $file->save();
  $media = Media::create([
    'bundle' => 'image',
    'name' => $name,
    'uid' => $users['demo-editor']->id(),
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt' => $alt,
    ],
    'field_decorative' => 0,
    'field_sensitive' => $sensitive ? 1 : 0,
    'field_caption' => $caption,
    'field_credit' => 'Nusry Foundation Demo Studio (synthetic generator)',
    'field_license' => 'CC0-1.0 (synthetic local placeholder)',
    'field_provenance' => 'Generated locally by scripts/seed-demo.php; no real photography or patients.',
    'field_source_url' => ['uri' => 'https://example.test/assets/' . $filename],
    'moderation_state' => 'published',
    'status' => 1,
  ]);
  $media->save();
  return $media;
};

$ensurePdfMedia = static function (string $name, string $filename, string $docTitle, string $accessibleText) use ($entityTypeManager, $directory, $fileRepository, $users): Media {
  $existing = $entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'document', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  $uri = "$directory/$filename";
  $existingFile = $entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri]);
  if ($existingFile) {
    $file = reset($existingFile);
  }
  else {
    $safeTitle = preg_replace('/[^A-Za-z0-9 \-.:()]/', '', $docTitle);
    $stream = "BT /F1 14 Tf 50 740 Td ($safeTitle) Tj 0 -24 Td (Fictional Nusry Foundation demonstration report. Not a real charity filing.) Tj ET";
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
      . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
      . "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n"
      . "4 0 obj << /Length " . strlen($stream) . " >> stream\n$stream\nendstream endobj\n"
      . "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
      . "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000241 00000 n \n0000000400 00000 n \ntrailer << /Size 6 /Root 1 0 R >>\nstartxref\n468\n%%EOF\n";
    $file = $fileRepository->writeData($pdf, $uri, \Drupal\Core\File\FileExists::Replace);
    $file->setPermanent();
    $file->save();
  }
  $media = Media::create([
    'bundle' => 'document',
    'name' => $name,
    'uid' => $users['demo-editor']->id(),
    'field_media_document' => ['target_id' => $file->id()],
    'field_credit' => 'Nusry Foundation Demo Publications',
    'field_license' => 'CC0-1.0 (synthetic local placeholder)',
    'field_provenance' => 'Generated locally by scripts/seed-demo.php as a fictional demonstration report.',
    'field_source_url' => ['uri' => 'https://example.test/reports/' . $filename],
    'field_accessible_text' => $accessibleText,
    'field_document_date' => '2026-01-15T09:00:00',
    'moderation_state' => 'published',
    'status' => 1,
  ]);
  $media->save();
  return $media;
};

$images = [
  'hero' => $ensureImageMedia('Demo hero clinic scene', 'demo-hero-clinic.png', 'Illustrative graphic representing a rural clinic shelter.', 'Illustrative photograph (synthetic demo placeholder).', [243, 243, 241], [238, 0, 0]),
  'water' => $ensureImageMedia('Demo water station scene', 'demo-water-station.png', 'Illustrative graphic representing a clean water distribution point.', 'Illustrative photograph (synthetic demo placeholder).', [233, 240, 244], [238, 0, 0]),
  'logistics' => $ensureImageMedia('Demo cold-chain logistics scene', 'demo-logistics.png', 'Illustrative graphic representing solar medical supply boxes.', 'Illustrative photograph (synthetic demo placeholder).', [250, 245, 230], [238, 0, 0]),
  'midwives' => $ensureImageMedia('Demo training workshop scene', 'demo-midwives.png', 'Illustrative graphic representing a community health workshop.', 'Illustrative photograph (synthetic demo placeholder).', [240, 238, 235], [238, 0, 0]),
  'sensitive' => $ensureImageMedia('Demo sensitive placeholder', 'demo-sensitive-placeholder.png', 'Illustrative graphic gated behind content warning.', 'Illustrative graphic used to demonstrate warning-and-reveal control.', [224, 224, 220], [35, 31, 32], TRUE),
];

$docs = [
  'annual' => $ensurePdfMedia(
    'Demo annual report 2025 PDF',
    'demo-annual-report-2025.pdf',
    'Demo Annual Report 2025',
    'Accessible summary: In this fictional 2025 demonstration period, illustrative figures cover 6 regions, 24,800 consultations, and 3,600 admissions, with 82% programmes, 12% fundraising, and 6% administration allocation. No real operations or audited charity accounts are represented.'
  ),
  'financial' => $ensurePdfMedia(
    'Financial transparency summary 2025 PDF',
    'demo-financial-summary-2025.pdf',
    'Demo Financial Transparency Summary 2025',
    'Accessible summary: Illustrative demo fund breakdown showing 82% programmes, 12% fundraising, and 6% administration for the fictional 2025 reporting cycle.'
  ),
];

$existingVideo = $entityTypeManager->getStorage('media')->loadByProperties(['bundle' => 'remote_video', 'name' => 'Demo Medical Briefing Video']);
if (!$existingVideo) {
  $video = Media::create([
    'bundle' => 'remote_video',
    'name' => 'Demo Medical Briefing Video',
    'uid' => $users['demo-editor']->id(),
    'field_media_remote_video' => 'https://example.test/videos/demo-briefing',
    'field_credit' => 'Nusry Foundation Demo Studio',
    'field_license' => 'CC0-1.0 (synthetic demo placeholder)',
    'field_provenance' => 'Placeholder remote video reference for privacy-aware embed testing.',
    'field_source_url' => ['uri' => 'https://example.test/videos/demo-briefing'],
    'field_accessible_text' => 'Transcript (demo): Welcome to the fictional Nusry Foundation logistics briefing.',
    'field_captions_confirmed' => 1,
    'moderation_state' => 'published',
    'status' => 1,
  ]);
  $video->save();
  \Drupal::messenger()->deleteAll();
  $videoMedia = $video;
}
else {
  $videoMedia = reset($existingVideo);
}

// Helper for idempotent node creation + optional explicit path alias.
$ensureNode = static function (string $bundle, string $title, array $values, ?string $alias = NULL) use ($entityTypeManager, $users): Node {
  $existing = $entityTypeManager->getStorage('node')->loadByProperties(['type' => $bundle, 'title' => $title]);
  if ($existing) {
    return reset($existing);
  }
  $node = Node::create($values + [
    'type' => $bundle,
    'title' => $title,
    'uid' => $values['uid'] ?? $users['demo-editor']->id(),
    'field_demo' => 1,
    'moderation_state' => $values['moderation_state'] ?? 'published',
    'status' => ($values['moderation_state'] ?? 'published') === 'published' ? 1 : 0,
  ]);
  if ($alias !== NULL) {
    $node->set('path', ['alias' => $alias, 'pathauto' => 0]);
  }
  $node->save();
  if ($alias !== NULL) {
    $existingAlias = $entityTypeManager->getStorage('path_alias')->loadByProperties(['alias' => $alias]);
    if (!$existingAlias) {
      PathAlias::create(['path' => '/node/' . $node->id(), 'alias' => $alias, 'langcode' => 'en'])->save();
    }
  }
  return $node;
};

// 4. Reports (2).
$reports = [];
$reports['annual'] = $ensureNode('report', 'Demo annual report 2025', [
  'field_summary' => 'Fictional 2025 annual overview presenting illustrative medical activity and fund allocation metrics.',
  'field_period' => '2025',
  'field_document' => ['target_id' => $docs['annual']->id()],
  'field_category' => ['target_id' => $reportCategories['Annual Report']->id()],
  'field_date' => '2026-01-15T09:00:00',
  'field_accessible_text' => [
    'value' => '<p><strong>Demo Annual Report (2025):</strong> Illustrative figures only: 6 regions, 24,800 outpatient consultations, and 3,600 inpatient admissions. Fund allocation: 82% programmes, 12% fundraising, 6% administration.</p>',
    'format' => 'restricted_html',
  ],
], '/reports/demo-annual-report-2025');

$reports['financial'] = $ensureNode('report', 'Financial transparency summary 2025', [
  'field_summary' => 'Demonstrative breakdown of fictional programme, fundraising and administration spending.',
  'field_period' => '2025',
  'field_document' => ['target_id' => $docs['financial']->id()],
  'field_category' => ['target_id' => $reportCategories['Financial Statement']->id()],
  'field_date' => '2026-02-01T09:00:00',
  'field_accessible_text' => [
    'value' => '<p>Illustrative allocation dataset summing to 100%: 82% programmes, 12% fundraising, 6% administration. Not an audited charitable filing.</p>',
    'format' => 'restricted_html',
  ],
], '/reports/financial-transparency-summary-2025');

// 5. Countries (4 clearly illustrative profiles).
$countrySpecs = [
  'valeria' => ['title' => 'Valeria Republic (Illustrative)', 'iso' => 'VR', 'region' => 'East Africa (Demo)', 'slug' => '/where-we-work/valeria-republic', 'facts' => 'Illustrative profile: 8,200 demo consultations and 2 mobile maternal units. No actual operations represented.'],
  'kestrel' => ['title' => 'Kestrel Coast (Illustrative)', 'iso' => 'KC', 'region' => 'West Africa (Demo)', 'slug' => '/where-we-work/kestrel-coast', 'facts' => 'Illustrative profile: 5,400 demo consultations and community midwife workshops. No actual operations represented.'],
  'surya' => ['title' => 'Surya Delta (Illustrative)', 'iso' => 'SD', 'region' => 'South Asia (Demo)', 'slug' => '/where-we-work/surya-delta', 'facts' => 'Illustrative profile: 6,900 demo consultations and flood-resilient water points. No actual operations represented.'],
  'marjan' => ['title' => 'Al-Marjan Valley (Illustrative)', 'iso' => 'AM', 'region' => 'Middle East (Demo)', 'slug' => '/where-we-work/al-marjan-valley', 'facts' => 'Illustrative profile: 4,300 demo consultations and surgical triage support. No actual operations represented.'],
];
$countries = [];
foreach ($countrySpecs as $key => $spec) {
  $countries[$key] = $ensureNode('country', $spec['title'], [
    'field_summary' => 'Fictional demonstration country profile illustrating regional medical support patterns.',
    'field_iso_code' => $spec['iso'],
    'field_region' => ['target_id' => $regions[$spec['region']]->id()],
    'field_facts' => $spec['facts'],
    'field_hero' => ['target_id' => $images['hero']->id()],
  ], $spec['slug']);
}

// 6. Medical Issues (4).
$issueSpecs = [
  'maternal' => ['title' => 'Maternal & Newborn Care', 'slug' => '/medical-issues/maternal-newborn-care', 'countries' => [$countries['valeria']->id(), $countries['kestrel']->id()], 'body' => '<p>Illustrative medical overview describing mobile antenatal visits, emergency obstetric referrals and community midwife training in fictional demo settings.</p>'],
  'malnutrition' => ['title' => 'Acute Malnutrition', 'slug' => '/medical-issues/acute-malnutrition', 'countries' => [$countries['valeria']->id(), $countries['surya']->id()], 'body' => '<p>Illustrative overview covering outpatient screening, ready-to-use therapeutic nutrition protocols and family follow-up visits.</p>'],
  'waterborne' => ['title' => 'Waterborne Illness & Cholera Prevention', 'slug' => '/medical-issues/waterborne-illness', 'countries' => [$countries['surya']->id(), $countries['kestrel']->id()], 'body' => '<p>Illustrative overview of oral rehydration points, safe water chlorination and rapid community hygiene education.</p>'],
  'trauma' => ['title' => 'Emergency Trauma & Surgical Care', 'slug' => '/medical-issues/emergency-trauma', 'countries' => [$countries['marjan']->id(), $countries['valeria']->id()], 'body' => '<p>Illustrative overview of triage stabilization, sterile surgical kits and post-operative rehabilitation pathways.</p>'],
];
$issues = [];
foreach ($issueSpecs as $key => $spec) {
  $issues[$key] = $ensureNode('medical_issue', $spec['title'], [
    'field_summary' => 'Fictional demonstration overview of a core humanitarian medical focus area.',
    'field_body' => ['value' => $spec['body'], 'format' => 'restricted_html'],
    'field_reviewed' => '2026-09-01',
    'field_countries' => array_map(static fn($id) => ['target_id' => $id], $spec['countries']),
    'field_hero' => ['target_id' => $images['logistics']->id()],
  ], $spec['slug']);
}

// 7. Campaigns (2).
$campaigns = [];
$campaigns['maternal'] = $ensureNode('campaign', 'Emergency Maternal Health Fund (Demo)', [
  'field_summary' => 'Fictional campaign supporting simulated maternal clinics and safe delivery kits.',
  'field_hero' => ['target_id' => $images['hero']->id()],
  'field_start' => '2026-01-01T00:00:00',
  'field_end' => '2027-12-31T23:59:59',
  'field_campaign_id' => 'DEMO-MAT-2025',
  'field_campaign_status' => 'active',
  'field_amounts' => [3500, 7500, 15000, 30000],
  'field_frequency' => 'monthly',
  'field_matching' => 'Demo notice: no real matching sponsor or financial commitment exists.',
  'field_themes' => [['target_id' => $campaignThemes['Maternal Health']->id()]],
], '/campaigns/emergency-maternal-health-fund');

$campaigns['water'] = $ensureNode('campaign', 'Clean Water Rapid Response (Demo)', [
  'field_summary' => 'Fictional campaign demonstrating rapid clean-water and cholera-prevention kits.',
  'field_hero' => ['target_id' => $images['water']->id()],
  'field_start' => '2026-02-01T00:00:00',
  'field_end' => '2027-12-31T23:59:59',
  'field_campaign_id' => 'DEMO-WAT-2025',
  'field_campaign_status' => 'active',
  'field_amounts' => [2500, 5000, 10000, 25000],
  'field_frequency' => 'once',
  'field_matching' => 'Demo notice: illustrative fundraising scenario only.',
  'field_themes' => [['target_id' => $campaignThemes['Emergency Relief']->id()]],
], '/campaigns/clean-water-rapid-response');

// 8. Events (3).
$ensureNode('event', 'Inside Field Maternal Clinics: Virtual Briefing', [
  'field_summary' => 'Live fictional Q&A introducing simulated mobile maternal care workflows.',
  'field_start' => '2026-12-10T15:00:00',
  'field_end' => '2026-12-10T16:00:00',
  'field_timezone' => 'UTC',
  'field_format' => ['target_id' => $eventFormats['Webinar']->id()],
  'field_virtual_url' => ['uri' => 'https://example.test/events/maternal-briefing', 'title' => 'Join demo stream'],
  'field_accessibility' => 'Live captions and full post-event transcript provided.',
  'field_booking' => ['uri' => 'https://example.test/register/maternal-briefing', 'title' => 'Register (demo)'],
  'field_campaign' => ['target_id' => $campaigns['maternal']->id()],
], '/events/maternal-clinics-virtual-briefing');

$ensureNode('event', 'Annual Transparency & Demo Report Forum', [
  'field_summary' => 'Walkthrough of the fictional 2025 Demo Annual Report and allocation model.',
  'field_start' => '2026-12-18T18:00:00',
  'field_end' => '2026-12-18T19:30:00',
  'field_timezone' => 'America/New_York',
  'field_format' => ['target_id' => $eventFormats['Community Briefing']->id()],
  'field_venue' => 'Demo Community Hall, 100 Example Plaza (Fictional Address)',
  'field_accessibility' => 'Step-free entrance, hearing loop and ASL interpretation on request.',
  'field_booking' => ['uri' => 'https://example.test/register/transparency-forum', 'title' => 'RSVP (demo)'],
  'field_campaign' => ['target_id' => $campaigns['water']->id()],
], '/events/annual-transparency-forum');

$ensureNode('event', 'University Humanitarian Health Workshop', [
  'field_summary' => 'Interactive student chapter session exploring fictional cold-chain logistics.',
  'field_start' => '2027-01-14T17:00:00',
  'field_end' => '2027-01-14T18:15:00',
  'field_timezone' => 'UTC',
  'field_format' => ['target_id' => $eventFormats['Student Forum']->id()],
  'field_virtual_url' => ['uri' => 'https://example.test/events/student-workshop', 'title' => 'Workshop link'],
  'field_accessibility' => 'Screen-reader-friendly slide pack shared 48 hours prior.',
], '/events/university-humanitarian-health-workshop');

// 9. Eight original published stories (+ 1 draft + 1 scheduled workflow sample).
$longBody = '<p>In this fictional demonstration dispatch, mobile medical teams coordinate with community health workers to keep essential primary care accessible across dispersed settlements. Rather than waiting for patients to travel long distances along seasonal roads, rotating clinical vans carry diagnostic kits, maternal screening equipment and temperature-monitored vaccines directly to village hubs.</p><p>Each rotation follows strict clinical checklists and transparent supply tracking. Community focal points share feedback on clinic hours and language needs, allowing schedules to adapt before seasonal rains disrupt transit. All locations, counts and operational descriptions on this site are illustrative examples designed to demonstrate the editorial platform.</p><p>Follow-up referrals connect high-risk pregnancies and acute malnutrition cases with regional stabilization centers, while local midwives receive continuing peer support and sterile supply replenishment.</p>';

$storySpecs = [
  ['title' => 'Mobile clinics expand maternal care across Valeria Republic', 'kind' => 'news', 'date' => '2026-10-15T09:00:00', 'author' => 'Dr. Amira Nasser (Demo)', 'img' => 'hero', 'country' => 'valeria', 'issue' => 'maternal', 'campaign' => 'maternal', 'cat' => 'Emergency Response', 'slug' => '/stories/mobile-clinics-expand-maternal-care-across-valeria-republic'],
  ['title' => 'Clean water stations cut clinic delays in Surya Delta', 'kind' => 'story', 'date' => '2026-10-10T09:00:00', 'author' => 'Rohan Sen (Demo)', 'img' => 'water', 'country' => 'surya', 'issue' => 'waterborne', 'campaign' => 'water', 'cat' => 'Field Dispatch', 'slug' => '/stories/clean-water-stations-cut-clinic-delays-surya-delta'],
  ['title' => 'How cold-chain logistics protect rural immunization teams', 'kind' => 'story', 'date' => '2026-10-05T09:00:00', 'author' => 'Claire Moreau (Demo)', 'img' => 'logistics', 'country' => 'kestrel', 'issue' => 'maternal', 'campaign' => 'maternal', 'cat' => 'Medical Update', 'slug' => '/stories/how-cold-chain-logistics-protect-rural-immunization'],
  ['title' => 'Emergency surgical kits reach coastal health posts', 'kind' => 'news', 'date' => '2026-09-28T09:00:00', 'author' => 'Tariq Haddad (Demo)', 'img' => 'hero', 'country' => 'marjan', 'issue' => 'trauma', 'campaign' => 'water', 'cat' => 'Emergency Response', 'slug' => '/stories/emergency-surgical-kits-reach-coastal-health-posts'],
  ['title' => 'Training community midwives in Kestrel Coast', 'kind' => 'story', 'date' => '2026-09-20T09:00:00', 'author' => 'Nadia Osei (Demo)', 'img' => 'midwives', 'country' => 'kestrel', 'issue' => 'maternal', 'campaign' => 'maternal', 'cat' => 'Voices', 'slug' => '/stories/training-community-midwives-kestrel-coast'],
  ['title' => 'Nutrition screening reaches 3,600 families in demo review', 'kind' => 'news', 'date' => '2026-09-14T09:00:00', 'author' => 'Dr. Amira Nasser (Demo)', 'img' => 'logistics', 'country' => 'valeria', 'issue' => 'malnutrition', 'campaign' => 'maternal', 'cat' => 'Medical Update', 'slug' => '/stories/nutrition-screening-reaches-3600-families-demo'],
  ['title' => 'Magazine: Designing resilient field pharmacies', 'kind' => 'story', 'date' => '2026-09-05T09:00:00', 'author' => 'Editorial Desk (Demo)', 'img' => 'water', 'country' => 'surya', 'issue' => 'waterborne', 'campaign' => 'water', 'cat' => 'Magazine', 'slug' => '/stories/magazine-designing-resilient-field-pharmacies'],
  ['title' => 'Solar microgrids keep rural maternity wards lit overnight', 'kind' => 'story', 'date' => '2026-08-28T09:00:00', 'author' => 'Claire Moreau (Demo)', 'img' => 'midwives', 'country' => 'marjan', 'issue' => 'trauma', 'campaign' => 'maternal', 'cat' => 'Field Dispatch', 'slug' => '/stories/solar-microgrids-keep-rural-maternity-wards-lit'],
];

$seededStories = [];
foreach ($storySpecs as $spec) {
  $seededStories[] = $ensureNode('story', $spec['title'], [
    'field_summary' => 'Fictional demonstration article illustrating humanitarian medical reporting.',
    'field_body' => ['value' => $longBody, 'format' => 'restricted_html'],
    'field_story_kind' => $spec['kind'],
    'field_date' => $spec['date'],
    'field_author' => $spec['author'],
    'field_hero' => ['target_id' => $images[$spec['img']]->id()],
    'field_social_image' => ['target_id' => $images[$spec['img']]->id()],
    'field_countries' => [['target_id' => $countries[$spec['country']]->id()]],
    'field_issues' => [['target_id' => $issues[$spec['issue']]->id()]],
    'field_campaign' => ['target_id' => $campaigns[$spec['campaign']]->id()],
    'field_categories' => [['target_id' => $storyCategories[$spec['cat']]->id()]],
  ], $spec['slug']);
}

// Link curated related stories on the lead story.
if ($seededStories[0]->get('field_related')->isEmpty() && count($seededStories) >= 4) {
  $seededStories[0]->set('field_related', [
    ['target_id' => $seededStories[1]->id()],
    ['target_id' => $seededStories[2]->id()],
  ]);
  $seededStories[0]->save();
}

// Non-published workflow samples.
$ensureNode('story', 'Draft: Upcoming seasonal preparedness review', [
  'uid' => $users['demo-contributor']->id(),
  'field_summary' => 'Unpublished contributor draft verifying non-public revision isolation.',
  'field_body' => ['value' => '<p>Draft text not visible to anonymous visitors.</p>', 'format' => 'restricted_html'],
  'field_story_kind' => 'story',
  'field_date' => '2026-11-20T09:00:00',
  'field_author' => 'Contributor Account (Demo)',
  'field_hero' => ['target_id' => $images['logistics']->id()],
  'moderation_state' => 'draft',
  'status' => 0,
]);

$ensureNode('story', 'Scheduled: Mid-year logistics digest', [
  'uid' => $users['demo-editor']->id(),
  'field_summary' => 'Approved revision scheduled for future publication via Scheduler.',
  'field_body' => ['value' => '<p>Scheduled article queued for future release.</p>', 'format' => 'restricted_html'],
  'field_story_kind' => 'news',
  'field_date' => '2026-12-01T09:00:00',
  'field_author' => 'Editor Account (Demo)',
  'field_hero' => ['target_id' => $images['water']->id()],
  'moderation_state' => 'scheduled',
  'publish_on' => time() + (7 * 86400),
  'publish_state' => 'published',
  'status' => 0,
]);

// 10. Flexible pages with Paragraph components.
$makeParagraph = static function (string $bundle, array $fields): Paragraph {
  $p = Paragraph::create(['type' => $bundle] + $fields);
  $p->save();
  return $p;
};

$existingHome = $entityTypeManager->getStorage('node')->loadByProperties(['type' => 'flexible_page', 'title' => 'Home (Demo)']);
if (!$existingHome) {
  $statItems = [
    $makeParagraph('statistic_item', ['field_label' => 'Regions', 'field_value' => '6', 'field_unit' => 'demo regions', 'field_period' => '2025 Demo Period', 'field_source' => 'Demo annual report (illustrative only)']),
    $makeParagraph('statistic_item', ['field_label' => 'Consultations', 'field_value' => '24,800', 'field_unit' => 'outpatient visits', 'field_period' => '2025 Demo Period', 'field_source' => 'Demo annual report (illustrative only)']),
    $makeParagraph('statistic_item', ['field_label' => 'Admissions', 'field_value' => '3,600', 'field_unit' => 'inpatient admissions', 'field_period' => '2025 Demo Period', 'field_source' => 'Demo annual report (illustrative only)']),
  ];
  $principleItems = [
    $makeParagraph('principle_item', ['field_heading' => 'Independence (Demo)', 'field_body' => ['value' => '<p>Decisions rest on medical need alone.</p>', 'format' => 'restricted_html']]),
    $makeParagraph('principle_item', ['field_heading' => 'Impartiality (Demo)', 'field_body' => ['value' => '<p>Care is offered without discrimination.</p>', 'format' => 'restricted_html']]),
    $makeParagraph('principle_item', ['field_heading' => 'Accountability (Demo)', 'field_body' => ['value' => '<p>Transparent demo reporting and safety standards.</p>', 'format' => 'restricted_html']]),
  ];
  $homeComponents = [
    $makeParagraph('hero', [
      'field_heading' => 'Care, dignity and solidarity',
      'field_summary' => 'Fictional medical humanitarian demonstration showcasing editorial, accessibility and donation workflows.',
      'field_media' => ['target_id' => $images['hero']->id()],
      'field_link' => ['uri' => 'internal:/what-we-do', 'title' => 'Explore what we do'],
      'field_secondary_link' => ['uri' => 'internal:/ways-to-give', 'title' => 'Demo giving options'],
    ]),
    $makeParagraph('donation', [
      'field_heading' => 'Support demo care',
      'field_campaign' => ['target_id' => $campaigns['maternal']->id()],
      'field_amounts' => [3500, 7500, 15000, 30000],
      'field_currency' => 'USD',
      'field_frequencies' => ['once', 'monthly'],
    ]),
    $makeParagraph('statistics', [
      'field_heading' => 'Illustrative annual figures (Demo)',
      'field_items' => $statItems,
    ]),
    $makeParagraph('card_grid', [
      'field_heading' => 'Featured news & stories',
      'field_content' => array_map(static fn(Node $n) => ['target_id' => $n->id()], array_slice($seededStories, 0, 5)),
      'field_count' => 5,
    ]),
    $makeParagraph('newsletter', [
      'field_heading' => 'Stay informed (Demo)',
      'field_summary' => 'Receive simulated dispatches from the fictional Nusry Foundation.',
      'field_consent_text' => 'I agree to receive fictional demonstration updates and understand no real communications are sent.',
      'field_consent_version' => 'v1-demo',
      'field_link' => ['uri' => 'internal:/privacy', 'title' => 'Read demo privacy notice'],
    ]),
    $makeParagraph('principles', [
      'field_heading' => 'Our guiding principles',
      'field_items' => $principleItems,
      'field_link' => ['uri' => 'internal:/who-we-are', 'title' => 'About our fictional mission'],
    ]),
  ];
  $ensureNode('flexible_page', 'Home (Demo)', [
    'field_summary' => 'Nusry Foundation fictional demonstration homepage content model.',
    'field_components' => $homeComponents,
  ], '/pages/home');
}

$flexiblePages = [
  'What We Do' => ['/what-we-do', 'Overview of fictional humanitarian medical services.', [$makeParagraph('image_text', ['field_heading' => 'Rapid clinical deployment (Demo)', 'field_body' => ['value' => '<p>Illustrative overview of emergency medical kits and mobile clinics.</p>', 'format' => 'restricted_html'], 'field_media' => ['target_id' => $images['hero']->id()], 'field_alignment' => 'left'])]],
  'Where We Work' => ['/where-we-work', 'Directory of fictional illustrative country profiles.', [$makeParagraph('country_directory', ['field_heading' => 'Illustrative regions', 'field_regions' => array_map(static fn($t) => ['target_id' => $t->id()], array_values($regions))])]],
  'Medical Issues' => ['/medical-issues', 'Fictional summaries of priority humanitarian medical topics.', [$makeParagraph('rich_text', ['field_body' => ['value' => '<p>Explore our four illustrative medical focus guides.</p>', 'format' => 'restricted_html']])]],
  'Our Work in Focus' => ['/our-work-in-focus', 'Deep-dive demonstration components including video and sensitive-media warning.', [$makeParagraph('video', ['field_heading' => 'Logistics briefing (Demo)', 'field_video' => ['target_id' => $videoMedia->id()]]), $makeParagraph('sensitive_image', ['field_warning' => 'Content warning (demo): Graphic imagery simulated behind explicit reveal control.', 'field_media' => ['target_id' => $images['sensitive']->id()]])]],
  'Who We Are' => ['/who-we-are', 'About the fictional Nusry Foundation demonstration.', [$makeParagraph('quote', ['field_quote' => 'Every community deserves dignified care regardless of geography.', 'field_attribution' => 'Dr. Amira Nasser (Fictional Character)', 'field_source' => 'Demo annual report 2025'])]],
  'Finances & Accountability' => ['/finances-and-accountability', 'Illustrative 82% / 12% / 6% allocation dataset and sample PDFs.', [$makeParagraph('downloads', ['field_heading' => 'Demo reports', 'field_documents' => [['target_id' => $docs['annual']->id()], ['target_id' => $docs['financial']->id()]]])]],
  'Our History' => ['/our-history', 'Fictional timeline illustrating organizational milestones.', [$makeParagraph('timeline', ['field_heading' => 'Fictional milestones', 'field_items' => [$makeParagraph('timeline_item', ['field_date' => '2020 (Demo)', 'field_heading' => 'Fictional founding concept', 'field_body' => ['value' => '<p>Established as an illustrative humanitarian training network.</p>', 'format' => 'restricted_html']]), $makeParagraph('timeline_item', ['field_date' => '2025 (Demo)', 'field_heading' => 'Demo digital platform launch', 'field_body' => ['value' => '<p>Introduced accessible editorial and mock-donation workflows.</p>', 'format' => 'restricted_html']])]])]],
  'The US Office (Fictional Demo)' => ['/us-office', 'Fictional regional office information — no real street address or phone number.', [$makeParagraph('rich_text', ['field_body' => ['value' => '<p>Fictional demo office: 100 Example Plaza, Suite 400 (not a real address).</p>', 'format' => 'restricted_html']])]],
  'Ways to Get Involved' => ['/get-involved', 'Community, student and fundraising participation concepts (demo).', []],
  'Ways to Give' => ['/ways-to-give', 'Overview of demonstrative giving options; not tax or legal advice.', []],
  'Fundraise' => ['/fundraise', 'Fictional peer-to-peer fundraising guide.', []],
  'Student Chapters' => ['/student-chapters', 'Illustrative campus chapter resources.', []],
  'Work With Us' => ['/work-with-us', 'Fictional humanitarian recruitment overview (no live applications).', []],
  'Give Monthly (Demo)' => ['/ways-to-give/monthly', 'Demonstrative monthly giving overview.', []],
  'Honor & Tribute Gifts (Demo)' => ['/ways-to-give/tribute', 'Demonstrative tribute giving overview.', []],
  'Legacy & Planned Giving (Demo)' => ['/ways-to-give/legacy', 'Demonstrative planned giving overview; not legal or tax advice.', []],
  'Donor-Advised Funds (Demo)' => ['/ways-to-give/donor-advised-fund', 'Demonstrative DAF overview.', []],
  'Workplace Giving (Demo)' => ['/ways-to-give/workplace', 'Demonstrative workplace giving overview.', []],
  'Corporate Partnerships (Demo)' => ['/ways-to-give/corporate', 'Demonstrative corporate partnership overview.', []],
  'Vehicle Donations (Demo)' => ['/ways-to-give/vehicle', 'Demonstrative vehicle donation overview.', []],
  'FAQ & Scam Warning' => ['/faq', 'Frequently asked questions and demo-mode scam notice.', [$makeParagraph('faq', ['field_heading' => 'Common questions', 'field_items' => [$makeParagraph('faq_item', ['field_question' => 'Are real donations processed on this site?', 'field_answer' => ['value' => '<p>No. This is a fictional demonstration site; no real payments or tax receipts are issued.</p>', 'format' => 'restricted_html']])]])]],
  'Contact (Demo)' => ['/contact', 'Demo contact information using placeholder details.', []],
  'Privacy Policy (Demo)' => ['/privacy', 'Demo privacy notice placeholder.', []],
  'Terms of Use (Demo)' => ['/terms', 'Demo terms of use placeholder.', []],
  'Accessibility Statement' => ['/accessibility', 'Accessibility commitment and WCAG 2.1 AA testing approach.', []],
];

foreach ($flexiblePages as $title => [$alias, $summary, $components]) {
  $ensureNode('flexible_page', $title, [
    'field_summary' => $summary,
    'field_components' => $components,
  ], $alias);
}

// 11. Navigation menu links (idempotent by menu_name + title).
$seedMenu = static function (string $menuName, array $items) use ($entityTypeManager): void {
  $storage = $entityTypeManager->getStorage('menu_link_content');
  foreach ($items as $weight => $item) {
    $existing = $storage->loadByProperties(['menu_name' => $menuName, 'title' => $item['title']]);
    if ($existing) {
      $parent = reset($existing);
    }
    else {
      $parent = MenuLinkContent::create([
        'title' => $item['title'],
        'link' => ['uri' => 'internal:' . $item['path']],
        'menu_name' => $menuName,
        'weight' => $weight,
        'expanded' => !empty($item['children']),
      ]);
      $parent->save();
    }
    foreach ($item['children'] ?? [] as $childWeight => $child) {
      $existingChild = $storage->loadByProperties(['menu_name' => $menuName, 'title' => $child['title']]);
      if (!$existingChild) {
        MenuLinkContent::create([
          'title' => $child['title'],
          'description' => $child['desc'] ?? '',
          'link' => ['uri' => 'internal:' . $child['path']],
          'menu_name' => $menuName,
          'parent' => $parent->getPluginId(),
          'weight' => $childWeight,
        ])->save();
      }
    }
  }
};

$seedMenu('main', [
  [
    'title' => 'Our Work',
    'path' => '/what-we-do',
    'children' => [
      ['title' => 'What We Do', 'path' => '/what-we-do', 'desc' => 'Emergency medical teams and mobile clinic models (demo).'],
      ['title' => 'Where We Work', 'path' => '/where-we-work', 'desc' => 'Illustrative regional and country profiles.'],
      ['title' => 'Medical Issues', 'path' => '/medical-issues', 'desc' => 'Maternal health, nutrition, waterborne care and trauma.'],
      ['title' => 'Our Work in Focus', 'path' => '/our-work-in-focus', 'desc' => 'Field briefings and multimedia demonstrations.'],
    ],
  ],
  [
    'title' => 'About Us',
    'path' => '/who-we-are',
    'children' => [
      ['title' => 'Who We Are', 'path' => '/who-we-are', 'desc' => 'Fictional humanitarian mission and guiding principles.'],
      ['title' => 'Finances & Accountability', 'path' => '/finances-and-accountability', 'desc' => 'Illustrative 82/12/6 fund allocation and demo reports.'],
      ['title' => 'Our History', 'path' => '/our-history', 'desc' => 'Fictional organizational timeline.'],
      ['title' => 'The US Office (Fictional)', 'path' => '/us-office', 'desc' => 'Placeholder office details for this demo.'],
    ],
  ],
  [
    'title' => 'Latest',
    'path' => '/stories/mobile-clinics-expand-maternal-care-across-valeria-republic',
    'children' => [
      ['title' => 'News & Stories', 'path' => '/stories/mobile-clinics-expand-maternal-care-across-valeria-republic', 'desc' => 'Fictional field dispatches and medical updates.'],
      ['title' => 'Magazine', 'path' => '/stories/magazine-designing-resilient-field-pharmacies', 'desc' => 'Long-form demonstration features.'],
      ['title' => 'Events Calendar', 'path' => '/events/maternal-clinics-virtual-briefing', 'desc' => 'Upcoming demo webinars and student forums.'],
      ['title' => 'Campaigns', 'path' => '/campaigns/emergency-maternal-health-fund', 'desc' => 'Illustrative fundraising campaigns.'],
    ],
  ],
  [
    'title' => 'Get Involved',
    'path' => '/get-involved',
    'children' => [
      ['title' => 'Ways to Get Involved', 'path' => '/get-involved', 'desc' => 'Community participation options (demo).'],
      ['title' => 'Ways to Give', 'path' => '/ways-to-give', 'desc' => 'Simulated giving options — not tax advice.'],
      ['title' => 'Fundraise', 'path' => '/fundraise', 'desc' => 'Peer-to-peer demo campaign ideas.'],
      ['title' => 'Student Chapters', 'path' => '/student-chapters', 'desc' => 'Campus engagement guides.'],
    ],
  ],
  ['title' => 'Work With Us', 'path' => '/work-with-us', 'children' => []],
]);

$seedMenu('donate', [
  ['title' => 'Demo Donation', 'path' => '/ways-to-give', 'children' => []],
  ['title' => 'Give Monthly (demo)', 'path' => '/ways-to-give/monthly', 'children' => []],
  ['title' => 'More Ways to Give', 'path' => '/ways-to-give/tribute', 'children' => []],
]);

$seedMenu('footer', [
  ['title' => 'Contact (Demo)', 'path' => '/contact', 'children' => []],
  ['title' => 'FAQ & Scam Warning', 'path' => '/faq', 'children' => []],
  ['title' => 'Privacy Policy (Demo)', 'path' => '/privacy', 'children' => []],
  ['title' => 'Terms of Use (Demo)', 'path' => '/terms', 'children' => []],
  ['title' => 'Accessibility Statement', 'path' => '/accessibility', 'children' => []],
]);

echo "Synthetic demo content seeded cleanly (8 published stories, 1 draft, 1 scheduled, 4 countries, 4 medical issues, 3 events, 2 campaigns, 2 reports, flexible pages, menus, synthetic users).\n";
