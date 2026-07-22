<?php

namespace Drupal\registration_scheduled_action\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for registration scheduled action.
 */
class RegistrationScheduledActionHooks {

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    \Drupal::service('registration_scheduled_action.cron.schedule')->run();
  }

}
