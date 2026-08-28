<?php

namespace Drupal\media_folders\Plugin\Field\FieldWidget;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\field_ui\FieldUI;
use Drupal\media\Entity\Media;
use Drupal\media_folders\MediaFoldersState;
use Drupal\media_folders\MediaFoldersUiBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'media_folders_widget' widget.
 *
 * @internal
 *   Plugin classes are internal.
 */
#[FieldWidget(
  id: 'media_folders_widget',
  label: new TranslatableMarkup('Media Folders'),
  description: new TranslatableMarkup('Allows you to select items from the file explorer.'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
/**
 * Media Folders Widget.
 */
class MediaFoldersWidget extends WidgetBase implements TrustedCallbackInterface {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    ModuleHandlerInterface $module_handler,
    EntityDisplayRepository $entity_display_repository,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getSetting('target_type') === 'media';
  }

  /**
   * {@inheritdoc}
   */
  public static function viewTypes() {
    return [
      'thumbs' => t('Folder view'),
      'list' => t('List view'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function viewOrders() {
    return [
      'az' => t('Alphabetic ascending'),
      'za' => t('Alphabetic descending'),
      'date-asc' => t('Date ascending'),
      'date-desc' => t('Date descending'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'view_type' => 'list',
      'view_order' => 'date-desc',
      'show_navigation' => TRUE,
      'show_search' => TRUE,
      'form_mode' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['view_type'] = [
      '#type' => 'select',
      '#options' => static::viewTypes(),
      '#required' => TRUE,
      '#title' => $this->t('View Mode'),
      '#default_value' => $this->getSetting('view_type'),
    ];

    $elements['view_order'] = [
      '#type' => 'select',
      '#options' => static::viewOrders(),
      '#required' => TRUE,
      '#title' => $this->t('Order'),
      '#default_value' => $this->getSetting('view_order'),
    ];

    $elements['show_navigation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show navigation'),
      '#default_value' => $this->getSetting('show_navigation'),
    ];

    $elements['show_search'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show search form'),
      '#default_value' => $this->getSetting('show_search'),
    ];

    $elements['form_mode'] = [
      '#type' => 'details',
      '#title' => $this->t('Media edit form modes'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $settings['target_bundles'] = $this->getFieldSetting('handler_settings');
    $default_form_modes = $this->getSetting('form_mode');
    foreach ($settings['target_bundles']['target_bundles'] as $bundle) {
      $form_modes = [];
      $custom_form_modes = $this->entityDisplayRepository->getFormModeOptionsByBundle('media', $bundle);
      if (!empty($custom_form_modes)) {
        foreach ($custom_form_modes as $key => $value) {
          $form_modes[$key] = $value;
        }
      }
      $elements['form_mode'][$bundle] = [
        '#type' => 'select',
        '#options' => $form_modes,
        '#required' => TRUE,
        '#title' => $bundle,
        '#default_value' => (!empty($default_form_modes[$bundle])) ? $default_form_modes[$bundle] : 'default',
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $view_type = $this->getSetting('view_type');
    $view_types = static::viewTypes();
    $summary[] = $this->t('View mode: @mode', ['@mode' => $view_types[$view_type]]);

    $view_order = $this->getSetting('view_order');
    $view_orders = static::viewOrders();
    $summary[] = $this->t('View order: @order', ['@order' => $view_orders[$view_order]]);

    if ($this->getSetting('show_navigation')) {
      $summary[] = $this->t('Show navigation');
    }

    if ($this->getSetting('show_search')) {
      $summary[] = $this->t('Show search form');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $field_state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
    if (isset($field_state['items'])) {
      usort($field_state['items'], [SortArray::class, 'sortByWeightElement']);
      $items->setValue($field_state['items']);
    }

    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    $field_name = $this->fieldDefinition->getName();
    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    $field_state['items'] = $items->getValue();
    static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    $referenced_entities = $items->referencedEntities();
    $view_builder = $this->entityTypeManager->getViewBuilder('media');
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];

    $id_suffix = $parents ? '-' . implode('-', $parents) : '';
    $field_widget_id = implode(':', array_filter([$field_name, $id_suffix]));
    $wrapper_id = $field_name . '-media-folders' . $id_suffix;
    $limit_validation_errors = [array_merge($parents, [$field_name])];

    $settings = $this->getFieldSetting('handler_settings');
    $element += [
      '#type' => 'fieldset',
      '#cardinality' => $this->fieldDefinition->getFieldStorageDefinition()->getCardinality(),
      '#target_bundles' => $settings['target_bundles'] ?? [],
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => ['js-media-library-widget'],
      ],
      '#pre_render' => [
        [$this, 'preRenderWidget'],
      ],
      '#attached' => [
        'library' => [
          'media_library/widget',
          'media_folders/widget',
        ],
      ],
      '#theme_wrappers' => [
        'fieldset__media_library_widget',
      ],
    ];

    if ($this->fieldDefinition->isRequired()) {
      $element['#element_validate'][] = [static::class, 'validateRequired'];
    }

    $multiple_items = FALSE;
    if (empty($referenced_entities)) {
      $element['#field_prefix']['empty_selection'] = [
        '#markup' => $this->t('No media items are selected.'),
      ];
    }
    else {
      $multiple_items = count($referenced_entities) > 1;
      $element['#field_prefix']['weight_toggle'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Show media item weights'),
        '#access' => $multiple_items,
        '#attributes' => [
          'class' => [
            'link',
            'js-media-library-widget-toggle-weight',
          ],
        ],
      ];
    }

    $element['selection'] = [
      '#type' => 'container',
      '#theme_wrappers' => [
        'container__media_library_widget_selection',
      ],
      '#attributes' => [
        'class' => [
          'js-media-library-selection',
        ],
      ],
    ];

    foreach ($referenced_entities as $delta => $media_item) {
      if ($media_item->access('view')) {
        $preview = $view_builder->view($media_item, 'media_library');
      }
      else {
        $item_label = $media_item->access('view label') ? $media_item->label() : new FormattableMarkup('@label @id', [
          '@label' => $media_item->getEntityType()->getSingularLabel(),
          '@id' => $media_item->id(),
        ]);
        $preview = [
          '#theme' => 'media_embed_error',
          '#message' => $this->t('You do not have permission to view @item_label.', ['@item_label' => $item_label]),
        ];
      }
      $element['selection'][$delta] = [
        '#theme' => 'media_library_item__widget',
        '#attributes' => [
          'class' => [
            'js-media-library-item',
          ],
          'tabindex' => '-1',
          'data-media-library-item-delta' => $delta,
        ],
        'remove_button' => [
          '#type' => 'submit',
          '#name' => $field_name . '-' . $delta . '-media-folders-remove-button' . $id_suffix,
          '#value' => $this->t('Remove'),
          '#media_id' => $media_item->id(),
          '#attributes' => [
            'class' => ['media-folders-item__remove'],
            'aria-label' => $media_item->access('view label') ? $this->t('Remove @label', ['@label' => $media_item->label()]) : $this->t('Remove media'),
          ],
          '#ajax' => [
            'callback' => [static::class, 'updateWidget'],
            'wrapper' => $wrapper_id,
            'progress' => [
              'type' => 'throbber',
              'message' => $media_item->access('view label') ? $this->t('Removing @label.', ['@label' => $media_item->label()]) : $this->t('Removing media.'),
            ],
          ],
          '#submit' => [[static::class, 'removeItem']],
          '#limit_validation_errors' => $limit_validation_errors,
        ],
        'rendered_entity' => $preview,
        'target_id' => [
          '#type' => 'hidden',
          '#value' => $media_item->id(),
        ],
        'weight' => [
          '#type' => 'number',
          '#theme' => 'input__number__media_library_item_weight',
          '#title' => $this->t('Weight'),
          '#access' => $multiple_items,
          '#default_value' => $delta,
          '#attributes' => [
            'class' => [
              'js-media-library-item-weight',
            ],
          ],
        ],
      ];
    }

    $cardinality_unlimited = ($element['#cardinality'] === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $remaining = $element['#cardinality'] - count($referenced_entities);

    if (!$cardinality_unlimited) {
      if ($remaining) {
        $cardinality_message = $this->formatPlural($remaining, 'One media item remaining.', '@count media items remaining.');
      }
      else {
        $cardinality_message = $this->t('The maximum number of media items have been selected.');
      }

      if (!empty($element['#description'])) {
        $element['#description'] .= '<br />';
      }
      $element['#description'] .= $cardinality_message;
    }

    $allowed_media_type_ids = ['file'];
    $selected_type_id = reset($allowed_media_type_ids);
    $remaining = $cardinality_unlimited ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : $remaining;

    $entity = $items->getEntity();
    $opener_parameters = [
      'field_widget_id' => $field_widget_id,
      'entity_type_id' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'field_name' => $field_name,
      'settings' => $this->getSetting('show'),
      'form_mode' => $this->getSetting('form_mode'),
    ];

    if (!$entity->isNew()) {
      $opener_parameters['entity_id'] = (string) $entity->id();

      if ($entity->getEntityType()->isRevisionable()) {
        $opener_parameters['revision_id'] = (string) $entity->getRevisionId();
      }
    }
    $state = MediaFoldersState::create(
      'media_folders.opener.field_widget',
      $allowed_media_type_ids,
      $selected_type_id,
      $remaining,
      $opener_parameters,
      $this->getSettings(),
    );

    $element['open_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Add media'),
      '#name' => $field_name . '-media-folders-open-button' . $id_suffix,
      '#attributes' => [
        'class' => [
          'js-media-folders-folders-open-button',
        ],
      ],
      '#media_folders_state' => $state,
      '#ajax' => [
        'callback' => [static::class, 'openMediaFolders'],
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Opening media folders.'),
        ],
      ],
      '#limit_validation_errors' => [],
    ];

    if (!$cardinality_unlimited && $remaining === 0) {
      $triggering_element = $form_state->getTriggeringElement();
      if ($triggering_element && ($trigger_parents = $triggering_element['#array_parents']) && end($trigger_parents) === 'media_folders_update_widget') {
        $element['open_button']['#attributes']['data-disabled-focus'] = 'true';
        $element['open_button']['#attributes']['class'][] = 'visually-hidden';
      }
      else {
        $element['open_button']['#disabled'] = TRUE;
        $element['open_button']['#attributes']['class'][] = 'visually-hidden';
      }
    }

    $element['media_folders_selection'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'data-media-folders-widget-value' => $field_widget_id,
      ],
    ];

    $element['media_folders_update_widget'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update widget'),
      '#name' => $field_name . '-media-folders-update' . $id_suffix,
      '#ajax' => [
        'callback' => [static::class, 'updateWidget'],
        'wrapper' => $wrapper_id,
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding selection.'),
        ],
      ],
      '#attributes' => [
        'data-media-folders-widget-update' => $field_widget_id,
        'class' => ['js-hide'],
      ],
      '#validate' => [[static::class, 'validateItems']],
      '#submit' => [[static::class, 'addItems']],
      '#limit_validation_errors' => !empty($referenced_entities) ? $limit_validation_errors : [],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderWidget'];
  }

  /**
   * {@inheritdoc}
   */
  public function preRenderWidget(array $element) {
    if (isset($element['open_button'])) {
      $element['#field_suffix']['open_button'] = $element['open_button'];
      unset($element['open_button']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNoMediaTypesAvailableMessage() {
    $entity_type_id = $this->fieldDefinition->getTargetEntityTypeId();

    $default_message = $this->t('There are no allowed media types configured for this field. Contact the site administrator.');

    if (!$this->currentUser->hasPermission("administer $entity_type_id fields")) {
      return $default_message;
    }

    if (!$this->moduleHandler->moduleExists('field_ui')) {
      return $this->t('There are no allowed media types configured for this field. Edit the field settings to select the allowed media types.');
    }

    $route_parameters = FieldUI::getRouteBundleParameter($this->entityTypeManager->getDefinition($entity_type_id), $this->fieldDefinition->getTargetBundle());
    $route_parameters['field_config'] = $this->fieldDefinition->id();
    $url = Url::fromRoute('entity.field_config.' . $entity_type_id . '_field_edit_form', $route_parameters);
    if ($url->access($this->currentUser)) {
      return $this->t('There are no allowed media types configured for this field. <a href=":url">Edit the field settings</a> to select the allowed media types.', [
        ':url' => $url->toString(),
      ]);
    }

    return $default_message;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    return $element['target_id'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if (isset($values['selection'])) {
      usort($values['selection'], [SortArray::class, 'sortByWeightElement']);
      return $values['selection'];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function updateWidget(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];

    $is_remove_button = end($triggering_element['#parents']) === 'remove_button';
    $length = $is_remove_button ? -3 : -1;
    if (count($triggering_element['#array_parents']) < abs($length)) {
      throw new \LogicException('The element that triggered the widget update was at an unexpected depth. Triggering element parents were: ' . implode(',', $triggering_element['#array_parents']));
    }
    $parents = array_slice($triggering_element['#array_parents'], 0, $length);
    $element = NestedArray::getValue($form, $parents);

    $element['media_folders_selection']['#value'] = '';

    $field_state = static::getFieldState($element, $form_state);

    if ($is_remove_button) {
      $media_item = Media::load($field_state['removed_item_id']);
      $announcement = $media_item->access('view label') ? new TranslatableMarkup('@label has been removed.', ['@label' => $media_item->label()]) : new TranslatableMarkup('Media has been removed.');
    }
    else {
      $new_items = static::getNewMediaItems($element, $form_state);
      $media = end($new_items);
      $form_state->getStorage()['last_folder'] = $media->field_folders_folder->target_id;

      $announcement = \Drupal::translation()->formatPlural(count($new_items), 'Added one file.', 'Added @count files.');
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#$wrapper_id", $element));
    $response->addCommand(new AnnounceCommand($announcement));

    $removed_last = $is_remove_button && !count($field_state['items']);
    if ($is_remove_button && !$removed_last) {
      $removed_item_weight = $field_state['removed_item_weight'];
      $delta_to_focus = 0;
      foreach ($field_state['items'] as $delta => $item_fields) {
        $delta_to_focus = $delta;
        if ($item_fields['weight'] > $removed_item_weight) {
          $delta_to_focus--;
          break;
        }
      }
      $response->addCommand(new InvokeCommand("#$wrapper_id [data-media-folders-item-delta=$delta_to_focus]", 'focus'));
    }
    elseif ($removed_last || (!$is_remove_button && !isset($element['open_button']['#attributes']['data-disabled-focus']))) {
      $response->addCommand(new InvokeCommand("#$wrapper_id .js-media-folders-open-button", 'focus'));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public static function removeItem(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#parents'], 0, -2);
    NestedArray::setValue($form_state->getUserInput(), $parents, NULL);

    if (count($triggering_element['#array_parents']) < 4) {
      throw new \LogicException('Expected the remove button to be more than four levels deep in the form. Triggering element parents were: ' . implode(',', $triggering_element['#array_parents']));
    }
    $parents = array_slice($triggering_element['#array_parents'], 0, -3);
    $element = NestedArray::getValue($form, $parents);

    $path = $element['#parents'];
    $values = NestedArray::getValue($form_state->getValues(), $path);
    $field_state = static::getFieldState($element, $form_state);

    $delta = array_slice($triggering_element['#array_parents'], -2, 1)[0];
    if (isset($values['selection'][$delta])) {
      $media = Media::load($values['selection'][$delta]['target_id']);
      $form_state->getStorage()['last_folder'] = $media->field_folders_folder->target_id;
      $field_state['removed_item_weight'] = $values['selection'][$delta]['weight'];
      $field_state['removed_item_id'] = $triggering_element['#media_id'];
      unset($values['selection'][$delta]);
      $field_state['items'] = $values['selection'];
      static::setFieldState($element, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public static function openMediaFolders(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    try {
      $storage = $form_state->getStorage();
      $last_folder = (!empty($storage['last_folder'])) ? $storage['last_folder'] : 0;
    }
    catch (\Throwable $th) {
      $last_folder = 0;
    }

    $folders_ui = \Drupal::service('media_folders.ui_builder')->buildWidgetUi($triggering_element['#media_folders_state'], $last_folder);

    $dialog_options = MediaFoldersUiBuilder::dialogOptions();
    return (new AjaxResponse())->addCommand(new OpenModalDialogCommand($dialog_options['title'], $folders_ui, $dialog_options));
  }

  /**
   * {@inheritdoc}
   */
  public static function validateItems(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    $field_state = static::getFieldState($element, $form_state);
    $media = static::getNewMediaItems($element, $form_state);
    if (empty($media)) {
      return;
    }

    $cardinality_unlimited = ($element['#cardinality'] === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $selection = count($field_state['items']) + count($media);
    if (!$cardinality_unlimited && ($selection > $element['#cardinality'])) {
      $form_state->setError($element, \Drupal::translation()->formatPlural($element['#cardinality'], 'Only one item can be selected.', 'Only @count items can be selected.'));
    }

    $all_bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('media');
    $bundle_labels = array_map(function ($bundle) use ($all_bundles) {
      return $all_bundles[$bundle]['label'];
    }, $element['#target_bundles']);
    foreach ($media as $media_item) {
      if ($element['#target_bundles'] && !in_array($media_item->bundle(), $element['#target_bundles'], TRUE)) {
        $form_state->setError($element, new TranslatableMarkup('The file "@label" is not of an accepted type. Allowed types: @types', [
          '@label' => $media_item->label(),
          '@types' => implode(', ', $bundle_labels),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function addItems(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $parents = array_slice($button['#parents'], 0, -1);
    $parents[] = 'selection';
    NestedArray::setValue($form_state->getUserInput(), $parents, NULL);

    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    $field_state = static::getFieldState($element, $form_state);

    $media = static::getNewMediaItems($element, $form_state);
    if (!empty($media)) {
      $last_element = end($field_state['items']);
      $weight = $last_element ? $last_element['weight'] : 0;
      foreach ($media as $media_item) {
        if ($media_item->access('view')) {
          $field_state['items'][] = [
            'target_id' => $media_item->id(),
            'weight' => ++$weight,
          ];
        }
      }
      static::setFieldState($element, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  protected static function getNewMediaItems(array $element, FormStateInterface $form_state) {
    $values = $form_state->getUserInput();
    $path = $element['#parents'];
    $value = NestedArray::getValue($values, $path);

    if (!empty($value['media_folders_selection'])) {
      $ids = explode(',', $value['media_folders_selection']);
      $ids = array_filter($ids, 'is_numeric');
      if (!empty($ids)) {
        return Media::loadMultiple($ids);
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getFieldState(array $element, FormStateInterface $form_state) {
    $path = $element['#parents'];
    $values = NestedArray::getValue($form_state->getUserInput(), $path);
    $selection = $values['selection'] ?? [];

    $widget_state = static::getWidgetState($element['#field_parents'], $element['#field_name'], $form_state);
    $widget_state['items'] = $widget_state['items'] ?? $selection;
    return $widget_state;
  }

  /**
   * {@inheritdoc}
   */
  protected static function setFieldState(array $element, FormStateInterface $form_state, array $field_state) {
    static::setWidgetState($element['#field_parents'], $element['#field_name'], $form_state, $field_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function validateRequired(array $element, FormStateInterface $form_state, array $form) {
    if (in_array([static::class, 'removeItem'], $form_state->getSubmitHandlers(), TRUE)) {
      return;
    }

    if (isset($element['#access']) && !$element['#access']) {
      return;
    }

    $field_state = static::getFieldState($element, $form_state);
    if (count($field_state['items']) === 0) {
      $form_state->setError($element, new TranslatableMarkup('@name field is required.', ['@name' => $element['#title']]));
    }
  }

}
