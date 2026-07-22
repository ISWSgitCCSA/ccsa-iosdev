<?php

namespace Drupal\registration_test\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;

/**
 * Hook implementations for registration testing.
 */
class RegistrationTestHooks {

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    // Add a base registration field to node entities.
    if ($entity_type->id() === 'node') {
      // Default to enabled with capacity 5 and max 2 spaces per registration.
      $default_settings = [
        'status' => [
          'value' => TRUE,
        ],
        'capacity' => [
          0 => [
            'value' => 5,
          ],
        ],
        'maximum_spaces' => [
          0 => [
            'value' => 2,
          ],
        ],
        'from_address' => [
          0 => [
            'value' => 'test@example.com',
          ],
        ],
      ];
      $fields['event_registration'] = BaseFieldDefinition::create('registration')
        ->setLabel(t('Registration'))
        ->setDefaultValue([
          'registration_settings' => serialize($default_settings),
        ]);
    }
    return $fields;
  }

  /**
   * Implements hook_registration_presave().
   */
  #[Hook('registration_presave')]
  public function registrationPresave(RegistrationInterface $registration) {
    if ($registration->getAnonymousEmail() == 'trigger_presave_hook@example.org') {
      $registration->set('state', 'complete');
    }
  }

  /**
   * Implements hook_registration_host__access().
   */
  #[Hook('registration_host__access')]
  public function hostAccess(HostEntityInterface $host_entity, $operation, AccountInterface $account) {
    if ($operation === 'manage') {
      $this->state->set("registration_test_host_access_manage_hook_fired", TRUE);
      if ($this->state->get("registration_test_host_access_manage_result") === 'allowed') {
        return AccessResult::allowed();
      }
      elseif ($this->state->get("registration_test_host_access_manage_result") === 'forbidden') {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::neutral();
  }

}
