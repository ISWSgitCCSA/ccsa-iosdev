<?php

namespace Drupal\media_folders;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\Validation\FileValidatorInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Service which provides actions for managing media folders.
 *
 * This service includes methods for handling file uploads, moving files and
 * folders, and other operations related to media folder management.
 */
class MediaFoldersUiActions {
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * File file repository object.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * File file validator object.
   *
   * @var \Drupal\file\Validation\FileValidatorInterface
   */
  protected $fileValidator;

  /**
   * File transliteration object.
   *
   * @var \Drupal\Core\Transliteration\PhpTransliteration
   */
  protected $transliteration;

  /**
   * The language service.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $languageManager;

  /**
   * File file system object.
   *
   * @var \Drupal\file\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Token object.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The folders ui service.
   *
   * @var \Drupal\media_folders\MediaFoldersUiBuilder
   */
  protected $foldersUi;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    EntityTypeBundleInfoInterface $bundle_info,
    FileRepositoryInterface $file_repository,
    FileValidatorInterface $file_validator,
    PhpTransliteration $php_transliteration,
    LanguageManagerInterface $languageManager,
    FileSystemInterface $file_system,
    Token $token,
    MediaFoldersUiBuilder $media_folders_ui,
    EntityFieldManagerInterface $field_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->bundleInfo = $bundle_info;
    $this->fileRepository = $file_repository;
    $this->fileValidator = $file_validator;
    $this->transliteration = $php_transliteration;
    $this->languageManager = $languageManager;
    $this->fileSystem = $file_system;
    $this->token = $token;
    $this->foldersUi = $media_folders_ui;
    $this->fieldManager = $field_manager;
  }

  /**
   * Moves files or folders into a specified folder via AJAX.
   *
   * @param mixed $folder
   *   The folder entity.
   * @param array $request
   *   The request data containing objects to move.
   *
   * @return array
   *   An array of messages indicating the result of the operation.
   */
  public function ajaxMoveInto($folder, array $request) : array {
    $response = [
      'messages' => [],
    ];

    if (!empty($request['objects'])) {
      $errors = $moved = [];
      $folder = $this->loadFolder($folder);
      $folder_id = ($folder) ? $folder->id() : NULL;
      foreach ($request['objects'] as $object) {
        if (!empty($object['type']) && !empty($object['mid'])) {
          if (!$folder || ($folder && $folder->bundle() == 'media_folders_folder')) {
            if ($object['type'] == 'folder') {
              if ($object['mid'] == $folder_id) {
                $errors[] = $this->t('Can not move folder inside itself!');
              }
              else {
                $move = $this->loadFolder($object['mid']);
                if ($move) {
                  if ($folder) {
                    $children = $this->getChildren($folder);
                  }
                  else {
                    $children = $this->loadFolderTree();
                  }
                  $error = FALSE;
                  if (!empty($children)) {
                    foreach ($children as $child) {
                      if (strtolower($child->getName()) == strtolower($move->getName())) {
                        $errors[] = $this->t('<strong>@folder:</strong> There is already one folder with the same name!', [
                          '@folder' => $child->getName(),
                        ]);
                        $error = TRUE;
                      }
                    }
                  }
                  if (!$error) {
                    $move->parent->target_id = $folder_id;
                    $move->save();
                    $moved['folders'][$move->id()] = $move->getName();
                  }
                }
              }
            }
            elseif ($object['type'] == 'file') {
              $move = $this->entityTypeManager->getStorage('media')->load($object['mid']);
              if ($move) {
                $query = $this->entityTypeManager->getStorage('media')->getQuery();
                $query->condition('status', TRUE);
                if ($folder) {
                  $query->condition('field_folders_folder', $folder->id());
                }
                else {
                  $query->notExists('field_folders_folder');
                }
                $ids = $query->accessCheck(FALSE)->execute();
                $files = $this->entityTypeManager->getStorage('media')->loadMultiple($ids);
                $error = FALSE;
                if (!empty($files)) {
                  foreach ($files as $file) {
                    if (strtolower($file->getName()) == strtolower($move->getName())) {
                      $errors[] = $this->t('<strong>@filename:</strong> There is already one file with the same name!', [
                        '@filename' => $file->getName(),
                      ]);
                      $error = TRUE;
                    }
                  }
                }
                if (!$error) {
                  $move->field_folders_folder->target_id = $folder_id;
                  $move->save();
                  $moved['files'][$move->id()] = $move->getName();
                }
              }
            }
          }
        }
      }

      if (!empty($moved['folders'])) {
        $response['messages'][] = [
          'type' => 'status',
          'message' => $this->formatPlural(count($moved['folders']), 'Moved 1 folder!', 'Moved @count folders!'),
        ];
      }
      if (!empty($moved['files'])) {
        $response['messages'][] = [
          'type' => 'status',
          'message' => $this->formatPlural(count($moved['files']), 'Moved 1 file!', 'Moved @count files!'),
        ];
      }
      if (!empty($errors)) {
        $response['messages'][] = [
          'type' => 'error',
          'message' => implode('<br/>', $errors),
        ];
      }
    }

    return $response;
  }

  /**
   * Handles file uploads to a folder via AJAX.
   *
   * @param mixed $folder
   *   The folder entity.
   * @param array $request
   *   The request data containing file upload details.
   *
   * @return array
   *   An array of messages indicating the result of the operation.
   */
  public function ajaxGetUploadedFiles($folder, array $request) : array {
    $default_value = [];
    $folder_term = $this->entityTypeManager->getStorage('taxonomy_term')->load($folder);
    if (!$folder_term || ($folder_term && $folder_term->bundle() == 'media_folders_folder')) {
      foreach ($request['dialogOptions']['files'] as $file) {
        $default_value[] = $file['file'];
      }
    }

    return $default_value;
  }

  /**
   * Handles file uploads to a folder via AJAX.
   *
   * @param mixed $folder
   *   The folder entity.
   * @param array $request
   *   The request data containing file upload details.
   *
   * @return array
   *   An array of messages indicating the result of the operation.
   */
  public function ajaxUploadFile($folder, array $request) : array {
    $response = [
      'messages' => [],
    ];

    if (!empty($request['request'])) {
      $folder = $this->loadFolder($folder);
      $folder_id = ($folder) ? $folder->id() : NULL;
      if (!$folder || ($folder && $folder->bundle() == 'media_folders_folder')) {
        $validators = $this->getFileValidators();
        $filenames = $this->getFolderFileNames($folder_id);

        if (!empty($request['parameters'])) {
          $parameters = $request['parameters'];
          $bundle_fields = $this->fieldManager->getFieldDefinitions($parameters['entity_type_id'], $parameters['bundle']);
          $settings = $bundle_fields[$parameters['field_name']]->getSettings();
          $target_bundles = array_values($settings['handler_settings']['target_bundles']);
        }

        foreach ($request['request'] as $post) {
          try {
            $media_file_name = $post['name'];
            $position = strrpos($media_file_name, '.');
            $extension = substr($media_file_name, $position);
            $bundles = $this->findBundleByExtension($extension, $validators);
            $file_allowed = FALSE;
            foreach ($bundles as $bundle) {
              if (!(!$bundle || !$this->foldersUi->hasMediaCreateAccess(NULL, $bundle, TRUE) || (!empty($target_bundles) && !in_array($bundle, $target_bundles)))) {
                $file_allowed = TRUE;
              }
            }
            if (!$file_allowed) {
              $response['messages'][] = [
                'type' => 'error',
                'message' => $this->t('File not allowed!'),
              ];
              continue;
            }

            $this->fileNameRename($media_file_name, $filenames);
            $files_data = preg_replace('#^data:application/\w+;base64,#i', '', $post['contents']);
            $files_data = preg_replace('#^data:image/\w+;base64,#i', '', $files_data);
            $files_data = preg_replace('#^data:video/\w+;base64,#i', '', $files_data);
            $files_data = preg_replace('#^data:audio/\w+;base64,#i', '', $files_data);
            $files_data = preg_replace('#^data:text/\w+;base64,#i', '', $files_data);
            $file_data = base64_decode($files_data);
            $file = $this->fileRepository->writeData($file_data, 'temporary://' . $this->sanitizeFilename($media_file_name), FileExists::Rename);
            $file->status->value = 0;
            $file->save();

            $error_messages = [];
            $file_valid = FALSE;
            foreach ($bundles as $bundle) {
              $invalid = $this->fileValidator->validate($file, $validators[$bundle]);
              if ($invalid->count() == 0) {
                $file_valid = TRUE;
              }
              else {
                for ($i = 0; $i < $invalid->count(); $i++) {
                  $error_messages[] = $invalid->get($i)->getMessage()->render();
                }
              }
            }
            if (!$file_valid) {
              foreach ($error_messages as $error_message) {
                $response['messages'][] = [
                  'type' => 'error',
                  'message' => $error_message,
                ];
              }
              continue;
            }

            $destination = 'public://media_folders';
            $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
            $this->fileRepository->move($file, $destination, FileExists::Rename);

            $response['files'][] = [
              'file' => $file->id(),
              'bundles' => $bundles,
            ];
          }
          catch (\Exception $e) {
            $response['messages'][] = [
              'type' => 'error',
              'message' => $e->getMessage(),
            ];
          }
        }
      }
    }

    return $response;
  }

  /**
   * Retrieves file validators for media bundles.
   *
   * @return array
   *   An array of file validators grouped by media bundle.
   */
  public function getFileValidators() : array {
    $validators = [];
    $bundles = $this->bundleInfo->getBundleInfo('media');
    foreach (array_keys($bundles) as $bundle) {
      $field = MediaFoldersUiBuilder::getFolderEntitiesFileField($bundle);
      if ($field) {
        $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $bundle . '.' . $field);
        $field_settings = $field_config->getSettings();

        if (!empty($field_settings['file_extensions'])) {
          $validators[$bundle]['FileExtension'] = ['extensions' => $field_settings['file_extensions']];
        }
        if (!empty($field_settings['max_filesize'])) {
          $bytes = Bytes::toNumber($field_settings['max_filesize']);
          $validators[$bundle]['FileSizeLimit'] = ['fileLimit' => $bytes];
        }
      }
    }

    return $validators;
  }

  /**
   * Retrieves the names of files in a folder.
   *
   * @param mixed $folder
   *   The folder entity.
   *
   * @return array
   *   An array of file names in the folder.
   */
  public function getFolderFileNames($folder) : array {
    $filenames = [];
    $query = $this->entityTypeManager->getStorage('media')->getQuery();
    $query->condition('status', TRUE);
    if ($folder) {
      $query->condition('field_folders_folder', $folder);
    }
    else {
      $query->notExists('field_folders_folder');
    }
    $ids = $query->accessCheck(FALSE)->execute();
    $files = $this->entityTypeManager->getStorage('media')->loadMultiple($ids);
    if (!empty($files)) {
      foreach ($files as $file) {
        $filenames[$file->getName()] = $file->getName();
      }
    }

    return $filenames;
  }

  /**
   * Finds the media bundle associated with a file extension.
   *
   * @param string $ext
   *   The file extension.
   * @param array $validators
   *   The file validators.
   *
   * @return array
   *   An array of media bundle names.
   */
  public function findBundleByExtension($ext, array $validators) : array {
    $bundles = [];
    $ext = ltrim($ext, '.');
    foreach ($validators as $bundle => $values) {
      if (!empty($values['FileExtension']['extensions'])) {
        $allowed_extensions = explode(' ', $values['FileExtension']['extensions']);
        if (in_array($ext, $allowed_extensions)) {
          $bundles[$bundle] = $bundle;
        }
      }
    }

    if (count($bundles) > 1) {
      $bundles_settings = $this->configFactory->get('media_folders.settings')->get('bundles');
      $default_bundles = [];
      foreach ($bundles_settings as $values) {
        $default_bundles[$values['extension']] = $values['bundle'];
      }
      if (!empty($default_bundles) && !empty($default_bundles[$ext]) && !empty($bundles[$default_bundles[$ext]])) {
        $bundles = [$default_bundles[$ext] => $default_bundles[$ext]];
      }
    }

    return $bundles;
  }

  /**
   * Renames a file to avoid name conflicts.
   *
   * @param string &$filename
   *   The file name to rename.
   * @param array $filenames
   *   An array of existing file names.
   */
  public function fileNameRename(&$filename, array $filenames) : void {
    if (in_array($filename, $filenames)) {
      $pos = strrpos($filename, '.');
      if ($pos !== FALSE) {
        $name = substr($filename, 0, $pos);
        $ext = substr($filename, $pos);
      }
      else {
        $name = $filename;
        $ext = '';
      }

      $counter = 1;
      do {
        $filename = $name . ' (' . $counter++ . ')' . $ext;
      } while (in_array($filename, $filenames));
    }
  }

  /**
   * Creates a media entity for a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param mixed $folder
   *   The folder entity.
   * @param string $bundle
   *   The media bundle.
   * @param string $media_file_name
   *   The name of the media file.
   * @param string|null $alt
   *   The alt of the media file.
   * @param string|null $title
   *   The title of the media file.
   *
   * @return \Drupal\media\MediaInterface
   *   The created media entity.
   */
  public function createMedia(FileInterface $file, $folder, $bundle, $media_file_name, $alt = NULL, $title = NULL) : MediaInterface {
    $field = MediaFoldersUiBuilder::getFolderEntitiesFileField($bundle);
    $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $bundle . '.' . $field);
    $field_settings = $field_config->getSettings();

    $destination = $this->token->replace($field_settings['uri_scheme'] . '://' . $field_settings['file_directory']);
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
    $file->status->value = 1;
    $file->save();
    $this->fileRepository->move($file, $destination, FileExists::Rename);
    $file_values = [
      'bundle' => $bundle,
      'name' => $media_file_name,
      $field => [
        'target_id' => $file->id(),
      ],
      'field_folders_folder' => [
        'target_id' => ($folder) ? $folder->id() : NULL,
      ],
      'status' => 1,
    ];

    if (!empty($alt)) {
      $file_values[$field]['alt'] = $alt;
    }

    if (!empty($title)) {
      $file_values[$field]['title'] = $title;
    }

    $folder_file = $this->entityTypeManager->getStorage('media')->create($file_values);
    $folder_file->save();

    return $folder_file;
  }

  /**
   * Sanitizes a file name according to Drupal's file name sanitization rules.
   *
   * @param string $filename
   *   The file name to sanitize.
   *
   * @return string
   *   The sanitized file name.
   */
  public function sanitizeFilename($filename) : string {
    $fileSettings = $this->configFactory->get('file.settings');
    $transliterate = $fileSettings->get('filename_sanitization.transliterate');
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if ($extension !== '') {
      $extension = '.' . $extension;
      $filename = pathinfo($filename, PATHINFO_FILENAME);
    }

    $alphanumeric = $fileSettings->get('filename_sanitization.replace_non_alphanumeric');
    $replacement = $fileSettings->get('filename_sanitization.replacement_character');
    if ($transliterate) {
      $transliterated_filename = $this->transliteration->transliterate(
        $filename,
        $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId(),
        $replacement
      );
      if (mb_strlen($transliterated_filename) > 0) {
        $filename = $transliterated_filename;
      }
      else {
        $alphanumeric = TRUE;
      }
    }
    if ($fileSettings->get('filename_sanitization.replace_whitespace')) {
      $filename = preg_replace('/\s/u', $replacement, trim($filename));
    }
    if ($transliterate && $alphanumeric) {
      $filename = preg_replace('/[^0-9A-Za-z_.-]/u', $replacement, $filename);
    }
    if ($fileSettings->get('filename_sanitization.deduplicate_separators')) {
      $filename = preg_replace('/(_)_+|(\.)\.+|(-)-+/u', $replacement, $filename);
      $filename = preg_replace('/(_|\.|\-)[(_|\.|\-)]+/u', $replacement, $filename);
      $filename = preg_replace('/' . preg_quote($replacement, NULL) . '[' . preg_quote($replacement, NULL) . ']*/u', $replacement, $filename);
      $filename = rtrim($filename, $replacement);

      if (!empty($extension)) {
        $filename = rtrim($filename, '.');
      }
    }
    if ($fileSettings->get('filename_sanitization.lowercase')) {
      $filename = mb_strtolower($filename);
    }

    return $filename . $extension;
  }

  /**
   * Loads a taxonomy term.
   */
  private function loadFolder($folder_id) {
    return $this->entityTypeManager->getStorage('taxonomy_term')->load($folder_id);
  }

  /**
   * Gets all sub-folders.
   */
  private function getChildren($folder) {
    return $this->entityTypeManager->getStorage('taxonomy_term')->getChildren($folder);
  }

  /**
   * Loads folders tree.
   */
  private function loadFolderTree() {
    return $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('media_folders_folder', 0, 1, TRUE);
  }

}
