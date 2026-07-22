<?php

namespace Drupal\registration_workflow\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Hook implementations for registration workflow.
 */
class RegistrationWorkflowHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];

    // Add a cancel operation for registrations. The route has a custom access
    // checker that will ensure the operation is only displayed if the user has
    // permission to cancel registrations and the registration is not already
    // canceled.
    if ($entity instanceof RegistrationInterface) {
      $url = Url::fromRoute('registration_workflow.transition', [
        'registration' => $entity->id(),
        'transition' => 'cancel',
      ]);
      if ($url->access()) {
        $operations['cancel'] = [
          'title' => $this->t('Cancel'),
          'url' => $url->mergeOptions([
            'query' => \Drupal::destination()->getAsArray(),
          ]),
          'weight' => 10,
        ];
      }
    }

    return $operations;
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    // Flush the render cache when workflows are updated, as the list of
    // available registration entity operations could change.
    if ($entity instanceof WorkflowInterface) {
      $bins = Cache::getBins();
      $bins['render']->deleteAll();
    }
  }

  /**
   * Prepares variables for a registration.
   *
   * Default template: registration.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  #[Hook('preprocess_registration')]
  public function preprocessRegistration(array &$variables): void {
    // Add operations buttons when a registration is displayed on its own page.
    if ($variables['elements']['#view_mode'] == 'full') {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      $registration = $variables['elements']['#registration'];
      $transitions = \Drupal::service('registration_workflow.validation')->getValidTransitions($registration);

      // Render a button for each transition. Exclude cancel since it is already
      // rendered as an entity operation in registration listings.
      unset($transitions['cancel']);
      foreach ($transitions as $transition) {
        $url = Url::fromRoute('registration_workflow.transition', [
          'registration' => $registration->id(),
          'transition' => $transition->id(),
        ]);
        // Ensure the user has access to perform the transition.
        if ($url->access()) {
          $url->mergeOptions([
            'query' => \Drupal::destination()->getAsArray(),
          ]);
          // Append to the bottom of the registration template.
          if (empty($variables['registration']['transition'])) {
            $variables['registration']['transition'] = [
              '#prefix' => '<p>',
              '#type' => 'container',
              '#weight' => 50,
              // Rebuild if user permissions change.
              '#cache' => [
                'contexts' => [
                  'user.permissions',
                ],
              ],
            ];
          }
          $variables['registration']['transition'][$transition->id()] = [
            '#type' => 'link',
            '#url' => $url,
            '#title' => t('@transition registration', [
              '@transition' => $transition->label(),
            ]),
            '#attributes' => [
              'class' => [
                'button',
                'button--registration-transition',
                'button--registration-transition--' . $transition->id(),
              ],
            ],
          ];
        }
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_registration_admin_settings_alter')]
  public function formRegistrationAdminSettingsAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $config = $this->configFactory->get('registration_workflow.settings');
    $form['registration_workflow'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Registration workflow'),
    ];
    $form['registration_workflow']['require_update_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require update access to registrations'),
      '#default_value' => $config->get('require_update_access') ?? FALSE,
      '#description' => $this->t('Requires that users have update access to the registration, plus the relevant workflow transition permission, to access a transition for a given registration. If this option is not set, only the workflow transition permission is needed.'),
    ];
    $form['registration_workflow']['prevent_complete_own'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prevent users from completing their own registrations'),
      '#default_value' => $config->get('prevent_complete_own') ?? FALSE,
      '#description' => $this->t('Prevents workflow completion if the current user is also the registrant. This setting is ignored for administrators.'),
    ];
    $form['#submit'][] = 'registration_workflow_form_registration_admin_settings_submit';
  }

}
