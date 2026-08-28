<?php

namespace Drupal\Tests\media_folders\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\file\Entity\File;
use Drupal\media\MediaTypeInterface;
use Drupal\media\Entity\Media;
use org\bovigo\vfs\vfsStream;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests for media_folders admin UI.
 */
class MediaFoldersAdminUiTest extends BrowserTestBase {

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
  public function testView() {
    $assert_session = $this->assertSession();

    $viewer_user = $this->drupalCreateUser([
      'access media overview',
    ]);

    $this->drupalLogin($viewer_user);

    $this->drupalGet('/admin/content/media-folders');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('<div id="media-folders" class="thumbs">');
    $assert_session->responseContains('<div class="folder-navigation">');
    $assert_session->responseContains('<form class="media-folders-search-form"');
    $assert_session->responseContains('<div class="navbar-folder not-droppable" data-id="0">');
    $assert_session->responseContains('<div id="board" class="not-droppable" data-id="0">');
    $assert_session->responseContains('<div class="empty">Empty Folder</div>');

    $editor_user = $this->drupalCreateUser([
      'access media overview',
      'create media',
    ]);

    $this->drupalLogin($editor_user);

    $this->drupalGet('/admin/content/media-folders');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('<div id="media-folders" class="thumbs">');
    $assert_session->responseContains('<div class="folder-navigation">');
    $assert_session->responseContains('<form class="media-folders-search-form"');
    $assert_session->responseContains('<div class="navbar-folder" data-id="0">');
    $assert_session->responseContains('<div id="board" class="" data-id="0">');
    $assert_session->responseContains('<div class="empty">Empty Folder</div>');
    $assert_session->responseContains('admin/content/media-folders/0/add-file');

    $term_values = [
      'parent' => [0],
      'name' => 'Test',
      'description' => '',
      'vid' => 'media_folders_folder',
    ];
    $term = Term::create($term_values);
    $term->save();

    $this->drupalGet('/admin/content/media-folders');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseNotContains('<div class="empty">Empty Folder</div>');
    $assert_session->responseContains('<div class="navbar-folder" data-id="1">');
    $assert_session->responseContains('admin/content/media-folders/1">Test</a></div>');
    $assert_session->responseContains('admin/content/media-folders/1" class="folder-icon folder-icon-folder-empty" data-count="0">');

    $fileMediaType = $this->createMediaType('file', [
      'id' => 'document',
      'label' => 'Document',
    ]);

    $this->drupalGet('/media/add/document');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('<label for="edit-field-folders-folder">Folder</label>');
    $assert_session->responseContains('<option value="1" >Test</option>');

    $media = $this->generateMedia('test.pdf', $fileMediaType);
    $media->save();

    $this->drupalGet('/admin/content/media-folders/1');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseNotContains('<div class="empty">Empty Folder</div>');
    $assert_session->responseContains('<div class="folder folder-file" data-id="1" data-uuid');
    $assert_session->responseContains('<span class="title">test.pdf</span>');
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
