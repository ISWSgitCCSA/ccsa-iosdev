<?php

namespace Drupal\Tests\media_folders\FunctionalJavascript;

use Drupal\file\Entity\File;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaTypeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use org\bovigo\vfs\vfsStream;

/**
 * Ensures that media UI works correctly.
 */
class MediaFoldersWidgetUiTest extends WebDriverTestBase {
  use MediaTypeCreationTrait;

  /**
   * Permissions for the admin user that will be logged-in for test.
   *
   * @var array
   */
  protected static $adminUserPermissions = [
    'access media overview',
    'administer media',
    'administer media fields',
    'administer media form display',
    'administer media display',
    'administer media types',
    'view media',
    'access content overview',
    'view all revisions',
    'administer content types',
    'administer node fields',
    'administer node form display',
    'administer node display',
    'bypass node access',
  ];

  /**
   * An admin test user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'node',
    'field_ui',
    'views',
    'views_ui',
    'block',
    'taxonomy',
    'file',
    'media_library',
    'user',
    'media_folders',
  ];

  /**
   * Set default theme to stable.
   *
   * @var string
   */
  protected $defaultTheme = 'stable9';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('local_tasks_block');

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

    $fileMedia = $this->generateMedia('test.pdf', $fileMediaType);
    $fileMedia->save();

    $this->adminUser = $this->drupalCreateUser(static::$adminUserPermissions);
    $this->drupalLogin($this->adminUser);

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
  }

  /**
   * Tests that the Media folders's widget selection works as expected.
   */
  public function testWidgetSelection(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('/admin/structure/types/manage/page/fields/add-field/field_ui:entity_reference:media/false?entity_type=node');
    $second_edit = [
      'label' => 'Media',
    ];
    $this->submitForm($second_edit, 'Continue');
    $assert_session->fieldExists('settings[handler_settings][target_bundles][document]')->check();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('Sort by');
    $page->pressButton('Save');
    $this->submitForm([], 'Save');

    $this->drupalGet('/admin/structure/types/manage/page/fields');
    $assert_session->pageTextContains('Media');

    $this->drupalGet('/admin/structure/types/manage/page/form-display');
    $assert_session->pageTextContains('View mode: List view');

    $this->drupalGet('/admin/structure/types/manage/page/display');
    $assert_session->pageTextContains('field_media');
    $page->pressButton('Show row weights');
    $page->fillField('fields[field_media][region]', 'content');
    $page->fillField('fields[field_media][type]', 'media_folders');
    $page->pressButton('Save');

    $this->drupalGet('/node/add/page');
    $assert_session->responseContains('type="submit" id="edit-field-media-open-button" name="field_media-media-folders-open-button" value="Add media"');
    $page->fillField('title[0][value]', 'Test');
    $page->pressButton('Add media');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal'));
    $assert_session->responseContains('<div id="media-folders" class="widget');
    $assert_session->responseContains('<div class="folder folder-folder" data-id="1" data-uuid');

    $this->click('#explorer #navbar > ul li > div[data-id="1"] > a');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseContains('<div class="folder folder-file" data-id="1" data-uuid');
    $this->click('.folder-file[data-id="1"] a.folder-icon');
    $page->pressButton('Insert selected');
    $this->assertNotEmpty($assert_session->waitForElementRemoved('css', '#drupal-modal'));
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseNotContains('<div id="media-folders" class="widget');
    $page->pressButton('Save');
    $assert_session->addressEquals('node/1');
    $this->drupalGet('/node/1');
    $assert_session->responseContains('<span class="file file--mime-application-pdf file--application-pdf">');
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
