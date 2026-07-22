<?php

namespace Drupal\registration_purger\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for registration purger.
 */
class RegistrationPurgerHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity) {
    \Drupal::service('registration_purger.purger')->onEntityDelete($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity) {
    \Drupal::service('registration_purger.purger')->onEntityUpdate($entity);
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_registration_admin_settings_alter')]
  public function formRegistrationAdminSettingsAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $config = $this->configFactory->get('registration_purger.settings');
    $form['registration_purger'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Registration purger'),
    ];
    $form['registration_purger']['delete'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('On host entity delete'),
    ];
    $form['registration_purger']['delete']['purge_registration_settings_on_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Purge registration settings'),
      '#default_value' => $config->get('purge_registration_settings_on_delete') ?? TRUE,
    ];
    $form['registration_purger']['delete']['purge_registration_on_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Purge registrations'),
      '#default_value' => $config->get('purge_registration_on_delete') ?? TRUE,
    ];
    $form['registration_purger']['update'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('On host entity update after registration is disabled'),
    ];
    $form['registration_purger']['update']['purge_registration_settings_on_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Purge registration settings'),
      '#default_value' => $config->get('purge_registration_settings_on_update') ?? FALSE,
    ];
    $form['registration_purger']['update']['purge_registration_on_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Purge registrations'),
      '#default_value' => $config->get('purge_registration_on_update') ?? FALSE,
    ];
    $form['#submit'][] = 'registration_purger_form_registration_admin_settings_submit';
  }

}
