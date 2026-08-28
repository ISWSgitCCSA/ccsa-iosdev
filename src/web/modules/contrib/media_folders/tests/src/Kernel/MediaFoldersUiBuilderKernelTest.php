<?php

namespace Drupal\Tests\media_folders\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Defines a class for testing media_folders services.
 */
class MediaFoldersUiBuilderKernelTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'taxonomy',
    'views',
    'file',
    'media_library',
    'user',
    'media_folders',
  ];

  /**
   * {@inheritdoc}
   */
  public function testServiceContainer() {
    $service = $this->container->get('media_folders.ui_builder');
    $this->assertNotNull($service);
  }

}
