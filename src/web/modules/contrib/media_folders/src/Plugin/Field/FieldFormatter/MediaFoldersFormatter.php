<?php

namespace Drupal\media_folders\Plugin\Field\FieldFormatter;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Url;
use Drupal\media_folders\MediaFoldersUiBuilder;

#[FieldFormatter(
  id: 'media_folders',
  label: new TranslatableMarkup('Media Folders'),
  field_types: [
    'entity_reference',
  ],
)]
/**
 * {@inheritdoc}
 */
class MediaFoldersFormatter extends ImageFormatter {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, ImageStyleStorageInterface $image_style_storage, FileUrlGeneratorInterface $file_url_generator, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage, $file_url_generator);
    $this->renderer = $renderer;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('file_url_generator'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image_style' => '',
      'image_loading' => [
        'attribute' => 'lazy',
      ],
      'images' => [
        'image_style' => '',
        'image_loading' => [
          'attribute' => 'lazy',
        ],
      ],
      'videos' => [
        'video_width' => '400',
        'video_height' => '250',
        'video_muted' => FALSE,
        'video_controls' => TRUE,
        'video_autoplay' => FALSE,
      ],
      'audio' => [
        'audio_loop' => FALSE,
        'audio_controls' => TRUE,
        'audio_autoplay' => FALSE,
      ],
      'other' => [
        'other_format' => 'file_link',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    unset($element['image_link']);
    $field_settings = $this->getFieldSettings();
    $target_bundles = $field_settings['handler_settings']['target_bundles'];

    $element['images'] = [
      '#type' => 'details',
      '#title' => $this->t('Images'),
    ];
    $images = $this->getSetting('images');
    $element['images']['image_style'] = $element['image_style'];
    $element['images']['image_style']['#default_value'] = $images['image_style'];
    unset($element['image_style']);
    $element['images']['image_loading'] = $element['image_loading'];
    $element['images']['image_loading']['attribute']['#default_value'] = $images['image_loading']['attribute'];
    unset($element['image_loading']);
    if (!in_array('image', $target_bundles)) {
      $element['images']['#access'] = FALSE;
    }
    unset($target_bundles['image']);

    $element['videos'] = [
      '#type' => 'details',
      '#title' => $this->t('Videos'),
    ];
    $videos = $this->getSetting('videos');
    $element['videos']['video_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Width'),
      '#default_value' => $videos['video_width'],
    ];
    $element['videos']['video_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#default_value' => $videos['video_height'],
    ];
    $element['videos']['video_muted'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Muted'),
      '#default_value' => $videos['video_muted'],
    ];
    $element['videos']['video_controls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Controls'),
      '#default_value' => $videos['video_controls'],
    ];
    $element['videos']['video_autoplay'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autoplay'),
      '#default_value' => $videos['video_autoplay'],
    ];
    if (!in_array('video', $target_bundles)) {
      $element['videos']['#access'] = FALSE;
    }
    unset($target_bundles['video']);

    $element['audio'] = [
      '#type' => 'details',
      '#title' => $this->t('Audio'),
    ];
    $audio = $this->getSetting('audio');
    $element['audio']['audio_loop'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Loop'),
      '#default_value' => $audio['audio_loop'],
    ];
    $element['audio']['audio_controls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Controls'),
      '#default_value' => $audio['audio_controls'],
    ];
    $element['audio']['audio_autoplay'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Autoplay'),
      '#default_value' => $audio['audio_autoplay'],
    ];
    if (!in_array('audio', $target_bundles)) {
      $element['audio']['#access'] = FALSE;
    }
    unset($target_bundles['audio']);

    $element['other'] = [
      '#type' => 'details',
      '#title' => $this->t('Other files'),
    ];
    $other = $this->getSetting('other');
    $element['other']['other_format'] = [
      '#type' => 'select',
      '#options' => [
        'file_link' => $this->t('Formatted link'),
        'link' => $this->t('Simple link'),
      ],
      '#title' => $this->t('Format'),
      '#default_value' => $other['other_format'],
    ];
    if (count($target_bundles) == 0) {
      $element['other']['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $field_settings = $this->getFieldSettings();
    $target_bundles = $field_settings['handler_settings']['target_bundles'];
    $summary = [];

    $image_styles = image_style_options(FALSE);
    unset($image_styles['']);

    $images = $this->getSetting('images');
    $image_style_setting = $images['image_style'];
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = $this->t('Image: @style', ['@style' => $image_styles[$image_style_setting]]);
    }
    else {
      $summary[] = $this->t('Image: Original');
    }
    if (!in_array('image', $target_bundles)) {
      array_pop($summary);
    }
    unset($target_bundles['image']);

    $videos = $this->getSetting('videos');
    $summary[] = $this->t('Video: @widthx@height@muted@controls@autoplay', [
      '@width' => $videos['video_width'],
      '@height' => $videos['video_height'],
      '@muted' => $videos['video_muted'] ? ', ' . $this->t('Muted') : '',
      '@controls' => $videos['video_controls'] ? ', ' . $this->t('Controls') : '',
      '@autoplay' => $videos['video_autoplay'] ? ', ' . $this->t('Autoplay') : '',
    ]);
    if (!in_array('video', $target_bundles)) {
      array_pop($summary);
    }
    unset($target_bundles['video']);

    $audio = $this->getSetting('audio');
    $summary[] = $this->t('Audio: @loop@controls@autoplay', [
      '@loop' => $audio['audio_loop'] ? $this->t('Loop') : '',
      '@controls' => $audio['audio_controls'] ? ' ' . $this->t('Controls') : '',
      '@autoplay' => $audio['audio_autoplay'] ? ' ' . $this->t('Autoplay') : '',
    ]);
    if (!in_array('audio', $target_bundles)) {
      array_pop($summary);
    }
    unset($target_bundles['audio']);

    $other = $this->getSetting('other');
    $options = [
      'file_link' => $this->t('Formatted link'),
      'link' => $this->t('Simple link'),
    ];
    $summary[] = $this->t('Other: @format', ['@format' => $options[$other['other_format']]]);
    if (count($target_bundles) == 0) {
      array_pop($summary);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $media_items = $this->getEntitiesToView($items, $langcode);

    if (empty($media_items)) {
      return $elements;
    }

    /** @var \Drupal\media\Entity\Media[] $media_items */
    foreach ($media_items as $delta => $media) {
      $field = MediaFoldersUiBuilder::getFolderEntitiesFileField($media->bundle());

      if ($media->bundle() == 'image') {
        $images = $this->getSetting('images');
        $elements[$delta] = [
          '#theme' => 'image_formatter',
          '#item' => $media->get($field)->first(),
          '#item_attributes' => [
            'loading' => $images['image_loading']['attribute'],
          ],
          '#image_style' => $images['image_style'],
          '#url' => NULL,
        ];
      }
      elseif ($media->bundle() == 'video') {
        $file = $media->get($field)->first()->entity;
        $mime_type = $file->getMimeType();
        $source_attributes = new Attribute();
        $source_attributes->setAttribute('src', $file->createFileUrl())->setAttribute('type', $mime_type);
        $videos = $this->getSetting('videos');
        $elements[$delta] = [
          '#theme' => 'file_video',
          '#attributes' => [
            'width' => $videos['video_width'],
            'height' => $videos['video_height'],
            'muted' => $videos['video_muted'],
            'controls' => $videos['video_controls'],
            'autoplay' => !$videos['video_autoplay'],
          ],
          '#files' => [
            [
              'file' => $file,
              'source_attributes' => $source_attributes,
            ],
          ],
          '#cache' => [
            'tags' => $file->getCacheTags(),
          ],
        ];
      }
      elseif ($media->bundle() == 'audio') {
        $file = $media->get($field)->first()->entity;
        $mime_type = $file->getMimeType();
        $source_attributes = new Attribute();
        $source_attributes->setAttribute('src', $file->createFileUrl())->setAttribute('type', $mime_type);
        $audio = $this->getSetting('audio');
        $elements[$delta] = [
          '#theme' => 'file_audio',
          '#attributes' => [
            'loop' => $audio['audio_loop'],
            'controls' => $audio['audio_controls'],
            'autoplay' => !$audio['audio_autoplay'],
          ],
          '#files' => [
            [
              'file' => $file,
              'source_attributes' => $source_attributes,
            ],
          ],
          '#cache' => [
            'tags' => $file->getCacheTags(),
          ],
        ];
      }
      else {
        $other = $this->getSetting('other');
        $file = $media->get($field)->first()->entity;
        if ($other['other_format'] == 'file_link') {
          $elements[$delta] = [
            '#theme' => 'file_link',
            '#file' => $file,
            '#description' => $media->getName(),
            '#cache' => [
              'tags' => $file->getCacheTags(),
            ],
          ];
        }
        else {
          $elements[$delta] = [
            '#type' => 'link',
            '#title' => $media->getName(),
            '#url' => Url::fromUri('internal:/' . $file->createFileUrl()),
            '#cache' => [
              'tags' => $file->getCacheTags(),
            ],
          ];
        }
      }

      $this->renderer->addCacheableDependency($elements[$delta], $media);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view', NULL, TRUE)
      ->andIf(parent::checkAccess($entity));
  }

}
