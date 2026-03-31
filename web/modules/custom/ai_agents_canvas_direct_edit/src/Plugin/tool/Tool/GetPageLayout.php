<?php

declare(strict_types=1);

namespace Drupal\ai_agents_canvas_direct_edit\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\canvas_ai\CanvasAiTempStore;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the current Canvas page layout tree from tempstore.
 *
 * Use this tool to understand the current page structure before making edits.
 * Returns the raw layout data stored by the Canvas AI session, or a message
 * indicating no layout is available.
 */
#[Tool(
  id: 'ai_agents_canvas_direct_edit:get_page_layout',
  label: new TranslatableMarkup('Get Page Layout'),
  description: new TranslatableMarkup('Returns the current Canvas page layout tree from tempstore. Use this to understand page structure before making edits.'),
  operation: ToolOperation::Read,
  input_definitions: [],
)]
final class GetPageLayout extends ToolBase {

  /**
   * The Canvas AI tempstore service.
   */
  protected CanvasAiTempStore $canvasAiTempStore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->canvasAiTempStore = $container->get('canvas_ai.tempstore');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $layout = $this->canvasAiTempStore->getData(CanvasAiTempStore::CURRENT_LAYOUT_KEY);

    if ($layout === NULL || $layout === '') {
      return ExecutableResult::success(
        new TranslatableMarkup('No layout currently stored in tempstore.'),
        ['result' => ''],
      );
    }

    return ExecutableResult::success(
      new TranslatableMarkup('Current page layout retrieved from tempstore.'),
      ['result' => $layout],
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'use ai agents canvas direct edit');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
