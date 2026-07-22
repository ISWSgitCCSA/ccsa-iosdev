<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration_waitlist\HostEntity;
use Drupal\registration_waitlist\HostEntityHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the registration waitlist Host Entity class.
 */
#[CoversClass(HostEntity::class)]
#[Group('registration')]
class RegistrationWaitListHostEntityTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests the registration waitlist Host Entity class in various scenarios.
   */
  public function testWaitListHostEntity() {
    $node = $this->createAndSaveNode();

    // Fill standard capacity.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->save();
    $host_entity = $registration->getHostEntity();
    // The next registration should not be placed on the wait list.
    $this->assertFalse($host_entity->shouldAddToWaitList());

    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 4);
    $registration->save();
    // Standard capacity is now full.
    // The next registration should be placed on the wait list.
    $this->assertTrue($host_entity->shouldAddToWaitList());

    // Wait list is enabled but no spaces taken yet.
    $this->assertTrue($host_entity->isWaitListEnabled());
    $this->assertEquals(0, $host_entity->getWaitListSpacesReserved());

    // There is room somewhere for a registration.
    $this->assertTrue($host_entity->hasRoom());
    // There is no room off the wait list.
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    // There is room on the wait list.
    $this->assertTrue($host_entity->hasRoomOnWaitList());
    $this->assertTrue($host_entity->isAvailableForRegistration());
    $this->assertTrue($host_entity->isEnabledForRegistration());
    $this->assertTrue($host_entity->shouldAddToWaitList());

    // Hold a registration while the wait list is active.
    $registration->set('state', 'held');
    $registration->save();
    $this->assertTrue($registration->isHeld());
    $this->assertTrue($host_entity->hasRoom());
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    $this->assertTrue($host_entity->hasRoomOnWaitList());
    $this->assertTrue($host_entity->shouldAddToWaitList());
    $this->assertTrue($host_entity->isAvailableForRegistration());
    $this->assertTrue($host_entity->isEnabledForRegistration());

    // Complete a registration while the wait list is active.
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertTrue($registration->isComplete());
    $this->assertTrue($host_entity->hasRoom());
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    $this->assertTrue($host_entity->hasRoomOnWaitList());
    $this->assertTrue($host_entity->shouldAddToWaitList());
    $this->assertTrue($host_entity->isAvailableForRegistration());
    $this->assertTrue($host_entity->isEnabledForRegistration());

    // Wait list spaces reserved and remaining.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 2);
    $registration->save();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->save();
    $this->assertEquals(3, $host_entity->getWaitListSpacesReserved());
    $this->assertEquals(7, $host_entity->getWaitListSpacesRemaining());

    // Still room on the wait list.
    $this->assertTrue($host_entity->hasRoom());
    $this->assertTrue($host_entity->hasRoomOnWaitList());
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    $this->assertTrue($host_entity->shouldAddToWaitList());
    $this->assertTrue($host_entity->isAvailableForRegistration());
    $this->assertTrue($host_entity->isEnabledForRegistration());

    // Disable the wait list.
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('registration_waitlist_enable', FALSE);
    $settings->save();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 5);
    $registration->save();
    $this->assertFalse($host_entity->hasRoom());
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    $this->assertFalse($host_entity->hasRoomOnWaitList());
    $this->assertFalse($host_entity->shouldAddToWaitList());
    $this->assertFalse($host_entity->isAvailableForRegistration());
    $this->assertFalse($host_entity->isEnabledForRegistration());
    $this->assertNull($host_entity->getWaitListSpacesRemaining());

    // Delete the host entity and the registration.
    $node->delete();
    $registration = $this->entityTypeManager->getStorage('registration')->load($registration->id());
    $registration->delete();
  }

  /**
   * Test deprecation of 'host_entity' handler on registration entity.
   */
  #[Group('legacy')]
  public function testHostHandlerDeprecation(): void {
    if (version_compare(\Drupal::VERSION, '11.0.0', '<')) {
      $this->markTestSkipped('Registration deprecation tests fail on Drupal 10.');
    }

    $node = $this->createAndSaveNode();

    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $this->assertInstanceOf(HostEntityHandler::class, $handler);
    $host_entity = $handler->createHostEntity($node);
    $this->assertInstanceOf(HostEntity::class, $host_entity);

    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $this->assertInstanceOf(HostEntityHandler::class, $handler);
    $this->expectDeprecation('Using the host_entity handler of the registration entity type is deprecated in registration:3.1.5 and is removed from registration:4.0.0. Use the registration_host_entity handler for the host entity type instead. See https://www.drupal.org/node/3462126');
    $host_entity = $handler->createHostEntity($node);
    $this->assertInstanceOf(HostEntity::class, $host_entity);
  }

}
