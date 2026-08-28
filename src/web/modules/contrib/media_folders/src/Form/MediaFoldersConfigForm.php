<?php

namespace Drupal\media_folders\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure settings for the media folder file.
 */
class MediaFoldersConfigForm extends ConfigFormBase {

  /**
   * The bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_folders_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'media_folders.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    EntityTypeBundleInfoInterface $bundle_info,
    EntityDisplayRepository $entity_display_repository,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->bundleInfo = $bundle_info;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('media_folders.settings');

    $form['config'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration'),
      '#open' => TRUE,
    ];

    $form['config']['default_view'] = [
      '#type' => 'select',
      '#options' => [
        'thumbs' => $this->t('Folder view'),
        'list' => $this->t('List view'),
      ],
      '#required' => TRUE,
      '#title' => $this->t('Default View Mode'),
      '#default_value' => $config->get('default_view'),
    ];

    $form['config']['default_order'] = [
      '#type' => 'select',
      '#options' => [
        'az' => $this->t('Alphabetic ascending'),
        'za' => $this->t('Alphabetic descending'),
        'date-asc' => $this->t('Date ascending'),
        'date-desc' => $this->t('Date descending'),
      ],
      '#required' => TRUE,
      '#title' => $this->t('Default Order'),
      '#default_value' => $config->get('default_order'),
    ];

    $form['config']['pager_limit'] = [
      '#type' => 'number',
      '#min' => 1,
      '#required' => TRUE,
      '#title' => $this->t('Files per "page"'),
      '#default_value' => $config->get('pager_limit'),
    ];

    $form['config']['disable_ckeditor'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable ckeditor plugin'),
      '#default_value' => $config->get('disable_ckeditor'),
    ];

    $form['config']['show_thumbnails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show image thumbnails'),
      '#default_value' => $config->get('show_thumbnails'),
    ];

    $form['config']['form_mode'] = [
      '#type' => 'details',
      '#title' => $this->t('Media edit form modes'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $default_form_modes = $config->get('form_mode');
    foreach ($this->bundleInfo->getBundleInfo('media') as $bundle => $values) {
      $form_modes = [];
      $custom_form_modes = $this->entityDisplayRepository->getFormModeOptionsByBundle('media', $bundle);
      if (!empty($custom_form_modes)) {
        foreach ($custom_form_modes as $key => $value) {
          $form_modes[$key] = $value;
        }
      }
      $form['config']['form_mode'][$bundle] = [
        '#type' => 'select',
        '#options' => $form_modes,
        '#required' => TRUE,
        '#title' => $values['label'],
        '#default_value' => (!empty($default_form_modes[$bundle])) ? $default_form_modes[$bundle] : 'default',
      ];
    }

    $bundles_by_extensions = _media_folders_get_bundles_by_extension();
    if (!empty($bundles_by_extensions) && count(max($bundles_by_extensions)) > 1) {
      $form['bundles'] = [
        '#type' => 'details',
        '#title' => $this->t('Extension bundles'),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];
      $bundles_labels = $this->bundleInfo->getBundleInfo('media');
      $default_bundles_config = $config->get('bundles');
      $default_bundles = [];
      if (!empty($default_bundles_config)) {
        foreach ($default_bundles_config as $values) {
          $default_bundles[$values['extension']] = $values['bundle'];
        }
      }

      foreach ($bundles_by_extensions as $extension => $bundles) {
        if (count($bundles) > 1) {
          $options = ['' => $this->t('- Always ask -')];
          foreach ($bundles as $value) {
            $options[$value] = $bundles_labels[$value]['label'];
          }
          $form['bundles'][$extension] = [
            '#type' => 'select',
            '#options' => $options,
            '#title' => $this->t('Bundle for @extension files', ['@extension' => $extension]),
            '#default_value' => (!empty($default_bundles[$extension])) ? $default_bundles[$extension] : '',
          ];
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('media_folders.settings');
    $config
      ->set('default_view', $form_state->getValue('default_view'))
      ->set('default_order', $form_state->getValue('default_order'))
      ->set('pager_limit', $form_state->getValue('pager_limit'))
      ->set('disable_ckeditor', $form_state->getValue('disable_ckeditor'))
      ->set('show_thumbnails', $form_state->getValue('show_thumbnails'))
      ->set('form_mode', $form_state->getValue('form_mode'));

    if (!empty($form_state->getValue('bundles'))) {
      $bundles = [];
      foreach ($form_state->getValue('bundles') as $key => $value) {
        $bundles[] = [
          'extension' => $key,
          'bundle' => $value,
        ];
      }
      $config->set('bundles', $bundles);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
