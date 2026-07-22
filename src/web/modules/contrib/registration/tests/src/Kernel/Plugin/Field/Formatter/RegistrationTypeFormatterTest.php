<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\registration\Plugin\Field\FieldFormatter\RegistrationTypeFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the registration_type formatter.
 */
#[CoversClass(RegistrationTypeFormatter::class)]
#[Group('registration')]
class RegistrationTypeFormatterTest extends FormatterTestBase {

  use NodeCreationTrait;

  /**
   * Tests rendering a registration type name.
   */
  public function testRegistrationTypeFormatter() {
    // A new host entity.
    $node = $this->createNode();
    $build = $node->get('event_registration')->view([
      'type' => 'registration_type',
      'label' => 'hidden',
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('Conference', $output);

    // An existing host entity.
    $node->save();
    $build = $node->get('event_registration')->view([
      'type' => 'registration_type',
      'label' => 'hidden',
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('Conference', $output);
  }

}
