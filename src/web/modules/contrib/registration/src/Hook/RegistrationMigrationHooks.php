<?php

namespace Drupal\registration\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Migration hook implementations for registration.
 */
class RegistrationMigrationHooks {

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public function migrationPluginsAlter(array &$migrations): void {
    if (isset($migrations['d7_field_instance'])) {
      // Migrate registration types before field instances since the field
      // instances have references to the registration types as field bundles.
      $migrations['d7_field_instance']['migration_dependencies']['optional'][] = 'd7_registration_type';
    }
  }

}
