<?php

namespace Drupal\registration_admin_overrides\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface;

/**
 * Hook implementations for registration admin overrides.
 */
class RegistrationAdminOverridesHooks {

  use StringTranslationTrait;

  public function __construct(
    protected RegistrationOverrideCheckerInterface $registrationOverrideChecker,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for registration_type_form.
   */
  #[Hook('form_registration_type_form_alter')]
  public function formRegistrationTypeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $registration_type = $form_state->getFormObject()->getEntity();

    $form['registration_admin_overrides'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Administrative override settings'),
      '#description' => $this->t('These settings apply to accounts that can administer registrations of this type and have the relevant override permissions.'),
    ];

    $overridable_settings = $this->registrationOverrideChecker->getOverridableSettings($registration_type);
    foreach ($overridable_settings as $setting => $label) {
      $form['registration_admin_overrides'][$setting] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => $registration_type->getThirdPartySetting('registration_admin_overrides', $setting),
      ];
    }

    $form['actions']['submit']['#submit'][] = 'registration_admin_overrides_form_registration_type_submit';
  }

}
