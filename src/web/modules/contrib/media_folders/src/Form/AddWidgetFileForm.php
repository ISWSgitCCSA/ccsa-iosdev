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
use Drupal\media_folders\MediaFoldersUiActions;
use Drupal\media_folders\MediaFoldersUiBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form builder.
 */
class AddWidgetFileForm extends FormBase {

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
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

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
    ConfigFactoryInterface $config_factory,
    EntityFieldManagerInterface $field_manager,
    RequestStack $request_stack,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $this->entityTypeManager->getStorage('user')->load($current_user->id());
    $this->bundleInfo = $bundle_info;
    $this->renderer = $renderer;
    $this->foldersActionsUi = $media_folders_actions_ui;
    $this->foldersUi = $media_folders_ui;
    $this->configFactory = $config_factory;
    $this->fieldManager = $field_manager;
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
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add-widget-folder-form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, mixed $folder = NULL, $entity_type_id = NULL, $bundle = NULL, $widget_id = NULL) {
    $field_validators = [
      'FileExtension' => [
        'extensions' => [],
      ],
      'FileSizeLimit' => [
        'fileLimit' => Environment::getUploadMaxSize(),
      ],
    ];

    $bundle_fields = $this->fieldManager->getFieldDefinitions($entity_type_id, $bundle);
    if (empty($bundle_fields[$widget_id])) {
      [$widget_id, $trash] = explode(':', $widget_id);
    }
    $settings = $bundle_fields[$widget_id]->getSettings();
    $target_bundles = array_values($settings['handler_settings']['target_bundles']);
    $form_state->set('target_bundles', $target_bundles);
    $validators = $this->foldersActionsUi->getFileValidators();
    $bundles = $this->bundleInfo->getBundleInfo('media');

    foreach (array_keys($bundles) as $bundle_id) {
      if (!in_array($bundle_id, $target_bundles)) {
        continue;
      }
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
      '#title' => $this->t('File'),
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

    return $form;
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
    $response->addCommand(new CloseDialogCommand('.ui-dialog:has(.add-widget-folder-form) .ui-dialog-content'));

    return $response;
  }

}
