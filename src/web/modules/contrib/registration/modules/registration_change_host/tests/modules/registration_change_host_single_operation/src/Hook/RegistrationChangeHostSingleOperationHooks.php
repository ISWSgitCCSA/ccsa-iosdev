<?php

namespace Drupal\registration_change_host_single_operation\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for registration change host single operation testing.
 */
class RegistrationChangeHostSingleOperationHooks {

  /**
   * Implements hook_entity_operation_alter().
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity) {
    // Remove the change host operation.
    if (isset($operations['change_host'])) {
      unset($operations['change_host']);
    }
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(&$local_tasks) {
    // Remove the change host tab.
    if (isset($local_tasks['entity.registration.change_host'])) {
      unset($local_tasks['entity.registration.change_host']);
    }
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name, RefinableCacheableDependencyInterface $cacheability) {
    // Modify the edit tab to cover both steps of the change host
    // & edit procedure.
    if (isset($data['tabs'][0]['entity.registration.edit_form'])) {
      $data['tabs'][0]['entity.registration.edit_form']['#active'] = in_array($route_name, [
        'entity.registration.edit_form',
        'entity.registration.edit_fields_form',
      ]);
    }
  }

}
