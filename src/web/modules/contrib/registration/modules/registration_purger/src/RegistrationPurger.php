<?php

namespace Drupal\registration_purger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\registration\HostEntity;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service to coordinate the purging of registration related entities.
 */
class RegistrationPurger implements RegistrationPurgerInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.registration_purger')]
    protected LoggerInterface $logger,
    protected RegistrationManagerInterface $registrationManager,
    protected ?ConfigFactoryInterface $configFactory = NULL,
  ) {
    if ($configFactory === NULL) {
      $this->configFactory = \Drupal::service('config.factory');
      @trigger_error('Calling ' . __CLASS__ . '::__construct() without the $configFactory argument is deprecated in registration:3.4.5 and will be required in registration:4.0.0. See https://www.drupal.org/node/3556846', E_USER_DEPRECATED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityDelete(EntityInterface $entity): void {
    if ($this->isApplicable($entity, 'delete')) {
      $host_entity = HostEntity::create($entity);

      if ($this->isEnabled('delete', 'registration_settings')) {
        $this->purgeSettings($host_entity);
      }
      if ($this->isEnabled('delete', 'registration')) {
        $this->purgeRegistrations($host_entity);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityUpdate(EntityInterface $entity): void {
    if (!empty($entity->original) && $this->isApplicable($entity, 'update')) {
      $host_entity = HostEntity::create($entity);

      if ($this->isEnabled('update', 'registration_settings')) {
        $this->purgeSettings($host_entity);
      }
      if ($this->isEnabled('update', 'registration')) {
        $this->purgeRegistrations($host_entity);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function purgeSettings(HostEntityInterface $host_entity): void {
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadByProperties([
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
    ]);

    if (!empty($settings)) {
      $settings_storage->delete($settings);
      $this->logger->notice('Purged registration settings entity for %type %id', [
        '%type' => $host_entity->getEntityTypeId(),
        '%id' => $host_entity->id(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function purgeRegistrations(HostEntityInterface $host_entity): void {
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registrations = $registration_storage->loadByProperties([
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
    ]);

    if (!empty($registrations)) {
      $registration_storage->delete($registrations);
      $this->logger->notice('Purged registration entities (%count) for %type %id', [
        '%count' => count($registrations),
        '%type' => $host_entity->getEntityTypeId(),
        '%id' => $host_entity->id(),
      ]);
    }
  }

  /**
   * Checks if purge is applicable for a given entity and operation.
   *
   * Purge is applicable if configuration is set to purge either settings or
   * registrations for the given operation, and the entity has a registration
   * field. On updates, the entity must also be changing from enabled for
   * registration to disabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being deleted or updated.
   * @param string $operation
   *   The operation being performed on the entity, either 'delete' or 'update'.
   *
   * @return bool
   *   TRUE if purge is applicable, FALSE otherwise.
   */
  protected function isApplicable(EntityInterface $entity, string $operation): bool {
    $is_applicable = FALSE;

    // First check configuration, to avoid the overhead of checking for a
    // registration field if purge is not enabled for the operation.
    if ($this->isEnabled($operation)) {
      $is_applicable = $this->registrationManager->hasRegistrationField($entity->getEntityType(), $entity->bundle());
      if ($is_applicable && ($operation == 'update')) {
        // On update, purge is only applicable if a host entity is changing from
        // enabled for registration to disabled for registration.
        $is_applicable = FALSE;
        $host_entity = HostEntity::create($entity->original);
        if ($host_entity->isConfiguredForRegistration()) {
          $host_entity = HostEntity::create($entity);
          $is_applicable = !$host_entity->isConfiguredForRegistration();
        }
      }
    }

    return $is_applicable;
  }

  /**
   * Checks if purge is enabled for a given operation and entity type ID.
   *
   * @param string $operation
   *   The operation being performed on the entity.
   * @param string|null $entity_type_id
   *   (Optional) The entity type ID, if available.
   *   If set, must be either 'registration_settings' or 'registration',
   *   If NULL, TRUE is returned if purge is enabled for either type.
   *
   * @return bool
   *   TRUE if purge is enabled, FALSE otherwise.
   */
  protected function isEnabled(string $operation, ?string $entity_type_id = NULL): bool {
    if (is_null($entity_type_id)) {
      return $this->isEnabled($operation, 'registration_settings') || $this->isEnabled($operation, 'registration');
    }

    // Set a default if settings do not exist because database updates have
    // not been run yet. For backwards compatibility, purge on host entity
    // delete is enabled by default, and purge on host entity update is not.
    $default = ($operation == 'delete');

    $setting = 'purge_' . $entity_type_id . '_on_' . $operation;
    return (bool) $this->configFactory->get('registration_purger.settings')->get($setting) ?? $default;
  }

}
