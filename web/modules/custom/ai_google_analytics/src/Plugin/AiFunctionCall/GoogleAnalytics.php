<?php

namespace Drupal\ai_google_analytics\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\InListFilter;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;

/**
 * Plugin implementation of the get current URL of an entity..
 */
#[FunctionCall(
  id: 'ai_google_analytics:get_data',
  function_name: 'ai_google_analytics_get_data',
  name: 'Google Analytics',
  description: 'Retrieve conversion rate information from GA4.',
  group: 'information_tools',
  context_definitions: [
    'url' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("URLs"),
      description: new TranslatableMarkup("The URLs of the pages to get analytics for."),
      required: FALSE,
    ),
  ],
)]
class GoogleAnalytics extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() : void {
    $config = \Drupal::config('ai_google_analytics.settings');
    $credentials_uri = $config->get('credentials_uri');
    $credentials_path = \Drupal::service('file_system')->realpath($credentials_uri);
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentials_path);

    $url = $this->getContextValue('url');
    if (empty($url)) {
      $this->setOutput('No URLs provided.');
      return;
    }

    $filterExpression = new FilterExpression([
      'filter' => new Filter([
        'field_name' => 'pagePath',
        'in_list_filter' => new InListFilter([
          'values' => explode(',', $url),
          'case_sensitive' => FALSE,
        ]),
      ])
    ]);

    $gaClient = new BetaAnalyticsDataClient();
    $endDate = (new \DateTimeImmutable())->format('Y-m-d');
    $startDate = (new \DateTimeImmutable('-90 days'))->format('Y-m-d');
    $request = (new RunReportRequest())
      ->setProperty('properties/' . $config->get('property_id'))
      ->setDateRanges([
        new DateRange([
          'start_date' => $startDate,
          'end_date' => $endDate,
        ]),
      ])
      ->setDimensions([
        new Dimension([
          'name' => 'pagePath',
        ]),
      ])
      ->setMetrics([
        new Metric([
          'name' => 'engagedSessions',
        ]),
        new Metric([
          'name' => 'bounceRatePercentage',
          'expression' => 'bounceRate*100',
        ]),
        new Metric([
          'name' => 'conversionRatePercentage',
          'expression' => 'sessionKeyEventRate*100',
        ]),
      ])
      ->setDimensionFilter($filterExpression);

    $response = $gaClient->runReport($request);

    // Parse the response into an array keyed by URL.
    $output = [];
    foreach ($response->getRows() as $row) {
      $output[$row->getDimensionValues()[0]->getValue()] = [
        'engagedSessions' => $row->getMetricValues()[0]->getValue(),
        'bounceRate' => $row->getMetricValues()[1]->getValue(),
        'keyEventRate' => $row->getMetricValues()[2]->getValue(),
      ];
    }

    $this->setStructuredOutput($output);
    $this->setOutput((string) json_encode($output, JSON_UNESCAPED_SLASHES));
  }

}
