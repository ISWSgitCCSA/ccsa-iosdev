<?php

namespace Drupal\registration_test_validator\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for registration validator testing.
 */
class RegistrationTestValidatorHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    $entity_types['node']->addConstraint('Constraint4');
  }

}
