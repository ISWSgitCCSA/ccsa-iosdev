<?php

namespace Drupal\media_folders\Controller;

use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\media_folders\MediaFoldersUiBuilder;
use Drupal\media_folders\MediaFoldersUiActions;

/**
 * Controller for managing media folders.
 *
 * This controller provides methods for displaying and interacting with
 * media folders, including file explorers, AJAX responses, and folder actions.
 */
class MediaFoldersController extends ControllerBase {

  /**
   * The folders UI builder service.
   *
   * @var \Drupal\media_folders\MediaFoldersUiBuilder
   */
  protected $foldersUi;

  /**
   * The folders UI actions service.
   *
   * @var \Drupal\media_folders\MediaFoldersUiActions
   */
  protected $foldersActionsUi;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_folders.ui_builder'),
      $container->get('media_folders.ui_actions'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    MediaFoldersUiBuilder $media_folders_ui,
    MediaFoldersUiActions $media_folders_actions_ui,
    RequestStack $request_stack,
  ) {
    $this->foldersUi = $media_folders_ui;
    $this->foldersActionsUi = $media_folders_actions_ui;
    $this->requestStack = $request_stack;
  }

  /**
   * Checks if the user has permission to create media in a folder.
   *
   * @param mixed $folder
   *   The folder entity.
   * @param string|null $bundle
   *   (Optional) The media bundle.
   * @param bool $bool
   *   (Optional) Whether to return a boolean.
   *
   * @return bool
   *   TRUE if the user has permission, FALSE otherwise.
   */
  public function hasMediaCreateAccess($folder, $bundle = NULL, $bool = FALSE) : bool|AccessResult {
    return $this->foldersUi->hasMediaCreateAccess($folder);
  }

  /**
   * Checks if the user has permission to create media in a folder.
   *
   * @return bool
   *   TRUE if the user has permission, FALSE otherwise.
   */
  public function canEditMedia() : bool|AccessResult {
    return $this->foldersUi->canEditMedia();
  }

  /**
   * Checks if the user has permission to create terms.
   *
   * @return bool
   *   TRUE if the user has permission, FALSE otherwise.
   */
  public function hasTermCreatePermission() : bool|AccessResult {
    return $this->foldersUi->hasTermPermission('create', FALSE);
  }

  /**
   * Checks if the user has permission to edit terms.
   *
   * @return bool
   *   TRUE if the user has permission, FALSE otherwise.
   */
  public function hasTermEditPermission() : bool|AccessResult {
    return $this->foldersUi->hasTermPermission('edit', FALSE);
  }

  /**
   * Checks if the user has permission to delete terms.
   *
   * @return bool
   *   TRUE if the user has permission, FALSE otherwise.
   */
  public function hasTermDeletePermission() : bool|AccessResult {
    return $this->foldersUi->hasTermPermission('delete', FALSE);
  }

  /**
   * Gets the title for a folder.
   *
   * @param mixed $folder
   *   (Optional) The folder entity.
   *
   * @return string
   *   The folder title.
   */
  public function getTitle(mixed $folder = NULL) : string {
    return $this->foldersUi->getTitle($folder);
  }

  /**
   * Displays the media folder file explorer.
   *
   * @param mixed $folder
   *   (Optional) The folder entity.
   *
   * @return array
   *   A render array for the file explorer.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown if the folder is invalid.
   */
  public function fileExplorer($folder = NULL) : array {
    if ($folder && ($folder->bundle() !== 'media_folders_folder')) {
      throw new NotFoundHttpException();
    }

    return $this->foldersUi->buildAdminUi($folder);
  }

  /**
   * Displays the media folder file explorer via AJAX.
   *
   * @param mixed $folder
   *   (Optional) The folder entity.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A JSON response for the file explorer.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown if the folder is invalid.
   */
  public function fileExplorerAjax($folder = NULL) : CacheableJsonResponse {
    if ($folder && ($folder->bundle() !== 'media_folders_folder')) {
      throw new NotFoundHttpException();
    }

    $build = [];
    $output = $this->foldersUi->buildAjaxResponse($folder, $build);
    $response = new CacheableJsonResponse($output, 200);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    if ($folder) {
      $response->addCacheableDependency($folder);
    }

    return $response;
  }

  /**
   * Displays the media folder search results via AJAX.
   *
   * @param mixed $folder
   *   (Optional) The folder entity.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A JSON response for the search results.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown if the folder is invalid.
   */
  public function fileExplorerSearchAjax($folder = NULL) : CacheableJsonResponse {
    $folder = $this->entityTypeManager()->getStorage('taxonomy_term')->load($folder);
    if ($folder && ($folder->bundle() !== 'media_folders_folder')) {
      throw new NotFoundHttpException();
    }

    $build = [];
    $request = $this->requestStack->getCurrentRequest()->request->all();
    $output = $this->foldersUi->buildAjaxSearchResponse($folder, $request, $build);
    $response = new CacheableJsonResponse($output, 200);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    if ($folder) {
      $response->addCacheableDependency($folder);
    }

    return $response;
  }

  /**
   * Displays the media folder search pages via AJAX.
   *
   * @param mixed $folder
   *   (Optional) The folder entity.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A JSON response for the search results.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown if the folder is invalid.
   */
  public function fileExplorerLoadMoreAjax($folder = NULL) : CacheableJsonResponse {
    $folder = $this->entityTypeManager()->getStorage('taxonomy_term')->load($folder);
    if ($folder && ($folder->bundle() !== 'media_folders_folder')) {
      throw new NotFoundHttpException();
    }

    $build = [];
    $request = $this->requestStack->getCurrentRequest()->request->all();
    $output = $this->foldersUi->buildLoadMoreResponse($folder, $request, $build);
    $response = new CacheableJsonResponse($output, 200);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    if ($folder) {
      $response->addCacheableDependency($folder);
    }

    return $response;
  }

  /**
   * Displays the media folder file explorer widget via AJAX.
   *
   * This method builds and returns an AJAX response for the media folder
   * file explorer widget. It supports filtering by folder, entity type,
   * bundle, and widget ID.
   *
   * @param mixed $folder
   *   (Optional) The folder entity.
   * @param string|null $entity_type_id
   *   (Optional) The entity type ID for filtering.
   * @param string|null $bundle
   *   (Optional) The bundle for filtering.
   * @param string|null $widget_id
   *   (Optional) The widget ID for filtering.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A JSON response for the file explorer widget.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown if the folder is invalid.
   */
  public function fileExplorerWidgetAjax($folder = NULL, $entity_type_id = NULL, $bundle = NULL, $widget_id = NULL) : CacheableJsonResponse {
    if ($folder && ($folder->bundle() !== 'media_folders_folder')) {
      throw new NotFoundHttpException();
    }

    $build = [];
    $request = $this->requestStack->getCurrentRequest()->request->all();
    $output = $this->foldersUi->buildWidgetAjaxResponse($folder, $request, $entity_type_id, $bundle, $widget_id, $build);
    $response = new CacheableJsonResponse($output, 200);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    if ($folder) {
      $response->addCacheableDependency($folder);
    }

    return $response;
  }

  /**
   * Displays the CKEditor file explorer.
   *
   * @return array
   *   A render array for the CKEditor file explorer.
   */
  public function fileExplorerEditor() : array {
    return $this->foldersUi->buildEditorUi();
  }

  /**
   * Displays the CKEditor file explorer via AJAX.
   *
   * @param mixed $folder
   *   (Optional) The folder entity.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A JSON response for the search results.
   */
  public function fileExplorerEditorAjax($folder = NULL) : CacheableJsonResponse {
    if ($folder && ($folder->bundle() !== 'media_folders_folder')) {
      throw new NotFoundHttpException();
    }

    $build = [];
    $request = $this->requestStack->getCurrentRequest()->request->all();
    $output = $this->foldersUi->buildEditorAjaxResponse($folder, $request, $build);
    $response = new CacheableJsonResponse($output, 200);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    if ($folder) {
      $response->addCacheableDependency($folder);
    }

    return $response;
  }

  /**
   * Handles AJAX file uploads to a folder.
   *
   * @param mixed $folder
   *   The folder entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response for the file upload.
   */
  public function ajaxUploadFile($folder) : JsonResponse {
    $request = $this->requestStack->getCurrentRequest()->request->all();
    $response = $this->foldersActionsUi->ajaxUploadFile($folder, $request);

    return new JsonResponse($response);
  }

  /**
   * Handles AJAX requests to move files into a folder.
   *
   * @param mixed $folder
   *   The folder entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response for the move operation.
   */
  public function ajaxMoveInto($folder) : JsonResponse {
    $request = $this->requestStack->getCurrentRequest()->request->all();
    $response = $this->foldersActionsUi->ajaxMoveInto($folder, $request);

    return new JsonResponse($response);
  }

  /**
   * Displays media details via AJAX.
   *
   * @param mixed $media
   *   The media entity.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A JSON response for the media details.
   */
  public function ajaxMediaDetail($media) : CacheableJsonResponse {
    $build = [
      '#cache' => [
        'tags' => ['media:' . $media->id()],
      ],
    ];
    $output = $this->foldersUi->ajaxMediaDetail($media);

    $response = new CacheableJsonResponse($output, 200);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    if ($media) {
      $response->addCacheableDependency($media);
    }

    return $response;
  }

}
