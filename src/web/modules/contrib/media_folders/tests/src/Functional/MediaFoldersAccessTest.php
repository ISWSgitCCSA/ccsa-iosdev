<?php

namespace Drupal\Tests\media_folders\Functional;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaTypeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use org\bovigo\vfs\vfsStream;

/**
 * Basic access tests for Media folders.
 */
class MediaFoldersAccessTest extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait;
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
   * Tests some access control functionality.
   */
  public function testMediaFoldersAccess(): void {
    $assert_session = $this->assertSession();

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load(RoleInterface::AUTHENTICATED_ID);

    $term_values = [
      'parent' => [0],
      'name' => 'Test',
      'description' => '',
      'vid' => 'media_folders_folder',
    ];
    $term = Term::create($term_values);
    $term->save();

    $this->createMediaType('image', [
      'id' => 'image',
      'label' => 'Image',
    ]);

    $fileMediaType = $this->createMediaType('file', [
      'id' => 'document',
      'label' => 'Document',
    ]);

    $fileMedia = $this->generateMedia('test.pdf', $fileMediaType, 1);
    $fileMedia->save();

    $editor_user = $this->drupalCreateUser([]);

    // No permissions test.
    $this->drupalLogin($editor_user);

    $this->drupalGet('/admin/content/media-folders');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/ajax');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/ajax');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/add-file');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/root/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/edit-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/delete-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/preview/ajax');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders-cookie/view-type/list/ajax');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/config/media-folders');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    // Add "access media folders configuration" permission.
    user_role_grant_permissions($role->id(), ['access media folders configuration']);

    $this->drupalGet('/admin/config/media-folders');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    // Add "access media overview" permission.
    user_role_grant_permissions($role->id(), ['access media overview']);

    $this->drupalGet('/admin/content/media-folders');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('/admin/content/media-folders/ajax');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('/admin/content/media-folders/1');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('/admin/content/media-folders/1/ajax');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('/admin/content/media-folders/1/add-file');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/root/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/edit-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/delete-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/preview/ajax');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('/admin/content/media-folders-cookie/view-type/list');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseHeaderContains('Set-Cookie', 'view-type=list;');

    // Add "edit terms in media_folders_folder" permission.
    user_role_grant_permissions($role->id(), ['edit terms in media_folders_folder']);

    $this->drupalGet('/admin/content/media-folders/1/add-file');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/root/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/edit-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('/admin/content/media-folders/1/delete-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    // Add "delete terms in media_folders_folder" permission.
    user_role_grant_permissions($role->id(), ['delete terms in media_folders_folder']);

    $this->drupalGet('/admin/content/media-folders/1/add-file');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/root/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/1/delete-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    // Add "create terms in media_folders_folder" permission.
    user_role_grant_permissions($role->id(), ['create terms in media_folders_folder']);

    $this->drupalGet('/admin/content/media-folders/1/add-file');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/content/media-folders/root/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('/admin/content/media-folders/1/add-folder');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    // Add "create image media" permission.
    user_role_grant_permissions($role->id(), ['create image media']);
    $this->drupalGet('/admin/content/media-folders/1/add-file');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains("Allowed types: png gif jpg jpeg webp.");

    // Add "create document media" permission.
    user_role_grant_permissions($role->id(), ['create document media']);
    $this->drupalGet('/admin/content/media-folders/1/add-file');
    $assert_session->pageTextContains("Allowed types: png gif jpg jpeg webp txt doc docx pdf");
  }

  /**
   * Helper to generate a media item.
   *
   * @param string $filename
   *   String filename with extension.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   * @param int $uid
   *   Owner id.
   *
   * @return \Drupal\media\Entity\Media
   *   A media item.
   */
  protected function generateMedia($filename, MediaTypeInterface $media_type, $uid) {
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
      'uid' => $uid,
    ]);
  }

}
