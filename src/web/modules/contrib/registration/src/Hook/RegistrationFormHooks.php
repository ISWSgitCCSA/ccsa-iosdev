<?php

namespace Drupal\registration\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Form hook implementations for registration.
 */
class RegistrationFormHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected RouteMatchInterface $currentRouteMatch,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $view = $form_state->get('view');
    if ($view->id() == 'manage_registrations') {
      // Hide the manage registrations filter when there are only a few rows.
      if ($host_entity = \Drupal::service('registration.manager')->getEntityFromParameters($this->currentRouteMatch->getParameters(), TRUE)) {
        $config = $this->configFactory->get('registration.settings');
        if ($host_entity->getRegistrationCount() < $config->get('hide_filter')) {
          $form['#access'] = FALSE;
        }
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for workflow_delete_form.
   */
  #[Hook('form_workflow_delete_form_alter')]
  public function formWorkflowDeleteFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // If there are no actions, the delete is not allowed.
    // Alter the error message to mention registration types.
    if (empty($form['actions'])) {
      $form['description'] = ['#markup' => $this->t('This workflow is in use. You cannot remove this workflow until you have removed all content using it and there are no registration types using it.')];
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for workflow_state_delete_form.
   */
  #[Hook('form_workflow_state_delete_form_alter')]
  public function formWorkflowStateDeleteFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // If there are no actions, the delete is not allowed.
    // Alter the error message to mention registration types.
    if (empty($form['actions'])) {
      $form['description'] = ['#markup' => $this->t('This workflow state is in use. You cannot remove this workflow state until you have removed all content using it and there are no registration types using it.')];
    }
  }

}
