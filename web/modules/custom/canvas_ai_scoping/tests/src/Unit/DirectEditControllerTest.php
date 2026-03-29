<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai_scoping\Unit;

use Drupal\canvas_ai\AiResponseValidator;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\canvas_ai_scoping\Controller\DirectEditController;
use Drupal\canvas_ai_scoping\Service\ComponentSchemaLoaderInterface;
use Drupal\canvas_ai_scoping\Service\DirectEditMatcher;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the direct edit controller.
 *
 * @group canvas_ai_scoping
 * @coversDefaultClass \Drupal\canvas_ai_scoping\Controller\DirectEditController
 */
final class DirectEditControllerTest extends UnitTestCase {

  /**
   * @covers ::edit
   */
  public function testEditSeedsTempstoreFromLayoutBeforeComponentValidation(): void {
    $schemaLoader = $this->createMock(ComponentSchemaLoaderInterface::class);
    $matcher = new DirectEditMatcher($schemaLoader);
    $responseValidator = $this->createMock(AiResponseValidator::class);
    $pageBuilderHelper = $this->createMock(CanvasAiPageBuilderHelper::class);
    $tempStore = $this->createMock(CanvasAiTempStore::class);
    $csrfTokenGenerator = $this->createMock(CsrfTokenGenerator::class);
    $logger = $this->createMock(LoggerInterface::class);

    $csrfTokenGenerator->expects($this->once())
      ->method('validate')
      ->with('valid-token', 'canvas_ai.canvas_builder')
      ->willReturn(TRUE);

    $schemaLoader->expects($this->once())
      ->method('getPropAliases')
      ->with('sdc.byte_theme.heading')
      ->willReturn([
        'heading' => 'heading_text',
      ]);

    $schemaLoader->expects($this->once())
      ->method('getEnumValues')
      ->with('heading_text', 'sdc.byte_theme.heading')
      ->willReturn(NULL);

    $tempStore->expects($this->once())
      ->method('setData')
      ->with(
        CanvasAiTempStore::COMPONENTS_IN_PAGE_WITH_PROP_VALUES_KEY,
        '{"390aa880-8d99-46f8-8727-3d0c762ece8a":{"heading_text":"Old"}}'
      );

    $responseValidator->expects($this->once())
      ->method('validateComponentExistsInPage')
      ->with('390aa880-8d99-46f8-8727-3d0c762ece8a');

    $responseValidator->expects($this->once())
      ->method('validateComponentPropUpdate')
      ->with('sdc.byte_theme.heading', ['heading_text' => 'Welcome']);

    $pageBuilderHelper->expects($this->once())
      ->method('populateMediaPropIfNeeded')
      ->with('sdc.byte_theme.heading', '390aa880-8d99-46f8-8727-3d0c762ece8a', ['heading_text' => 'Welcome'])
      ->willReturn(['heading_text' => 'Welcome']);

    $pageBuilderHelper->expects($this->once())
      ->method('includeUpdateOperations')
      ->with([
        [
          'uuid' => '390aa880-8d99-46f8-8727-3d0c762ece8a',
          'fieldValues' => ['heading_text' => 'Welcome'],
        ],
      ], ['status' => TRUE])
      ->willReturn([
        'status' => TRUE,
        'operations' => [
          [
            'operation' => 'UPDATE',
            'components' => [
              [
                'uuid' => '390aa880-8d99-46f8-8727-3d0c762ece8a',
                'fieldValues' => ['heading_text' => 'Welcome'],
              ],
            ],
          ],
        ],
      ]);

    $logger->expects($this->once())
      ->method('notice');

    $controller = new DirectEditController(
      $matcher,
      $responseValidator,
      $pageBuilderHelper,
      $tempStore,
      $csrfTokenGenerator,
      $logger,
    );

    $request = Request::create(
      '/admin/api/canvas/direct-edit',
      'POST',
      server: [
        'HTTP_X_CSRF_TOKEN' => 'valid-token',
      ],
      content: json_encode([
        'message' => 'Change the heading to Welcome',
        'component_uuid' => '390aa880-8d99-46f8-8727-3d0c762ece8a',
        'component_name' => 'sdc.byte_theme.heading',
        'layout' => '{"390aa880-8d99-46f8-8727-3d0c762ece8a":{"heading_text":"Old"}}',
      ], JSON_THROW_ON_ERROR)
    );

    $response = $controller->edit($request);
    $payload = json_decode((string) $response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['status']);
    $this->assertTrue($payload['direct_edit']);
    $this->assertSame(0, $payload['tokens_used']);
    $this->assertSame('heading_text', $payload['matched_prop']);
    $this->assertSame('Welcome', $payload['matched_value']);
  }

}
