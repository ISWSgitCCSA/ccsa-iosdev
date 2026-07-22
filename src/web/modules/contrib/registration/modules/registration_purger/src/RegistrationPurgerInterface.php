<?php

namespace Drupal\registration_purger;

use Drupal\Core\Entity\EntityInterface;
use Drupal\registration\HostEntityInterface;

/**
 * Defines the interface for the registration purger service.
 */
interface RegistrationPurgerInterface {

  /**
   * Responds to entity deletion.
   *
   * Check if this entity has associated registration data, and purges it if
   * it does.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being deleted.
   *
   * @see hook_entity_delete()
   */
  public function onEntityDelete(EntityInterface $entity): void;

  /**
   * Responds to entity update.
   *
   * Check if this entity has associated registration data, and purges it if
   * it does and the entity was just changed to disable registrations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being updated.
   *
   * @see hook_entity_update()
   */
  public function onEntityUpdate(EntityInterface $entity): void;

  /**
   * Purges registration settings for a host entity.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   */
  public function purgeSettings(HostEntityInterface $host_entity): void;

  /**
   * Purges registrations for a host entity.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   */
  public function purgeRegistrations(HostEntityInterface $host_entity): void;

}
