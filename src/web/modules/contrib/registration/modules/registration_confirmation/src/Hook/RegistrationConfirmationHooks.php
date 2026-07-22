<?php

namespace Drupal\registration_confirmation\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for registration confirmation.
 */
class RegistrationConfirmationHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for registration_type_form.
   */
  #[Hook('form_registration_type_form_alter')]
  public function formRegistrationTypeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $registration_type = $form_state->getFormObject()->getEntity();

    $form['registration_confirmation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Confirmation email settings'),
    ];
    $form['registration_confirmation']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable confirmation email'),
      '#default_value' => $registration_type->getThirdPartySetting('registration_confirmation', 'enable'),
    ];
    $form['registration_confirmation']['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $registration_type->getThirdPartySetting('registration_confirmation', 'subject'),
      '#states' => [
        'required' => [
          ':input[name="registration_confirmation[enable]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="registration_confirmation[enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $message = $registration_type->getThirdPartySetting('registration_confirmation', 'message');
    $form['registration_confirmation']['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#description' => $this->t('Enter the message you want to send. Tokens are supported, e.g., [node:title].'),
      '#default_value' => $message['value'] ?? '',
      '#format' => $message['format'] ?? filter_default_format(),
      '#states' => [
        'visible' => [
          ':input[name="registration_confirmation[enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      $form['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [
          'registration',
          'registration_settings',
        ],
        '#global_types' => FALSE,
        '#weight' => 10,
      ];
      foreach (\Drupal::service('registration.manager')->getRegistrationEnabledEntityTypes() as $entity_type) {
        $form['token_tree']['#token_types'][] = $entity_type->id();
      }
    }
    $form['actions']['submit']['#submit'][] = 'registration_confirmation_form_registration_type_submit';
  }

}
