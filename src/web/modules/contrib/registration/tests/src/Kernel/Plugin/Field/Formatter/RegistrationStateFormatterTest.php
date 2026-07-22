<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Plugin\Field\FieldFormatter\RegistrationStateFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the registration_state formatter.
 */
#[CoversClass(RegistrationStateFormatter::class)]
#[Group('registration')]
class RegistrationStateFormatterTest extends FormatterTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests rendering registration state.
   */
  public function testRegistrationStateFormatter() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $build = $registration->get('state')->view([
      'type' => 'registration_state',
      'label' => 'hidden',
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('Pending', $output);
  }

}
