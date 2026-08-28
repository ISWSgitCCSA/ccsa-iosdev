<?php

namespace Drupal\Tests\media_folders\FunctionalJavascript;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\MediaTypeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use org\bovigo\vfs\vfsStream;

/**
 * Ensures that media folders UI works correctly.
 */
class MediaFoldersUiJavascriptTest extends WebDriverTestBase {

  use MediaTypeCreationTrait;

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
   * Tests admin UI.
   */
  public function testJavascriptView(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $test_filename = 'test.txt';
    $test_filepath = 'public://' . $test_filename;
    file_put_contents($test_filepath, $this->randomMachineName());

    $term_values = [
      'parent' => [0],
      'name' => 'Test',
      'description' => '',
      'vid' => 'media_folders_folder',
    ];
    $term = Term::create($term_values);
    $term->save();

    $fileMediaType = $this->createMediaType('file', [
      'id' => 'document',
      'label' => 'Document',
    ]);

    $media = $this->generateMedia('test.pdf', $fileMediaType);
    $media->save();

    $editor_user = $this->drupalCreateUser([
      'access media overview',
      'create media',
      'administer taxonomy',
    ]);

    $this->drupalLogin($editor_user);

    $this->drupalGet('/admin/content/media-folders');

    $this->click('#explorer #navbar > ul li > div[data-id="1"] > a');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseContains('<div class="folder folder-file" data-id="1" data-uuid');

    $this->click('#media-folders .top-bar .up-button');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseNotContains('<div class="folder folder-file" data-id="1" data-uuid');
    $assert_session->responseContains('<div class="folder folder-folder" data-id="1" data-uuid');

    $this->click('#media-folders .top-bar .buttons a.thumbs');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseContains('<div id="media-folders" class="thumbs"');

    $this->click('#media-folders .top-bar .buttons a.list');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseContains('<div id="media-folders" class="list"');

    $this->click('#media-folders .top-bar .operations .dropbutton-toggle button');
    $this->click('#media-folders .top-bar .operations a[href*="add-folder"]');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal'));
    $assert_session->responseContains('<form class="add-folder-form"');
    $page->fillField('name', 'New folder');
    $page->fillField('description', 'Description');
    $this->click('.ui-dialog .ui-widget-content.ui-dialog-buttonpane .button');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->addressEquals('admin/content/media-folders');
    $this->drupalGet('/admin/content/media-folders');
    $assert_session->responseContains('<div class="folder folder-folder" data-id="2" data-uuid');

    $this->click('#media-folders .top-bar .operations .dropbutton-toggle button');
    $this->click('#media-folders .top-bar .operations a[href*="/add-file"]');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal'));
    $assert_session->responseContains('<form class="add-folder-form"');

    $page->attachFileToField("files[file][]", \Drupal::service('file_system')->realpath($test_filepath));
    $assert_session->assertWaitOnAjaxRequest();
    $this->click('.ui-dialog .ui-widget-content.ui-dialog-buttonpane .button');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->addressEquals('admin/content/media-folders');
    $this->drupalGet('/admin/content/media-folders');
    $assert_session->responseContains('<div class="folder folder-file" data-id="2" data-uuid');

    $this->drupalGet('/admin/content/media-folders/2');
    $this->click('#media-folders .top-bar .operations .dropbutton-toggle button');
    $this->click('#media-folders .top-bar .operations a[href*="edit-folder"]');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal'));
    $page->fillField('name', 'New folder updated');
    $this->click('.ui-dialog .ui-widget-content.ui-dialog-buttonpane .button.button--primary');
    $assert_session->waitForElement('css', '.messages-list');
    $assert_session->addressEquals('admin/content/media-folders/2');
    $assert_session->pageTextContains("Folder saved");
    $assert_session->responseContains('<span>New folder upd…</span>');

    $this->click('#media-folders .top-bar .operations .dropbutton-toggle button');
    $this->click('#media-folders .top-bar .operations a[href*="delete-folder"]');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal'));
    $this->click('.ui-dialog .ui-widget-content.ui-dialog-buttonpane .button.button--primary');
    $assert_session->waitForElement('css', '.messages-list');
    $assert_session->addressEquals('admin/content/media-folders');
    $assert_session->pageTextContains("Folder deleted");
    $assert_session->responseNotContains('<div class="folder folder-folder" data-id="2" data-uuid');
  }

  /**
   * Helper to generate a media item.
   *
   * @param string $filename
   *   String filename with extension.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   *
   * @return \Drupal\media\Entity\Media
   *   A media item.
   */
  protected function generateMedia($filename, MediaTypeInterface $media_type) {
    vfsStream::setup('drupal_root');
    vfsStream::create([
      'sites' => [
        'default' => [
          'files' => [
            $filename => str_repeat('a', 3000),
          ],
        ],
      ],
    ]);

    $file = File::create([
      'uri' => 'public://' . $filename,
    ]);
    $file->setPermanent();
    $file->save();

    return Media::create([
      'bundle' => $media_type->id(),
      'name' => $filename,
      'field_media_file' => [
        'target_id' => $file->id(),
      ],
      'field_folders_folder' => [
        'target_id' => 1,
      ],
    ]);
  }

}
