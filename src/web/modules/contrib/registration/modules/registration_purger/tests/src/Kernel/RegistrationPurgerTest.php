<?php

namespace Drupal\Tests\registration_purger\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\HostEntity;
use Drupal\registration_purger\RegistrationPurger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests registration purger.
 */
#[CoversClass(RegistrationPurger::class)]
#[Group('registration')]
class RegistrationPurgerTest extends RegistrationPurgerKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests purge on host entity delete.
   */
  public function testRegistrationPurgerOnDelete() {
    $node = $this->createAndSaveNode();
    $id1 = $node->id();
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    $node = $this->createAndSaveNode();
    $id2 = $node->id();
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id2));
    $this->assertEquals(3, $this->getRegistrationsCount($id2));

    // Delete the node.
    $node->delete();
    // Confirm its settings and registrations are gone.
    $this->assertEquals(0, $this->getSettingsCount($id2));
    $this->assertEquals(0, $this->getRegistrationsCount($id2));
    // Confirm the data for the other host is untouched.
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    // Ensure a node with no settings or registrations can be deleted
    // without incident.
    $node = $this->createAndSaveNode();
    $id3 = $node->id();
    $this->assertEquals(0, $this->getSettingsCount($id3));
    $this->assertEquals(0, $this->getRegistrationsCount($id3));
    $node->delete();
    $this->assertEquals(0, $this->getSettingsCount($id3));
    $this->assertEquals(0, $this->getRegistrationsCount($id3));
    // Confirm the data for the other host is untouched.
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    // Ensure a node that has registrations, but is no longer configured for
    // registration, is properly purged.
    $node = $this->createAndSaveNode();
    $id4 = $node->id();
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id4));
    $this->assertEquals(1, $this->getRegistrationsCount($id4));
    $node->set('event_registration', NULL);
    $node->save();
    $node->delete();
    // Confirm its settings and registrations are gone.
    $this->assertEquals(0, $this->getSettingsCount($id4));
    $this->assertEquals(0, $this->getRegistrationsCount($id4));
    // Confirm the data for the other host is untouched.
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');

    // Configure purger settings to not purge registration data after
    // host entity delete.
    $config_factory
      ->getEditable('registration_purger.settings')
      ->set('purge_registration_settings_on_delete', FALSE)
      ->set('purge_registration_on_delete', FALSE)
      ->save();

    $node = $this->createAndSaveNode();
    $id = $node->id();
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id));
    $this->assertEquals(4, $this->getRegistrationsCount($id));
    $node->delete();
    // Confirm the data for the host is untouched.
    $this->assertEquals(1, $this->getSettingsCount($id));
    $this->assertEquals(4, $this->getRegistrationsCount($id));

    // Purge manually.
    $host_entity = HostEntity::create($node);
    $purger = $this->container->get('registration_purger.purger');
    $purger->purgeSettings($host_entity);
    $this->assertEquals(0, $this->getSettingsCount($id));
    $this->assertEquals(4, $this->getRegistrationsCount($id));
    $purger->purgeRegistrations($host_entity);
    $this->assertEquals(0, $this->getSettingsCount($id));
    $this->assertEquals(0, $this->getRegistrationsCount($id));
  }

  /**
   * Tests purge on host entity update.
   */
  public function testRegistrationPurgerOnUpdate() {
    $node = $this->createAndSaveNode();
    $id1 = $node->id();
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));
    $node->set('event_registration', NULL);
    $node->save();
    // By default, registration related entities are not purged
    // for newly disabled hosts.
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    // Re-enable registrations.
    $node->set('event_registration', 'conference');
    $node->save();
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(2, $this->getRegistrationsCount($id1));

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');

    // Configure purger settings to delete registration related entities
    // for newly disabled hosts.
    $config_factory
      ->getEditable('registration_purger.settings')
      ->set('purge_registration_settings_on_update', TRUE)
      ->set('purge_registration_on_update', TRUE)
      ->save();

    $node->set('event_registration', NULL);
    $node->save();
    $this->assertEquals(0, $this->getSettingsCount($id1));
    $this->assertEquals(0, $this->getRegistrationsCount($id1));

    // Re-enable registrations.
    $node->set('event_registration', 'conference');
    $node->save();

    // Register.
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $registration = $this->createAndSaveRegistration($node);
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(3, $this->getRegistrationsCount($id1));

    // Registration is still enabled, so saving the node should not purge.
    $node->set('title', 'A node title that is meaningless');
    $node->save();
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(3, $this->getRegistrationsCount($id1));

    // Purge settings but not registrations.
    $config_factory
      ->getEditable('registration_purger.settings')
      ->set('purge_registration_on_update', FALSE)
      ->save();
    $node->set('event_registration', NULL);
    $node->save();
    $this->assertEquals(0, $this->getSettingsCount($id1));
    $this->assertEquals(3, $this->getRegistrationsCount($id1));

    // Purge registrations but not settings.
    $config_factory
      ->getEditable('registration_purger.settings')
      ->set('purge_registration_settings_on_update', FALSE)
      ->set('purge_registration_on_update', TRUE)
      ->save();
    $node->set('event_registration', 'conference');
    $node->save();
    $registration = $this->createAndSaveRegistration($node);
    $node->set('event_registration', NULL);
    $node->save();
    $this->assertEquals(1, $this->getSettingsCount($id1));
    $this->assertEquals(0, $this->getRegistrationsCount($id1));
  }

  /**
   * Gets the count of registrations for a given node.
   *
   * @param int $id
   *   The node ID.
   *
   * @return int
   *   The count.
   */
  protected function getRegistrationsCount(int $id): int {
    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $id)
      ->condition('entity_type_id', 'node');

    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Gets the count of registration settings for a given node.
   *
   * @param int $id
   *   The node ID.
   *
   * @return int
   *   The count. Should return either 1 or zero.
   */
  protected function getSettingsCount(int $id): int {
    $database = Database::getConnection();
    $query = $database->select('registration_settings_field_data')
      ->condition('entity_id', $id)
      ->condition('entity_type_id', 'node');

    return $query->countQuery()->execute()->fetchField();
  }

}
