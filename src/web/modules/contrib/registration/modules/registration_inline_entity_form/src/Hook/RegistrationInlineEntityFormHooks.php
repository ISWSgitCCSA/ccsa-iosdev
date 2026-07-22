<?php

namespace Drupal\registration_inline_entity_form\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration_inline_entity_form\RegistrationElementSubmit;
use Drupal\registration_inline_entity_form\RegistrationWidgetSubmit;

/**
 * Hook implementations for registration inline entity form.
 */
class RegistrationInlineEntityFormHooks {

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    $access_result = AccessResult::neutral();

    if ($entity instanceof RegistrationSettings) {
      if ($host_entity = $entity->getHostEntity()) {
        if ($type = $host_entity->getRegistrationTypeBundle()) {
          $access_result = AccessResult::allowedIfHasPermissions($account, [
            "edit registration settings",
            "edit $type registration settings",
          ], 'OR')
            ->addCacheableDependency($host_entity)
            ->addCacheableDependency($entity);
        }
      }
      else {
        $access_result = AccessResult::allowedIfHasPermission($account, "edit registration settings")
          ->addCacheableDependency($entity);
      }
    }

    return $access_result;
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    // Set the inline form for registration settings.
    $entity_types['registration_settings']->setHandlerClass('inline_form', 'Drupal\registration_inline_entity_form\Form\RegistrationSettingsInlineForm');
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // Attach registration related IEF handlers only if the current form has an
    // IEF widget placed by this module.
    $widget_state = $form_state->get('inline_entity_form');
    if (!is_null($widget_state)) {
      if ($provider = $form_state->get('provider')) {
        if ($provider == 'registration_inline_entity_form') {
          RegistrationElementSubmit::attach($form);
          RegistrationWidgetSubmit::attach($form, $form_state);
        }
      }
    }
  }

}
