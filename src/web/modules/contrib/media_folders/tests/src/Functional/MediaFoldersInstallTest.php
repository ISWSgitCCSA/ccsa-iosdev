<?php

namespace Drupal\Tests\media_folders\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests media_folders Install / Uninstall logic.
 */
class MediaFoldersInstallTest extends BrowserTestBase {

  /**
   * Set default theme to stable.
   *
   * @var string
   */
  protected $defaultTheme = 'stable9';

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
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer modules']));
  }

  /**
   * Tests reinstalling after being uninstalled.
   */
  public function testReinstallAfterUninstall(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->container->get('module_installer')->uninstall(['media_folders'], FALSE);

    $this->drupalGet('/admin/modules');
    $page->checkField('modules[media_folders][enable]');
    $page->pressButton('Install');
    $assert_session->pageTextContains('Module Media Folders has been installed');
  }

}
