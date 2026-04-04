<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Controller;

use Drupal\ai_agents_canvas_direct_edit\Telemetry\TelemetryAggregatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns aggregated telemetry data as JSON.
 *
 * @internal
 */
class TelemetryExportController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a TelemetryExportController.
   *
   * @param \Drupal\ai_agents_canvas_direct_edit\Telemetry\TelemetryAggregatorInterface $aggregator
   *   The telemetry aggregator service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected readonly TelemetryAggregatorInterface $aggregator,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai_agents_canvas_direct_edit.telemetry_aggregator'),
      $container->get('config.factory'),
    );
  }

  /**
   * Returns aggregated telemetry data as JSON.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with aggregated telemetry data.
   */
  public function export(Request $request): JsonResponse {
    $config = $this->configFactory->get('ai_agents_canvas_direct_edit.settings');

    if (!$config->get('telemetry.export_enabled')) {
      return new JsonResponse(['error' => 'Telemetry export is not enabled.'], 503);
    }

    $now = time();
    $thirtyDaysAgo = $now - (30 * 86400);

    $since = (int) $request->query->get('since', (string) $thirtyDaysAgo);
    $until = (int) $request->query->get('until', (string) $now);

    if ($since > $until) {
      return new JsonResponse(['error' => 'since must be before until.'], 400);
    }

    $summary = $this->aggregator->getSummary($since, $until);

    return new JsonResponse([
      'range' => [
        'since' => $since,
        'until' => $until,
      ],
      'data' => $summary,
    ]);
  }

}
