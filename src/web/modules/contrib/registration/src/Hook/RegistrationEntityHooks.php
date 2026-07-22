<?php

namespace Drupal\registration\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\RegistrationHelper;
use Drupal\registration\RegistrationHostAccessControlHandler;
use Drupal\registration\RegistrationHostEntityHandler;

/**
 * Entity hook implementations for registration.
 */
class RegistrationEntityHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account) {
    // Do not allow settings to be deleted by site builders. These are deleted
    // automatically when registration fields are removed. Using a locked field
    // was not an option here because site builders need to be able to provide
    // field default values for registration settings fields.
    if ($entity instanceof FieldConfigInterface) {
      if ($operation == 'delete') {
        if ($entity->getType() == 'registration_settings') {
          return AccessResult::forbidden();
        }
      }
    }
    // No opinion.
    return AccessResult::neutral();
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity instanceof FieldConfig) {
      if ($entity->getType() == 'registration') {
        // Rebuild routes, block plugins and local tasks when Registration
        // fields are deleted. Services are not injected to avoid circular
        // references.
        \Drupal::service("router.builder")->rebuild();
        \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
        \Drupal::service('plugin.manager.menu.local_task')->clearCachedDefinitions();
      }
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof FieldConfig) {
      if ($entity->getType() == 'registration') {
        // Rebuild routes, block plugins and local tasks when Registration
        // fields are added. Services are not injected to avoid circular
        // references.
        \Drupal::service("router.builder")->rebuild();
        \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
        \Drupal::service('plugin.manager.menu.local_task')->clearCachedDefinitions();
      }
    }
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];
    if (($entity instanceof RegistrationInterface) && $entity->access('view')) {
      // Add a View operation for registrations. This will be first, followed
      // by the Edit and Delete operations (if the user has those permissions).
      $operations['view'] = [
        'title' => $this->t('View'),
        'url' => Url::fromRoute('entity.registration.canonical', [
          'registration' => $entity->id(),
        ]),
        'weight' => -1,
      ];
    }
    return $operations;
  }

  /**
   * Implements hook_entity_operation_alter().
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    if ($entity instanceof RegistrationInterface) {
      // Make registration operations links language aware.
      RegistrationHelper::applyInterfaceLanguageToLinks($operations);
    }
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    // Validate new registration fields since Field UI skips constraints.
    if ($entity->isNew()) {
      if (($entity instanceof FieldConfig) || ($entity instanceof FieldStorageConfig)) {
        if ($entity->getType() == 'registration') {
          $violations = $entity->getTypedData()->validate();
          // Field UI catches exceptions thrown
          // and displays the error to the user.
          foreach ($violations as $violation) {
            /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
            throw new \InvalidArgumentException($violation->getMessage());
          }
        }
      }
    }

    // Always recompute email address since it is derived from other fields.
    if ($entity instanceof RegistrationInterface) {
      if ($user = $entity->getUser()) {
        $entity->set('mail', $user->getEmail());
      }
      else {
        $entity->set('mail', $entity->getAnonymousEmail());
      }

      // If the registration user was updated,
      // clear cache tags for the old user.
      if ($entity->original && ($entity->getUserId() != $entity->original->getUserId())) {
        if ($user = $entity->original->getUser()) {
          Cache::invalidateTags(['registration.user:' . $user->id()]);
        }
      }
    }
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter', order: Order::Last)]
  public function entityTypeAlter(array &$entity_types): void {
    // Add validation constraints to the field config entity types.
    // This prevents more than one registration field on a bundle, etc.
    $entity_types['field_config']->addConstraint('registration_field');
    $entity_types['field_storage_config']->addConstraint('registration_field');

    // If the Entity API contributed module is installed, use its views data
    // handler to derive additional reverse relationships. This is a workaround
    // to core issue https://www.drupal.org/project/drupal/issues/2706431.
    // @todo Remove once issue #2706431 is resolved.
    if (\Drupal::moduleHandler()->moduleExists('entity')) {
      $entity_types['registration']->setHandlerClass('views_data', 'Drupal\entity\EntityViewsData');
    }

    // If the host_entity handler is overridden, ensure that the
    // registration_host_entity handler is too, providing back compatibility.
    // @todo Remove before registration:4.0.0.
    $modern = $entity_types['registration']->getHandlerClass('registration_host_entity');
    $legacy = $entity_types['registration']->getHandlerClass('host_entity');
    if ($legacy !== $modern) {
      // The registration and registration_waitlist modules provide
      // their own host_entity handlers, but they also provide
      // registration_host_entity handlers too, so can be ignored.
      if (!in_array($legacy, [RegistrationHostEntityHandler::class, 'Drupal\registration_waitlist\HostEntityHandler'])) {
        @trigger_error("Using the host_entity handler of the registration entity type is deprecated in registration:3.1.5 and is removed from registration:4.0.0. Use the registration_host_entity handler for the host entity type instead. See https://www.drupal.org/node/3462126", E_USER_DEPRECATED);
        $entity_types['registration']->setHandlerClass('registration_host_entity', $legacy);
      }
    }
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type->getHandlerClass('registration_host_access')) {
        $entity_type->setHandlerClass('registration_host_access', RegistrationHostAccessControlHandler::class);
      }
      if (!$entity_type->getHandlerClass('registration_host_entity')) {
        $entity_type->setHandlerClass('registration_host_entity', RegistrationHostEntityHandler::class);
      }
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    // Use a static to ensure a rebuild is done at most once per page load.
    static $rebuild = FALSE;
    if (!$rebuild) {
      // Rebuild routes and local tasks when Registration fields are updated.
      // Services are not injected to avoid circular references.
      if ($entity instanceof EntityFormDisplay) {
        $entity_type_id = $entity->getTargetEntityTypeId();
        $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
        if (\Drupal::service('registration.manager')->hasRegistrationField($entity_type)) {
          $rebuild = TRUE;
          \Drupal::service("router.builder")->rebuild();
          \Drupal::service('plugin.manager.menu.local_task')->clearCachedDefinitions();
        }
      }
    }
  }

}
