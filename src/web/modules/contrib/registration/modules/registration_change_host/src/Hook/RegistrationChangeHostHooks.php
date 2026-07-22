<?php

namespace Drupal\registration_change_host\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Hook implementations for registration change host.
 */
class RegistrationChangeHostHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    if (isset($entity_types['registration'])) {
      $entity_types['registration']->setFormClass('change_host', 'Drupal\registration_change_host\Form\ChangeHostForm');
      $entity_types['registration']->setFormClass('single_step_change_host', 'Drupal\registration_change_host\Form\SingleStepChangeHostForm');
      $entity_types['registration']->setLinkTemplate('change-host', '/registration/{registration}/host');
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('registration_access')]
  public function registrationAccess(RegistrationInterface $entity, string $op, AccountInterface $account): AccessResultInterface {
    // Set the default access result.
    $access = AccessResult::neutral();

    // Change host access requires:
    // - permission to change host, checked later in
    //   RegistrationAccessControlHandler::checkEntityUserPermissions()
    //   (which also checks the 'change host registrations' operation on
    //   the current host entity.)
    // - update access, checked here for the multistep workflow only.
    //   The single step workflow does not allow field update, and thus
    //   does not require update access.
    // This hook returns neutral if there are no reasons to deny access,
    // rather than allowed which would override the access control handler.
    if ($op == 'change host') {
      $config = $this->configFactory->get('registration_change_host.settings');

      // Skip additional checking for the single step workflow.
      if ($config->get('workflow') != 'single_step') {
        // Forbid change host access if there is no update access.
        $update_access = $entity->access('update', $account, TRUE);
        if (!$update_access->isAllowed()) {
          $access = AccessResult::forbidden($update_access->getReason())->addCacheableDependency($update_access);
        }
      }

      // Add the settings configuration entity to access result cacheability.
      $access->addCacheableDependency($config);
    }
    return $access;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'registration_change_host_list' => [
        'variables' => [
          'hosts' => [],
          'set' => NULL,
        ],
        'initial preprocess' => static::class . ':preprocessRegistrationChangeHostList',
      ],
    ];
  }

  /**
   * Prepares variables for the list of available hosts.
   *
   * Default template: registration-change-host-list.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - hosts: An array of hosts with the label, description, and other keys.
   */
  public function preprocessRegistrationChangeHostList(array &$variables): void {
    $items = [];
    /** @var \Drupal\registration_change_host\PossibleHostSetInterface $set */
    $set = $variables['set'];
    foreach ($set->getHosts() as $host_id => $possible_host) {
      $items[$host_id]['possible_host'] = $possible_host;
      // Convert possible host objects to renderable strings.
      $items[$host_id]['description'] = ['#markup' => $possible_host->getDescription()];
      $items[$host_id]['label'] = ['#markup' => $possible_host->label()];
      $items[$host_id]['is_available'] = $possible_host->isAvailable();
      $items[$host_id]['is_current'] = $possible_host->isCurrent();

      if ($violations = $possible_host->isAvailable(TRUE)->getViolations()) {
        $causes = [];
        foreach ($violations as $violation) {
          // Ignore null or zero-length causes.
          if ($cause = $violation->getCause()) {
            if (!empty((string) $cause)) {
              $causes[] = $cause;
            }
          }
        }
        $items[$host_id]['causes'] = $causes;
      }

      if ($url = $possible_host->getUrl()) {
        $items[$host_id]['url'] = $url;
        $items[$host_id]['link'] = Link::fromTextAndUrl($possible_host->label(), $url);
      }
    }
    $variables['hosts'] = $items;
    $variables['#attached']['library'][] = 'registration_change_host/possible_hosts_list';
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_registration_type_form_alter')]
  public function formRegistrationTypeFormAlter(&$form, FormStateInterface $form_state, string $form_id): void {
    $registration_type = $form_state->getFormObject()->getEntity();

    $form['registration_change_host'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Changing host'),
    ];
    $form['registration_change_host']['allow_data_loss'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow data loss when changing to a host with a different registration type'),
      '#default_value' => $registration_type->getThirdPartySetting('registration_change_host', 'allow_data_loss'),
      '#description' => $this->t('Data loss occurs when there are non-empty fields on a registration using this registration type that are not present on the registration type used by the new host.'),
    ];
    $form['actions']['submit']['#submit'][] = 'registration_change_host_form_registration_type_submit';
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_registration_admin_settings_alter')]
  public function formRegistrationAdminSettingsAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $config = $this->configFactory->get('registration_change_host.settings');
    $form['registration_change_host'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Change host form'),
    ];
    $form['registration_change_host']['workflow'] = [
      '#type' => 'radios',
      '#title' => $this->t('Workflow'),
      '#options' => [
        'single_step' => $this->t('Single step'),
        'multistep'  => $this->t('Multistep'),
      ],
      '#default_value' => $config->get('workflow') ?? 'multistep',
      '#description' => t('Choose the workflow used for the change host form. The single step workflow uses a simple form and is recommended for sites with a single registration type, or sites with multiple registration types that all have the same fields. The multistep workflow has a selection interface that leads to a confirmation form where the fields can be reviewed and edited if necessary, and is required for sites having registration types with varying fields.'),
      '#required' => TRUE,
    ];
    $form['registration_change_host']['task_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local task title'),
      '#default_value' => $config->get('task_title'),
      '#description' => $this->t('Sets the title of the change host tab when viewing a registration. The default results in a title of "Change Event" if the host entity is an Event node.'),
      '#required' => TRUE,
    ];
    $form['registration_change_host']['form_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form title'),
      '#default_value' => $config->get('form_title'),
      '#description' => $this->t('Sets the title of the change host form in the single step workflow. The default results in a title of "Change Event for Registration #1" if the host entity is an Event node and the registration ID is 1.'),
      '#required' => TRUE,
    ];
    $form['registration_change_host']['page_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page title'),
      '#default_value' => $config->get('page_title'),
      '#description' => $this->t('Sets the title of the change host page in the multistep workflow. The default results in a title of "Select Event" if the host entity is an Event node.'),
      '#required' => TRUE,
    ];
    $form['registration_change_host']['confirm_form_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation form title'),
      '#default_value' => $config->get('confirm_form_title'),
      '#description' => $this->t('Sets the title of the change host confirmation form in the multistep workflow. The default results in a title of "Confirm change of Event for Registration #1" if the host entity is an Event node and the registration ID is 1.'),
      '#required' => TRUE,
    ];
    $form['#submit'][] = 'registration_change_host_form_registration_admin_settings_submit';
  }

}
