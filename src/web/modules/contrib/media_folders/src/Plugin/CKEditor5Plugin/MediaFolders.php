<?php

declare(strict_types=1);

namespace Drupal\media_folders\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Drupal\media_library\MediaLibraryState;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * CKEditor 5 Media Folders plugin.
 *
 * Provides media folders support and options for the CKEditor 5 build.
 *
 * @internal
 *   Plugin classes are internal.
 */
class MediaFolders extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The media type entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $mediaTypeStorage;

  /**
   * The media folder file settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $mediaFoldersSettings;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, string $plugin_id, CKEditor5PluginDefinition $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory,) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mediaTypeStorage = $entity_type_manager->getStorage('media_type');
    $this->mediaFoldersSettings = $config_factory->get('media_folders.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $media_type_ids = $this->mediaTypeStorage->getQuery()->execute();
    $static_plugin_config['drupalMedia']['dialogSettings']['title'] = $this->t('Add or select media');

    if ($editor->hasAssociatedFilterFormat()) {
      $media_embed_filter = $editor->getFilterFormat()->filters()->get('media_embed');
      if (!empty($media_embed_filter->settings['allowed_media_types'])) {
        $media_type_ids = array_intersect_key($media_type_ids, $media_embed_filter->settings['allowed_media_types']);
      }
    }
    if (in_array('image', $media_type_ids, TRUE)) {
      array_unshift($media_type_ids, 'image');
      $media_type_ids = array_unique($media_type_ids);
    }

    $state = MediaLibraryState::create(
      'media_library.opener.editor',
      $media_type_ids,
      reset($media_type_ids),
      1,
      ['filter_format_id' => $editor->getFilterFormat()->id()],
    );

    $route = $this->mediaFoldersSettings->get('disable_ckeditor') ? 'media_library.ui' : 'media_folders.collection.editor';
    $library_url = Url::fromRoute($route)
      ->setOption('query', $state->all())
      ->toString(TRUE)
      ->getGeneratedUrl();

    $dynamic_plugin_config = $static_plugin_config;
    $dynamic_plugin_config['drupalMedia']['libraryURL'] = $library_url;
    return $dynamic_plugin_config;
  }

}
