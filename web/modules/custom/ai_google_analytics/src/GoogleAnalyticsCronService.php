<?php

declare(strict_types=1);

namespace Drupal\ai_google_analytics;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\path_alias\AliasManagerInterface;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;

/**
 * Fetches GA4 metrics for monitored Canvas pages during cron.
 */
class GoogleAnalyticsCronService {

  /**
   * Constructs a GoogleAnalyticsCronService.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   The path alias manager.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    protected readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * Fetches GA4 metrics for all monitored Canvas pages.
   *
   * Saves engaged sessions, bounce rate, and key event rate to entity fields.
   * Handles errors per-page so a single failure does not abort the entire run.
   */
  public function fetchMetrics(): void {
    $logger = $this->loggerFactory->get('ai_google_analytics');
    $config = $this->configFactory->get('ai_google_analytics.settings');
    $credentials_uri = $config->get('credentials_uri');

    if (!$credentials_uri) {
      $logger->warning('No credentials file configured; skipping GA cron.');
      return;
    }

    $property_id = $config->get('property_id');
    if (!$property_id) {
      $logger->warning('No GA4 property ID configured; skipping GA cron.');
      return;
    }

    $pages = $this->getMonitoredPages();
    if (empty($pages)) {
      return;
    }

    $credentials_path = $this->fileSystem->realpath($credentials_uri);
    if (!$credentials_path || !file_exists($credentials_path)) {
      $logger->error('Credentials file not found at %uri.', ['%uri' => $credentials_uri]);
      return;
    }

    try {
      putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentials_path);
      $ga_client = new BetaAnalyticsDataClient();
    }
    catch (\Throwable $e) {
      $logger->error('Failed to initialize GA client: @message', ['@message' => $e->getMessage()]);
      return;
    }

    $end_date = (new \DateTimeImmutable())->format('Y-m-d');
    $start_date = (new \DateTimeImmutable('-90 days'))->format('Y-m-d');

    foreach ($pages as $page) {
      try {
        $this->fetchPageMetrics($ga_client, $page, $property_id, $start_date, $end_date);
      }
      catch (\Throwable $e) {
        $logger->error('GA fetch failed for page %id (%label): @message', [
          '%id' => $page->id(),
          '%label' => $page->label(),
          '@message' => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Fetches and saves GA metrics for a single page.
   *
   * @param \Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient $ga_client
   *   The GA client.
   * @param \Drupal\Core\Entity\ContentEntityInterface $page
   *   The Canvas page entity.
   * @param string $property_id
   *   The GA4 property ID.
   * @param string $start_date
   *   The start date (Y-m-d).
   * @param string $end_date
   *   The end date (Y-m-d).
   */
  protected function fetchPageMetrics(BetaAnalyticsDataClient $ga_client, $page, string $property_id, string $start_date, string $end_date): void {
    $internal = $page->toUrl('canonical', ['alias' => FALSE])->toString();
    $alias = $this->aliasManager->getAliasByPath($internal);
    $path = ($alias && $alias !== $internal) ? $alias : $internal;

    $filter_expression = new FilterExpression([
      'filter' => new Filter([
        'field_name' => 'pagePath',
        'string_filter' => new StringFilter([
          'value' => $path,
          'match_type' => MatchType::EXACT,
        ]),
      ]),
    ]);

    $request = (new RunReportRequest())
      ->setProperty('properties/' . $property_id)
      ->setDateRanges([
        new DateRange([
          'start_date' => $start_date,
          'end_date' => $end_date,
        ]),
      ])
      ->setDimensions([
        new Dimension(['name' => 'pagePath']),
      ])
      ->setMetrics([
        new Metric(['name' => 'engagedSessions']),
        new Metric([
          'name' => 'bounceRatePercentage',
          'expression' => 'bounceRate*100',
        ]),
        new Metric([
          'name' => 'conversionRatePercentage',
          'expression' => 'sessionKeyEventRate*100',
        ]),
      ])
      ->setDimensionFilter($filter_expression);

    $response = $ga_client->runReport($request);
    $rows = $response->getRows();
    if (empty($rows)) {
      return;
    }

    $row = $rows[0];
    $page->set('engaged_sessions', $row->getMetricValues()[0]->getValue());
    $page->set('bounce_rate', $row->getMetricValues()[1]->getValue());
    $page->set('key_event_rate', $row->getMetricValues()[2]->getValue());
    $page->save();
  }

  /**
   * Returns Canvas page entities marked for analytics monitoring.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Canvas page entities with monitoring enabled.
   */
  protected function getMonitoredPages(): array {
    $storage = $this->entityTypeManager->getStorage('canvas_page');
    $ids = $storage->getQuery()
      ->condition('monitor', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($ids)) {
      return [];
    }

    return $storage->loadMultiple($ids);
  }

}
