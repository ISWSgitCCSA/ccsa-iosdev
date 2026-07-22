<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Plugin\Field\FieldFormatter\RegistrationHostEntityFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the registration_host_entity formatter.
 */
#[CoversClass(RegistrationHostEntityFormatter::class)]
#[Group('registration')]
class RegistrationHostEntityFormatterTest extends FormatterTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests rendering a host entity title.
   */
  public function testRegistrationHostEntityFormatter() {
    // The user must be able to view the host entity in order to view its title.
    $account = $this->createUser(['access content']);
    $this->setCurrentUser($account);

    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();

    // Host entity title, no link.
    $build = $registration->get('host_entity')->view([
      'type' => 'registration_host_entity',
      'label' => 'hidden',
      'settings' => [
        'link' => FALSE,
      ],
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('My event', $output);

    // Host entity title, with link.
    $build = $registration->get('host_entity')->view([
      'type' => 'registration_host_entity',
      'label' => 'hidden',
      'settings' => [
        'link' => TRUE,
      ],
    ]);
    $output = $this->renderElement($build);
    $this->assertEquals('<a href="/node/1" hreflang="en">My event</a>', $output);

    $registration->set('entity_id', 999);
    $registration->save();
    $registration = $this->reloadEntity($registration);

    // No host entity.
    $build = $registration->get('host_entity')->view([
      'type' => 'registration_host_entity',
      'label' => 'hidden',
      'settings' => [
        'link' => FALSE,
      ],
    ]);
    $output = $this->renderElement($build);
    $this->assertEmpty($output);
  }

}
