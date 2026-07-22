<?php

namespace Drupal\registration_waitlist\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\RegistrationHostEntityHandler;
use Drupal\registration_waitlist\Event\RegistrationWaitListEvents;
use Drupal\registration_waitlist\Plugin\Field\RegistrationItemFieldItemList;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Hook implementations for registration waitlist.
 */
class RegistrationWaitlistHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EventDispatcherInterface $eventDispatcher,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    // Override the host entity handler.
    // @todo remove host_entity override before registration:4.0.0
    $entity_types['registration']->setHandlerClass('host_entity', 'Drupal\registration_waitlist\HostEntityHandler');

    // Add a wait list constraint to registration settings.
    $entity_types['registration_settings']->addConstraint('MinimumWaitListCapacity');
  }

  /**
   * Implements hook_entity_type_build().
   *
   * Override the host entity handler. Uses the build hook not the alter hook
   * in order to make it easier for other modules to customize using the alter
   * hook.
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $handler_class = $entity_type->getHandlerClass('registration_host_entity');
      if (!$handler_class || $handler_class === RegistrationHostEntityHandler::class) {
        $entity_type->setHandlerClass('registration_host_entity', 'Drupal\registration_waitlist\HostEntityHandler');
      }
    }
  }

  /**
   * Implements hook_field_widget_info_alter().
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    $info['registration_spaces_default']['class'] = 'Drupal\registration_waitlist\Plugin\Field\FieldWidget\RegistrationSpacesWidget';
    $info['registration_state_default']['class'] = 'Drupal\registration_waitlist\Plugin\Field\FieldWidget\RegistrationStateWidget';
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('registration_presave')]
  public function registrationPresave(EntityInterface $entity): void {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $entity;
    if (!$registration->getState()->isCanceled()) {
      /** @var \Drupal\registration_waitlist\HostEntityInterface $host_entity */
      if ($host_entity = $registration->getHostEntity()) {
        $spaces = $registration->getSpacesReserved();
        if ($host_entity->shouldAddToWaitList($spaces, $registration)) {
          // Allow other modules to change the new state.
          $new_state = 'waitlist';
          $event = new RegistrationDataAlterEvent($new_state, [
            'host_entity' => $host_entity,
            'settings' => $host_entity->getSettings(),
            'registration' => $registration,
          ]);
          $this->eventDispatcher->dispatch($event, RegistrationWaitListEvents::REGISTRATION_WAITLIST_PRESAVE);
          $new_state = (string) $event->getData();
          $registration->set('state', $new_state);
        }
      }
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook('form_registration_type_form_alter')]
  public function formRegistrationTypeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $registration_type = $form_state->getFormObject()->getEntity();

    $form['registration_waitlist'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Wait list settings'),
    ];
    $form['registration_waitlist']['confirmation_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable wait list confirmation email'),
      '#default_value' => $registration_type->getThirdPartySetting('registration_waitlist', 'confirmation_email'),
    ];
    $form['registration_waitlist']['confirmation_email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $registration_type->getThirdPartySetting('registration_waitlist', 'confirmation_email_subject'),
      '#states' => [
        'required' => [
          ':input[name="registration_waitlist[confirmation_email]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="registration_waitlist[confirmation_email]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $message = $registration_type->getThirdPartySetting('registration_waitlist', 'confirmation_email_message');
    $form['registration_waitlist']['confirmation_email_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#description' => $this->t('Enter the message you want to send. Tokens are supported, e.g., [node:title].'),
      '#default_value' => $message['value'] ?? '',
      '#format' => $message['format'] ?? filter_default_format(),
      '#states' => [
        'visible' => [
          ':input[name="registration_waitlist[confirmation_email]"]' => ['checked' => TRUE],
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
    $form['registration_waitlist']['registration_waitlist_autofill'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Autofill priority'),
      '#description' => $this->t('Settings that apply when autofill is enabled in host entity registration settings.<br />The default is Registration ID ascending, which gives autofill priority to the oldest registrations first.'),
    ];
    $form['registration_waitlist']['registration_waitlist_autofill']['autofill_sort_field'] = [
      '#type' => 'select',
      '#title' => t('Sort field'),
      '#options' => \Drupal::service('registration_waitlist.manager')->getAutoFillSortFieldOptions($registration_type),
      '#default_value' => $registration_type->getThirdPartySetting('registration_waitlist', 'autofill_sort_field') ?? 'registration_id',
      '#description' => $this->t('The field that determines the priority by which wait listed registrations autofill into standard capacity when new slots become available.'),
    ];
    $form['registration_waitlist']['registration_waitlist_autofill']['autofill_sort_order'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort order'),
      '#options' => [
        'ASC' => $this->t('Ascending'),
        'DESC' => $this->t('Descending'),
      ],
      '#default_value' => $registration_type->getThirdPartySetting('registration_waitlist', 'autofill_sort_order'),
      '#description' => $this->t('The sort order applied to the autofill sort field.'),
    ];

    $form['actions']['submit']['#submit'][] = 'registration_waitlist_form_registration_type_submit';
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    if ($entity_type->id() === 'registration_settings') {
      // Add wait list fields to the registration settings entity.
      $fields['registration_waitlist_enable'] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Enable wait list'))
        ->setDescription($this->t('When wait list is enabled, registrations made after capacity has been reached will be set to the wait list state.'))
        ->setDefaultValue(FALSE)
        ->setRequired(FALSE)
        ->setDisplayOptions('form', [
          'type' => 'boolean_checkbox',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

      $fields['registration_waitlist_autofill'] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Autofill available spots'))
        ->setDescription($this->t('Automatically fill newly available spots in standard capacity from the wait list.'))
        ->setRequired(FALSE)
        ->setDefaultValue(FALSE)
        ->setDisplayOptions('form', [
          'type' => 'boolean_checkbox',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

      $fields['registration_waitlist_autofill_state'] = BaseFieldDefinition::create('string')
        ->setLabel($this->t('Autofill state'))
        ->setDescription($this->t('The state that wait listed registrations should be placed in when slots become available.'))
        ->setRequired(FALSE)
        ->setSetting('max_length', 255)
        ->setDisplayOptions('form', [
          'type' => 'registration_state_default',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

      $fields['registration_waitlist_capacity'] = BaseFieldDefinition::create('integer')
        ->setLabel($this->t('Wait list capacity'))
        ->setDescription($this->t('The maximum number of registrants on the wait list. Leave at 0 for no limit.'))
        ->setRequired(TRUE)
        ->setDefaultValue(0)
        ->setSetting('min', 0)
        ->setSetting('max', 99999)
        ->setDisplayOptions('form', [
          'type' => 'number',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

      $fields['registration_waitlist_message_enable'] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Enable wait list message'))
        ->setDescription($this->t('Enable to allow display of a message above the registration form when capacity has been reached.'))
        ->setRequired(FALSE)
        ->setDisplayOptions('form', [
          'type' => 'boolean_checkbox',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

      $fields['registration_waitlist_message'] = BaseFieldDefinition::create('text_long')
        ->setLabel($this->t('Wait list message'))
        ->setDescription($this->t('Enter a message to display above the registration form to notify users that they will be placed on a waitlist.  Message only appears if capacity is reached.'))
        ->setRequired(FALSE)
        ->setTranslatable(TRUE)
        ->setDisplayOptions('form', [
          'type' => 'text_textarea',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);

    }
    return $fields;
  }

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(array &$info): void {
    // Override the registration field item list.
    $info['registration']['list_class'] = RegistrationItemFieldItemList::class;
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_registration_settings_form_alter')]
  public function formRegistrationSettingsFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form['registration_waitlist_autofill']['#states'] = [
      'visible' => [
        ':input[name="registration_waitlist_enable[value]"]' => ['checked' => TRUE],
      ],
    ];
    $form['registration_waitlist_autofill_state']['#states'] = [
      'visible' => [
        ':input[name="registration_waitlist_enable[value]"]' => ['checked' => TRUE],
        ':input[name="registration_waitlist_autofill[value]"]' => ['checked' => TRUE],
      ],
    ];
    $form['registration_waitlist_capacity']['#states'] = [
      'visible' => [
        ':input[name="registration_waitlist_enable[value]"]' => ['checked' => TRUE],
      ],
    ];
    $form['registration_waitlist_message_enable']['#states'] = [
      'visible' => [
        ':input[name="registration_waitlist_enable[value]"]' => ['checked' => TRUE],
      ],
    ];
    $form['registration_waitlist_message']['#states'] = [
      'visible' => [
        ':input[name="registration_waitlist_enable[value]"]' => ['checked' => TRUE],
        ':input[name="registration_waitlist_message_enable[value]"]' => ['checked' => TRUE],
      ],
    ];
  }

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public function migrationPluginsAlter(array &$migrations): void {
    if (isset($migrations['d7_registration_settings'])) {
      // Add fields to the registration settings migration.
      $migrations['d7_registration_settings']['process']['registration_waitlist_enable'][] = [
        'plugin' => 'registration_additional_settings',
        'source' => 'settings',
        'key' => 'registration_waitlist_enable',
      ];
      $migrations['d7_registration_settings']['process']['registration_waitlist_capacity'][] = [
        'plugin' => 'registration_additional_settings',
        'source' => 'settings',
        'key' => 'registration_waitlist_capacity',
      ];
      $migrations['d7_registration_settings']['process']['registration_waitlist_message_enable'][] = [
        'plugin' => 'registration_additional_settings',
        'source' => 'settings',
        'key' => 'registration_waitlist_message_enable',
      ];
      $migrations['d7_registration_settings']['process']['registration_waitlist_message'][] = [
        'plugin' => 'registration_additional_settings',
        'source' => 'settings',
        'key' => 'registration_waitlist_message',
      ];
      // Pipeline into the reminder template plugin, which knows how to handle
      // migrating into a long text field with value and format.
      // @todo Rename plugin to registration_longtext.
      $migrations['d7_registration_settings']['process']['registration_waitlist_message'][] = [
        'plugin' => 'registration_reminder_template',
      ];
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'host_entity_waitlist_indicator' => [
        'variables' => [],
      ],
    ];
  }

}
