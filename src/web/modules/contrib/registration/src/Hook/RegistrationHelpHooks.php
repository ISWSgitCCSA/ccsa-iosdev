<?php

namespace Drupal\registration\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Help hook implementations for registration.
 */
class RegistrationHelpHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    $output = '';
    switch ($route_name) {
      case 'help.page.registration':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Registration module provides user registration for events such as conferences and webinars. Site builders enable registration by adding a Registration field to the appropriate content types and configuring default settings. Each event can have Open and Close dates that control when registration is open. Register links and forms appear for events with open registration, and site visitors can use those to register. Each event is provided with a Manage Registrations listing that administrators can use to review and maintain the registrations. Administrators can configure reminders to be sent to registrants on a per-event basis.') . '</p>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Configuration') . '</dt>';
        $output .= '<dd>' . $this->t('All relevant entities such as Registration Types and Registration Settings are fieldable. This allows administrators to configure the fields that appear on the Register and Settings forms. If the Views module is enabled then views listings are automatically created when the Registration module is installed. Site builders can customize these views to control what appears on the Manage Registrations and other administrative listings. The module leverages the core Workflows module to define the allowed registration states and transitions.') . '</dd>';
        $output .= '<dt>' . $this->t('Entity types') . '</dt>';
        $output .= '<dd>' . $this->t('Registration fields can be attached to any fieldable entity type, including any custom entity types that you define for your events. The most common use case would likely be creating an Event content type and enabling that for registration. If you have the Drupal Commerce module installed, you can add registration fields to Commerce products or product variations. However any fieldable entity can be used as the host.') . '</dd>';
        $output .= '</dl>';
        $output .= '<p>' . $this->t('View the <a href="https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/entity-registration" target="_blank">online help</a> for more information.') . '</p>';
    }
    return $output;
  }

}
