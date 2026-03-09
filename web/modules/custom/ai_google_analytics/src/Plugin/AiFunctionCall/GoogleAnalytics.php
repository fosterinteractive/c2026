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
use Google\Analytics\Data\V1beta\Filter\NumericFilter;
use Google\Analytics\Data\V1beta\Filter\NumericFilter\Operation;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\NumericValue;
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
    putenv('GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/web/sites/default/files/ai-integration-480315-c136045bcc0e.json');

//    $filterExpression = new FilterExpression([
//      'filter' => new Filter([
//        'field_name' => 'sessionKeyEventRate',
//        'numeric_filter' => new NumericFilter([
//          'value' => new NumericValue(['int64_value' => 0]),
//          'operation' => Operation::GREATER_THAN,
//        ]),
//      ])
//    ]);

    $filterExpression = new FilterExpression([
      'filter' => new Filter([
        'field_name' => 'pagePath',
        'in_list_filter' => new InListFilter([
          'values' => explode(',', $this->getContextValue('url')),
          'case_sensitive' => FALSE,
        ]),
      ])
    ]);

    $gaClient = new BetaAnalyticsDataClient();
    $request = (new RunReportRequest())
      ->setProperty('properties/259416874')
      ->setDateRanges([
        new DateRange([
          'start_date' => '2026-01-01',
          'end_date' => '2026-02-15',
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
      ->setMetricFilter($filterExpression);

    $response = $gaClient->runReport($request);

    // Parse the response into an array keyed by URL.
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
