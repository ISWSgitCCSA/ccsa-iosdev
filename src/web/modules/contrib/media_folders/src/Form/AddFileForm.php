<?php

namespace Drupal\media_folders\Form;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media_folders\MediaFoldersUiActions;
use Drupal\media_folders\MediaFoldersUiBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form builder.
 */
class AddFileForm extends FormBase {

  /**
   * The Get EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The folders ui actions service.
   *
   * @var \Drupal\media_folders\MediaFoldersUiActions
   */
  protected $foldersActionsUi;

  /**
   * The folders ui service.
   *
   * @var \Drupal\media_folders\MediaFoldersUiBuilder
   */
  protected $foldersUi;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    EntityTypeBundleInfoInterface $bundle_info,
    RendererInterface $renderer,
    MediaFoldersUiActions $media_folders_actions_ui,
    MediaFoldersUiBuilder $media_folders_ui,
    RequestStack $request_stack,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $this->entityTypeManager->getStorage('user')->load($current_user->id());
    $this->bundleInfo = $bundle_info;
    $this->renderer = $renderer;
    $this->foldersActionsUi = $media_folders_actions_ui;
    $this->foldersUi = $media_folders_ui;
    $this->request = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity_type.bundle.info'),
      $container->get('renderer'),
      $container->get('media_folders.ui_actions'),
      $container->get('media_folders.ui_builder'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add-folder-form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, mixed $folder = NULL) {
    $field_validators = [
      'FileExtension' => [
        'extensions' => [],
      ],
      'FileSizeLimit' => [
        'fileLimit' => Environment::getUploadMaxSize(),
      ],
    ];
    $validators = $this->foldersActionsUi->getFileValidators();
    $bundles = $this->bundleInfo->getBundleInfo('media');
    foreach (array_keys($bundles) as $bundle_id) {
      if ($this->foldersUi->hasMediaCreateAccess(NULL, $bundle_id, TRUE)) {
        if (!empty($validators[$bundle_id]['FileExtension']['extensions'])) {
          $field_validators['FileExtension']['extensions'][] = $validators[$bundle_id]['FileExtension']['extensions'];
          if (!empty($validators[$bundle_id]['FileSizeLimit']['fileLimit'])) {
            $bytes = Bytes::toNumber($validators[$bundle_id]['FileSizeLimit']['fileLimit']);
            if ($bytes < $field_validators['FileSizeLimit']['fileLimit']) {
              $field_validators['FileSizeLimit']['fileLimit'] = $bytes;
            }
          }
        }
      }
    }
    $field_validators['FileExtension']['extensions'] = implode(' ', $field_validators['FileExtension']['extensions']);

    $file_upload_help = [
      '#theme' => 'file_upload_help',
      '#upload_validators' => $field_validators,
    ];
    $form['file'] = [
      '#type' => 'managed_file',
      '#required' => TRUE,
      '#title' => $this->t('Files'),
      '#multiple' => TRUE,
      "#upload_location" => "public://media_folders",
      '#upload_validators' => $field_validators,
      '#description' => $this->renderer->renderInIsolation($file_upload_help),
      '#process' => [
        [
          'Drupal\file\Element\ManagedFile',
          'processManagedFile',
        ],
        [
          'Drupal\media_folders\Form\AddFileForm',
          'process',
        ],
      ],
    ];

    $request = (!empty($this->request->getCurrentRequest()->request)) ? $this->request->getCurrentRequest()->request->all() : [];
    if (!empty($request['dialogOptions']['files'])) {
      $form['file']['#default_value'] = $this->foldersActionsUi->ajaxGetUploadedFiles($folder, $request);
    }

    $form['parent'] = [
      '#type' => 'hidden',
      '#value' => ($folder) ? $folder : 0,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['upload'] = [
      '#type' => 'button',
      '#ajax' => [
        'callback' => '::submitCallback',
        'wrapper' => 'upload-form',
      ],
      '#value' => $this->t('Upload files'),
    ];

    $form['#attached']['library'][] = 'media_folders/common';

    return $form;
  }

  /**
   * Form API callback: Processes an image_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    if (!empty($element['remove_button'])) {
      unset($element['remove_button']);
    }
    if (!empty($element['#value']['fids'])) {
      $bundles_labels = \Drupal::service('entity_type.bundle.info')->getBundleInfo('media');
      $target_bundles = $form_state->get('target_bundles');
      foreach ($element['#files'] as $file) {
        $fid = $file->id();
        $file_name = $file->filename->value;
        $position = strrpos($file_name, '.');
        $extension = substr($file_name, $position);
        $validators = \Drupal::service('media_folders.ui_actions')->getFileValidators();
        $bundles = \Drupal::service('media_folders.ui_actions')->findBundleByExtension($extension, $validators);
        $possible_bundles = [];

        $alt_field = FALSE;
        $alt_field_required = FALSE;
        $title_field = FALSE;
        $title_field_required = FALSE;
        foreach ($bundles as $bundle) {
          if ($bundle && (empty($target_bundles) || (!empty($target_bundles) && in_array($bundle, $target_bundles))) && \Drupal::service('media_folders.ui_builder')->hasMediaCreateAccess(NULL, $bundle, TRUE)) {
            $possible_bundles[$bundle] = $bundles_labels[$bundle]['label'];
          }
          $field = MediaFoldersUiBuilder::getFolderEntitiesFileField($bundle);
          $bundle_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('media', $bundle);
          $settings = $bundle_fields[$field]->getSettings();

          if (!empty($settings['alt_field']) && $settings['alt_field']) {
            $alt_field = TRUE;
          }
          if (!empty($settings['alt_field_required']) && $settings['alt_field_required']) {
            $alt_field_required = TRUE;
          }
          if (!empty($settings['title_field']) && $settings['title_field']) {
            $title_field = TRUE;
          }
          if (!empty($settings['title_field_required']) && $settings['title_field_required']) {
            $title_field_required = TRUE;
          }
        }

        if (!empty($element['file_' . $fid])) {
          $image = \Drupal::service('image.factory')->get($file->getFileUri());
          if ($image->isValid()) {
            $width = $image->getWidth();
            $height = $image->getHeight();
          }
          else {
            $width = $height = NULL;
          }

          $element['file_' . $fid . '_wrapper'] = [
            '#type' => 'fieldset',
            '#title' => $element['file_' . $fid]['selected']['#title'],
            '#attributes' => [
              'class' => ['file-form'],
            ],
            'fields' => [
              '#theme' => 'media_folders_file_widget',
              'preview' => [
                '#weight' => -14,
                '#theme' => 'image_style',
                '#width' => $width,
                '#height' => $height,
                '#style_name' => 'thumbnail',
                '#uri' => $file->getFileUri(),
                '#access' => $image->isValid(),
              ],
              'bundle' => [
                '#type' => 'select',
                '#title' => new TranslatableMarkup('Bundle'),
                '#default_value' => '',
                '#description' => new TranslatableMarkup('Choose the target media bundle.'),
                '#options' => $possible_bundles,
                '#weight' => -13,
                '#access' => count($possible_bundles) > 1,
              ],
              'alt' => [
                '#title' => new TranslatableMarkup('Alternative text'),
                '#type' => 'textfield',
                '#default_value' => '',
                '#description' => new TranslatableMarkup('Short description of the image used by screen readers and displayed when the image is not loaded. This is important for accessibility.'),
                '#maxlength' => 512,
                '#weight' => -12,
                '#access' => $alt_field,
                '#required' => $alt_field_required,
                '#element_validate' => $alt_field_required == 1 ? [
                  [static::class, 'validateRequiredFields'],
                ] : [],
              ],
              'title' => [
                '#type' => 'textfield',
                '#title' => new TranslatableMarkup('Title'),
                '#default_value' => '',
                '#description' => new TranslatableMarkup('The title is used as a tool tip when the user hovers the mouse over the image.'),
                '#maxlength' => 1024,
                '#weight' => -11,
                '#access' => $title_field,
                '#required' => $title_field_required,
                '#element_validate' => $title_field_required == 1 ? [
                  [static::class, 'validateRequiredFields'],
                ] : [],
              ],
            ],
          ];
          if (count($possible_bundles) == 1) {
            $element['file_' . $fid . '_wrapper']['fields']['bundle'] = [
              '#type' => 'hidden',
              '#value' => key($possible_bundles),
              '#access' => TRUE,
            ];
          }
          unset($element['file_' . $fid]['selected']);
          $element['upload']['#access'] = FALSE;
          unset($element['#description']);
        }
      }
    }

    return $element;
  }

  /**
   * Validate callback for alt and title field, if the user wants them required.
   *
   * This is separated in a validate function instead of a #required flag to
   * avoid being validated on the process callback.
   */
  public static function validateRequiredFields($element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['#submit']) && in_array('file_managed_file_submit', $triggering_element['#submit'], TRUE)) {
      $form_state->setLimitValidationErrors([]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $form_values = $form_state->getValues();
    $response = new AjaxResponse();

    $errors = $form_state->getErrors();
    if (!empty($errors)) {
      foreach ($errors as $error) {
        $response->addCommand(new MessageCommand($error, '#folders-form-errors', ['type' => 'error']));
      }
      $this->messenger()->deleteAll();
      return $response;
    }

    if (!empty($form_values['file']) && isset($form_values['parent'])) {
      $user_input = $form_state->getUserInput();
      $filenames = $this->foldersActionsUi->getFolderFileNames($form_values['parent']);
      $folder = $this->entityTypeManager->getStorage('taxonomy_term')->load($form_values['parent']);
      $file_count = 0;
      foreach ($form_values['file'] as $file_id) {
        if (is_numeric($file_id) && !empty($user_input['file']['file_' . $file_id . '_wrapper']['fields']['bundle'])) {
          $media_file = $this->entityTypeManager->getStorage('file')->load($file_id);
          $media_file_name = $media_file->filename->value;
          $bundle = $user_input['file']['file_' . $file_id . '_wrapper']['fields']['bundle'];
          $alt = (!empty($user_input['file']['file_' . $file_id . '_wrapper']['fields']['alt'])) ? $user_input['file']['file_' . $file_id . '_wrapper']['fields']['alt'] : NULL;
          $title = (!empty($user_input['file']['file_' . $file_id . '_wrapper']['fields']['title'])) ? $user_input['file']['file_' . $file_id . '_wrapper']['fields']['title'] : NULL;
          $this->foldersActionsUi->fileNameRename($media_file_name, $filenames);
          $this->foldersActionsUi->createMedia($media_file, $folder, $bundle, $media_file_name, $alt, $title);
          $file_count++;
        }
      }
      $response->addCommand(new MessageCommand($this->t('@count files uploaded', [
        '@count' => $file_count,
      ]), '#folders-messages'));
      $response->addCommand(new InvokeCommand('.navbar-folder[data-id=' . $form_values['parent'] . '] a', 'click'));
    }
    $response->addCommand(new CloseDialogCommand('.ui-dialog:has(.add-folder-form) .ui-dialog-content'));

    return $response;
  }

}
