<?php

namespace Drupal\media_folders;

use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\media_library\MediaLibraryState;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Service which builds the file explorer widget.
 *
 * This service provides methods to build and render the UI for media folders,
 * including file explorers, AJAX responses, and widget-based interfaces.
 */
class MediaFoldersUiBuilder {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The media folder file settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $mediaFoldersSettings;

  /**
   * File url generator object.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Form builder will be used via Dependency Injection.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The default cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The page limit.
   *
   * @var int
   */
  protected $pageLimit;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    FileUrlGeneratorInterface $fileUrlGenerator,
    RendererInterface $renderer,
    RedirectDestinationInterface $redirect_destination,
    FormBuilderInterface $form_builder,
    RouteMatchInterface $route_match,
    CacheBackendInterface $cache,
    EntityTypeBundleInfoInterface $bundle_info,
    EntityFieldManagerInterface $field_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->mediaFoldersSettings = $config_factory->get('media_folders.settings');
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->renderer = $renderer;
    $this->redirectDestination = $redirect_destination;
    $this->formBuilder = $form_builder;
    $this->routeMatch = $route_match;
    $this->cache = $cache;
    $this->bundleInfo = $bundle_info;
    $this->fieldManager = $field_manager;
    $this->pageLimit = !empty($this->mediaFoldersSettings->get('pager_limit')) ? $this->mediaFoldersSettings->get('pager_limit') : 500;
  }

  /**
   * {@inheritdoc}
   */
  public static function dialogOptions() : array {
    return [
      'classes' => [
        'ui-dialog' => 'media-folders-widget-modal',
      ],
      'title' => t('Add or select media'),
      'height' => '75%',
      'width' => '85%',
    ];
  }

  /**
   * Checks if the user has permission to create media in a folder.
   *
   * This method verifies whether the current user has the necessary permissions
   * to create media entities in a specified folder. It checks for general media
   * creation permissions, as well as permissions specific to media bundles.
   *
   * @param mixed $folder
   *   The folder entity. Can be NULL if no specific folder is being checked.
   * @param string|null $bundle
   *   (Optional) The media bundle to check permissions for.
   * @param bool $bool
   *   (Optional) If TRUE, returns a boolean value. If FALSE, returns an
   *   \Drupal\Core\Access\AccessResult object.
   *
   * @return bool|\Drupal\Core\Access\AccessResult
   *   Returns TRUE or an AccessResult object if the user has permission,
   *   otherwise FALSE or an AccessResult object indicating forbidden access.
   */
  public function hasMediaCreateAccess($folder, $bundle = NULL, $bool = FALSE) : bool|AccessResult {
    if ($this->currentUser->hasPermission('administer media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($this->currentUser->hasPermission('create media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($bundle && $this->currentUser->hasPermission('create ' . $bundle . ' media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if (!$bundle) {
      $bundles = $this->bundleInfo->getBundleInfo('media');
      foreach (array_keys($bundles) as $bundle_id) {
        if ($this->currentUser->hasPermission('create ' . $bundle_id . ' media')) {
          return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
        }
      }
    }

    return ($bool) ? FALSE : AccessResult::forbidden()->cachePerPermissions();
  }

  /**
   * Checks if the user has permission to edit a media entity.
   *
   * This method verifies whether the current user has the necessary permissions
   * to edit a specified media entity. It checks for general media editing
   * permissions, as well as permissions specific to media bundles and owner.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to check permissions for.
   * @param bool $bool
   *   (Optional) If TRUE, returns a boolean value. If FALSE, returns an
   *   \Drupal\Core\Access\AccessResult object.
   *
   * @return bool|\Drupal\Core\Access\AccessResult
   *   Returns TRUE or an AccessResult object if the user has permission,
   *   otherwise FALSE or an AccessResult object indicating forbidden access.
   */
  public function hasMediaEditAccess(MediaInterface $media, $bool = FALSE) : bool|AccessResult {
    if ($this->currentUser->hasPermission('administer media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($this->currentUser->hasPermission('update any media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($media->uid->target_id == $this->currentUser->id() && $this->currentUser->hasPermission('update media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($this->currentUser->hasPermission('edit any ' . $media->bundle() . ' media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($media->uid->target_id == $this->currentUser->id() && $this->currentUser->hasPermission('edit own ' . $media->bundle() . ' media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    return ($bool) ? FALSE : AccessResult::forbidden()->cachePerPermissions();
  }

  /**
   * Checks if the user has permission to edit a media entity.
   *
   * This method verifies whether the current user has the necessary permissions
   * to edit a specified media entity. It checks for general media editing
   * permissions, as well as permissions specific to media bundles and owner.
   *
   * @param bool $bool
   *   (Optional) If TRUE, returns a boolean value. If FALSE, returns an
   *   \Drupal\Core\Access\AccessResult object.
   *
   * @return bool|\Drupal\Core\Access\AccessResult
   *   Returns TRUE or an AccessResult object if the user has permission,
   *   otherwise FALSE or an AccessResult object indicating forbidden access.
   */
  public function canEditMedia($bool = FALSE) : bool|AccessResult {
    if ($this->currentUser->hasPermission('administer media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($this->currentUser->hasPermission('update any media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($this->currentUser->hasPermission('update media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    $bundles = $this->bundleInfo->getBundleInfo('media');
    foreach (array_keys($bundles) as $bundle_id) {
      if ($this->currentUser->hasPermission('edit ' . $bundle_id . ' media')) {
        return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
      }
      if ($this->currentUser->hasPermission('edit any ' . $bundle_id . ' media')) {
        return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
      }
      if ($this->currentUser->hasPermission('edit own ' . $bundle_id . ' media')) {
        return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
      }
    }

    return ($bool) ? FALSE : AccessResult::forbidden()->cachePerPermissions();
  }

  /**
   * Checks if the user has permission to delete a media entity.
   *
   * This method verifies whether the current user has the necessary permissions
   * to delete a specified media entity. It checks for general media deleting
   * permissions, as well as permissions specific to media bundles and owner.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to check permissions for.
   * @param bool $bool
   *   (Optional) If TRUE, returns a boolean value. If FALSE, returns an
   *   \Drupal\Core\Access\AccessResult object.
   *
   * @return bool|\Drupal\Core\Access\AccessResult
   *   Returns TRUE or an AccessResult object if the user has permission,
   *   otherwise FALSE or an AccessResult object indicating forbidden access.
   */
  public function hasMediaDeleteAccess(MediaInterface $media, $bool = FALSE) : bool|AccessResult {
    if ($this->currentUser->hasPermission('administer media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($this->currentUser->hasPermission('delete any media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($media->uid->target_id == $this->currentUser->id() && $this->currentUser->hasPermission('delete media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($this->currentUser->hasPermission('delete any ' . $media->bundle() . ' media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    if ($media->uid->target_id == $this->currentUser->id() && $this->currentUser->hasPermission('delete own ' . $media->bundle() . ' media')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    return ($bool) ? FALSE : AccessResult::forbidden()->cachePerPermissions();
  }

  /**
   * Retrieves the title for the media folders page or a specific folder.
   *
   * This method generates a title for the media folders page. If a specific
   * folder is provided, the title includes the folder's name.
   *
   * @param mixed $folder
   *   (Optional) The folder entity. If NULL, the title will be for the root
   *   media folders page.
   *
   * @return string
   *   The generated title for the media folders page or folder.
   */
  public function getTitle(mixed $folder = NULL) : string {
    if (!$folder) {
      return $this->t('Media Folders');
    }
    $element = ($folder && ($folder->bundle() == 'media_folders_folder')) ? $folder->getName() : '';

    return $this->t('Media Folders | @element', ['@element' => $element]);
  }

  /**
   * Builds the admin UI for media folders.
   *
   * This method generates the render array for the media folders admin page,
   * including folder navigation, file listings, and available actions.
   *
   * @param mixed $folder
   *   (Optional) The folder entity. If NULL, the UI will be built for the root
   *   media folders page.
   *
   * @return array
   *   A render array for the media folders admin page.
   */
  public function buildAdminUi($folder) : array {
    $folder_id = ($folder) ? $folder->id() : 0;
    $view_type = $this->getCookie('view-type', $this->mediaFoldersSettings->get('default_view'));
    $cache_tags = [
      'media_folders_page',
      'taxonomy_term:' . $folder_id,
    ];

    $folders = $this->getFolderFolders($folder_id, $view_type, $cache_tags);
    $folders_ordered = $this->itemsOrder($folders);
    $this->getFolderFiles($folder, $folders_ordered, $cache_tags);
    $this->addTableHeader($folders_ordered);
    $navbar = $this->geAllUserFoldersTree($folder_id);
    $actions = $this->getFolderActions($folder);
    $board_class = (!$this->hasMediaCreateAccess(NULL, NULL, TRUE)) ? 'not-droppable' : '';

    return [
      '#theme' => 'media_folders_page',
      '#title' => $this->t('Media folders'),
      '#up_button' => $this->getUpButton($folder),
      '#breadcrumb' => $this->getBreadcrumb($folder),
      '#actions' => $actions,
      '#actions_raw' => !empty($actions) ? $this->getRawActions($actions) : NULL,
      '#buttons' => Markup::create($this->getButtons()),
      '#form' => Markup::create($this->getForm()),
      '#folders' => [
        '#theme' => 'media_folders_folders',
        '#folders' => $folders_ordered,
      ],
      '#navbar' => [
        '#theme' => 'media_folders_navbar',
        '#folders' => $navbar,
      ],
      '#class' => $view_type,
      '#board_class' => $board_class,
      '#fid' => $folder_id,
      '#attached' => [
        'library' => 'media_folders/folders',
      ],
      '#cache' => [
        'tags' => $cache_tags,
        'contexts' => ['user.roles'],
      ],
    ];
  }

  /**
   * Builds an AJAX response for the media folders admin UI.
   *
   * This method generates the response for an AJAX request to update the media
   * folders admin UI. Includes folder navigation, file listings, and actions.
   *
   * @param mixed $folder
   *   (Optional) The folder entity. If NULL, the response will be built for the
   *   root media folders page.
   * @param array &$build
   *   The build array to which cache metadata will be added.
   *
   * @return array
   *   An array representing the AJAX response, including rendered folders,
   *   navigation, breadcrumb, and actions.
   */
  public function buildAjaxResponse($folder, array &$build) : array {
    $folder_id = ($folder) ? $folder->id() : 0;
    $view_type = $this->getCookie('view-type', $this->mediaFoldersSettings->get('default_view'));
    $cache_tags = ['taxonomy_term:' . $folder_id];

    $folders = $this->getFolderFolders($folder_id, $view_type, $cache_tags);
    $folders_ordered = $this->itemsOrder($folders);
    $this->getFolderFiles($folder, $folders_ordered, $cache_tags);
    $this->addTableHeader($folders_ordered);
    $actions = $this->getFolderActions($folder);
    $actions_raw = (!empty($actions)) ? $this->getRawActions($actions) : NULL;
    $up = $this->getUpButton($folder);
    $folders = [
      '#theme' => 'media_folders_folders',
      '#folders' => $folders_ordered,
    ];
    $navbar = [
      '#theme' => 'media_folders_navbar',
      '#folders' => $this->geAllUserFoldersTree($folder_id),
    ];
    $board_class = (!$this->hasMediaCreateAccess(NULL, NULL, TRUE)) ? 'not-droppable' : '';

    $build['#cache'] = [
      'tags' => $cache_tags,
      'contexts' => ['user.roles'],
    ];

    return [
      'navbar' => '<div class="navbar-header">' . $this->t('Folders') . '</div>' . $this->renderer->render($navbar),
      'folders' => $this->renderer->render($folders) . '<div class="droppable-message">' . $this->t('Drop your file here!') . '</div>',
      'breadcrumb' => $this->getBreadcrumb($folder),
      'actions' => !empty($actions) ? $this->renderer->render($actions) : '',
      'actions_raw' => ($actions_raw) ? '<span class="close"></span>' . $this->renderer->render($actions_raw) : '',
      'buttons' => $this->getButtons(),
      'up_button' => (!$folder) ? $up : $this->renderer->render($up),
      'title' => $this->getTitle($folder),
      'board_class' => $board_class,
      'fid' => $folder_id,
    ];
  }

  /**
   * Builds an AJAX response for the media folders search results.
   *
   * This method generates the response for an AJAX request to display search
   * results in the media folders admin UI. It includes folder navigation,
   * file listings, and breadcrumb updates based on the search query.
   *
   * @param mixed $folder
   *   (Optional) The folder. If NULL, the search is performed in the root
   *   media folders.
   * @param array $request
   *   The request data containing the search query.
   * @param array &$build
   *   The build array to which cache metadata will be added.
   *
   * @return array
   *   An array representing the AJAX response, including rendered folders,
   *   breadcrumb, and navigation.
   */
  public function buildAjaxSearchResponse($folder, array $request, array &$build) : array {
    $folder_id = ($folder) ? $folder->id() : 0;
    $search = $request['request'];
    $opener_parameters = (!empty($request['stateParameters'])) ? $request['stateParameters'] : NULL;
    $cache_tags = ['taxonomy_term:' . $folder_id];
    $target_bundles = NULL;
    if ($opener_parameters) {
      $bundle_fields = $this->fieldManager->getFieldDefinitions($opener_parameters['entity_type_id'], $opener_parameters['bundle']);
      $settings = $bundle_fields[$opener_parameters['field_name']]->getSettings();
      $target_bundles = array_values($settings['handler_settings']['target_bundles']);
    }
    $folders = $this->getSearchFolderFolders($folder_id, $search, $cache_tags);
    $folders_ordered = $this->itemsOrder($folders);
    $this->getSearchFolderFiles($folder, $search, $folders_ordered, $cache_tags, $target_bundles);
    $this->addSearchTableHeader($folders_ordered);
    $folders = [
      '#theme' => 'media_folders_folders',
      '#folders' => $folders_ordered,
    ];
    $board_class = 'not-droppable search-results';
    $up = $this->getUpButton($folder, TRUE);
    $breadcrumb = $this->getBreadcrumb($folder, $search);

    $build['#cache'] = [
      'tags' => $cache_tags,
    ];

    return [
      'folders' => $this->renderer->render($folders),
      'board_class' => $board_class,
      'up_button' => $this->renderer->render($up),
      'breadcrumb' => $breadcrumb,

    ];
  }

  /**
   * Builds an AJAX response for the media folders pager results.
   *
   * This method generates the response for an AJAX request to display pager
   * results in the media folders admin UI. It includes folder navigation,
   * file listings, and breadcrumb updates based on the search query.
   *
   * @param mixed $folder
   *   (Optional) The folder. If NULL, the search is performed in the root
   *   media folders.
   * @param array $request
   *   The request data containing the search query.
   * @param array &$build
   *   The build array to which cache metadata will be added.
   *
   * @return array
   *   An array representing the AJAX response, including rendered folders,
   *   breadcrumb, and navigation.
   */
  public function buildLoadMoreResponse($folder, array $request, array &$build) : array {
    $folder_id = ($folder) ? $folder->id() : 0;
    $page = $request['page'] + 1;
    $widget_settings = $request['widgetSettings'];
    $opener_parameters = $request['stateParameters'];
    $view_order = ($widget_settings) ? $widget_settings['view_order'] : $this->getCookie('view-order', $this->mediaFoldersSettings->get('default_order'));
    $view_type = ($widget_settings) ? $widget_settings['view_type'] : $this->getCookie('view-type', $this->mediaFoldersSettings->get('default_view'));
    $target_bundles = NULL;
    if ($opener_parameters) {
      $bundle_fields = $this->fieldManager->getFieldDefinitions($opener_parameters['entity_type_id'], $opener_parameters['bundle']);
      $settings = $bundle_fields[$opener_parameters['field_name']]->getSettings();
      $target_bundles = array_values($settings['handler_settings']['target_bundles']);
    }
    $cache_tags = ['taxonomy_term:' . $folder_id];
    $folder_id = ($folder) ? $folder->id() : 0;
    $count = $this->getFileCount($folder_id, $target_bundles);

    $first = $page * $this->pageLimit;
    $values = $this->getFolderEntities($folder, $view_order, $target_bundles, $first);
    $files = $this->buildFolderFiles($values, $view_type, $cache_tags);
    $this->addLoadMore($folder_id, $files, ($first + count($values)), $count);

    foreach ($files as $key => $value) {
      $files[$key] = $this->renderer->render($value);
    }

    return [
      'files' => implode('', $files),
      'page' => $page,
    ];
  }

  /**
   * Builds the widget UI for media folders.
   *
   * This method generates the render array for the media folders widget UI,
   * including folder navigation, file listings, and available actions. It is
   * used in contexts where a widget-based interface is required, such as
   * embedding media folders in forms.
   *
   * @param \Drupal\media_library\MediaLibraryState|null $state
   *   (Optional) The state object containing widget settings and parameters.
   *   If NULL, the state will be derived from the current request.
   * @param int $last_folder
   *   (Optional) The last opened folder.
   *
   * @return array
   *   A render array for the media folders widget UI.
   */
  public function buildWidgetUi($state = NULL, $last_folder = 0) : array {
    if (!$state) {
      $state = MediaFoldersState::fromRequest($this->request);
    }

    $widget_settings = $state->getWidgetSettings();
    $opener_parameters = $state->getOpenerParameters();
    $folder_id = $last_folder;
    $folder = !empty($folder_id) ? $this->entityTypeManager->getStorage('taxonomy_term')->load($folder_id) : NULL;
    $view_type = $widget_settings['view_type'];
    $cache_tags = ['taxonomy_term:' . $folder_id];

    $folders = $this->getFolderFolders($folder_id, $view_type, $cache_tags, TRUE, $opener_parameters);
    $folders_ordered = $this->itemsOrder($folders, $widget_settings);
    $this->getWidgetFolderFiles($folder, $folders_ordered, $opener_parameters, $widget_settings);
    $this->addTableHeader($folders_ordered);
    $navbar = $this->geAllUserFoldersTree($folder_id);
    $board_class = (!$this->hasMediaCreateAccess($folder, NULL, TRUE)) ? 'not-droppable' : '';
    $actions = [
      $this->getUploadAction($folder, $opener_parameters),
      $this->getNewFolderAction($folder, $opener_parameters),
    ];

    $explorer = [
      '#theme' => 'media_folders_widget',
      '#up_button' => ($widget_settings['show_navigation']) ? $this->getUpButton(NULL) : NULL,
      '#breadcrumb' => ($widget_settings['show_navigation']) ? $this->getBreadcrumb(NULL) : NULL,
      '#form' => ($widget_settings['show_search']) ? Markup::create($this->getForm()) : NULL,
      '#upload_form' => $actions,
      '#folders' => [
        '#theme' => 'media_folders_folders',
        '#folders' => $folders_ordered,
      ],
      '#navbar' => [
        '#theme' => 'media_folders_navbar',
        '#folders' => $navbar,
      ],
      '#class' => 'widget ' . $view_type,
      '#board_class' => $board_class,
      '#fid' => $folder_id,
      '#cache' => [
        'tags' => [
          'taxonomy_term:' . $folder_id,
        ],
      ],
      '#attached' => [
        'drupalSettings' => [
          'media_folders' => [
            'selection_remaining' => $state->getAvailableSlots(),
            'state_parameters' => $opener_parameters,
            'widget_settings' => $widget_settings,
          ],
        ],
      ],
    ];

    return $explorer;
  }

  /**
   * Builds an AJAX response for the media folders widget.
   *
   * This method generates the response for an AJAX request to update the media
   * folders widget UI. Includes folder navigation, file listings, and actions
   * specific to the widget context.
   *
   * @param mixed $folder
   *   (Optional) The folder entity. If NULL, the response will be built for the
   *   root media folders.
   * @param array $request
   *   The request data containing widget settings and parameters.
   * @param string $entity_type_id
   *   The entity type ID for the widget.
   * @param string $bundle
   *   The bundle associated with the widget.
   * @param string $widget_id
   *   The widget ID.
   * @param array $build
   *   The build array to which cache metadata will be added.
   *
   * @return array
   *   An array representing the AJAX response, including rendered folders,
   *   navigation, breadcrumb, and actions.
   */
  public function buildWidgetAjaxResponse($folder, array $request, $entity_type_id, $bundle, $widget_id, array $build) : array {
    $folder_id = ($folder) ? $folder->id() : 0;
    $widget_settings = $request['request'];
    $view_type = $widget_settings['view_type'];
    $opener_parameters = [
      'entity_type_id' => $entity_type_id,
      'bundle' => $bundle,
      'field_name' => $widget_id,
    ];
    $cache_tags = ['taxonomy_term:' . $folder_id];

    $folders = $this->getFolderFolders($folder_id, $view_type, $cache_tags, TRUE, $opener_parameters);
    $folders_ordered = $this->itemsOrder($folders, $widget_settings);
    $this->getWidgetFolderFiles($folder, $folders_ordered, $opener_parameters, $widget_settings);
    $this->addTableHeader($folders_ordered);
    $folders = [
      '#theme' => 'media_folders_folders',
      '#folders' => $folders_ordered,
    ];
    $navbar = [
      '#theme' => 'media_folders_navbar',
      '#folders' => $this->geAllUserFoldersTree($folder_id),
    ];
    $board_class = (!$this->hasMediaCreateAccess(NULL, NULL, TRUE)) ? 'not-droppable' : '';
    $up = $this->getUpButton($folder);
    if ($folder) {
      $up = $this->renderer->render($up);
    }
    $actions = [
      $this->getUploadAction($folder, $opener_parameters),
      $this->getNewFolderAction($folder, $opener_parameters),
    ];

    $build['#cache'] = [
      'tags' => $cache_tags,
    ];

    return [
      'navbar' => '<div class="navbar-header">' . $this->t('Folders') . '</div>' . $this->renderer->render($navbar),
      'folders' => $this->renderer->render($folders) . '<div class="droppable-message">' . $this->t('Drop your file here!') . '</div>',
      'up_button' => ($widget_settings['show_navigation']) ? $up : NULL,
      'breadcrumb' => ($widget_settings['show_navigation']) ? $this->getBreadcrumb($folder) : NULL,
      'form' => ($widget_settings['show_search']) ? Markup::create($this->getForm()) : NULL,
      'upload_form' => $this->renderer->render($actions),
      'title' => $this->getTitle($folder),
      'board_class' => $board_class,
      'fid' => $folder_id,
    ];
  }

  /**
   * Builds the editor UI for media folders.
   *
   * @return array
   *   A render array for the editor UI.
   */
  public function buildEditorUi() : array {
    $state = MediaLibraryState::fromRequest($this->request);

    $widget_settings = [
      'view_type' => 'list',
      'view_order' => 'date-desc',
    ];
    $folder_id = 0;
    $view_type = $widget_settings['view_type'];
    $cache_tags = ['taxonomy_term:' . $folder_id];

    $folders = $this->getFolderFolders($folder_id, $view_type, $cache_tags, FALSE);
    $folders_ordered = $this->itemsOrder($folders, $widget_settings);
    $this->getEditorFolderFiles(NULL, $folders_ordered, $state->getAllowedTypeIds(), $widget_settings);
    $this->addTableHeader($folders_ordered);
    $navbar = $this->geAllUserFoldersTree($folder_id);
    $board_class = 'not-droppable';

    $explorer = [
      '#theme' => 'media_folders_widget',
      '#up_button' => $this->getUpButton(NULL),
      '#breadcrumb' => $this->getBreadcrumb(NULL),
      '#form' => Markup::create($this->getForm()),
      '#folders' => [
        '#theme' => 'media_folders_folders',
        '#folders' => $folders_ordered,
      ],
      '#navbar' => [
        '#theme' => 'media_folders_navbar',
        '#folders' => $navbar,
      ],
      '#class' => 'ckeditor-widget ' . $view_type,
      '#board_class' => $board_class,
      '#fid' => $folder_id,
      '#cache' => [
        'tags' => [
          'taxonomy_term:' . $folder_id,
        ],
      ],
      '#attached' => [
        'library' => ['media_folders/ckeditor'],
        'drupalSettings' => [
          'media_folders' => [
            'selection_remaining' => $state->getAvailableSlots(),
            'allowed_types' => $state->getAllowedTypeIds(),
            'widget_settings' => $widget_settings,
          ],
        ],
      ],
    ];

    return $explorer;
  }

  /**
   * Builds an AJAX response for the editor UI.
   *
   * @param mixed $folder
   *   The folder entity.
   * @param array $request
   *   The request data.
   * @param array $build
   *   The build array.
   *
   * @return array
   *   An array representing the AJAX response.
   */
  public function buildEditorAjaxResponse($folder, array $request, array $build) : array {
    $folder_id = ($folder) ? $folder->id() : 0;
    $widget_settings = $request['request']['widgetSettings'];
    $allowed_types = $request['request']['allowedTypes'];
    $view_type = $widget_settings['view_type'];
    $cache_tags = ['taxonomy_term:' . $folder_id];

    $folders = $this->getFolderFolders($folder_id, $view_type, $cache_tags, FALSE);
    $folders_ordered = $this->itemsOrder($folders, $widget_settings);
    $this->getEditorFolderFiles($folder, $folders_ordered, $allowed_types, $widget_settings);
    $this->addTableHeader($folders_ordered);
    $folders = [
      '#theme' => 'media_folders_folders',
      '#folders' => $folders_ordered,
    ];
    $navbar = [
      '#theme' => 'media_folders_navbar',
      '#folders' => $this->geAllUserFoldersTree($folder_id),
    ];
    $board_class = 'not-droppable';
    $up = $this->getUpButton($folder);
    if ($folder) {
      $up = $this->renderer->render($up);
    }

    $build['#cache'] = [
      'tags' => $cache_tags,
    ];

    return [
      'navbar' => '<div class="navbar-header">' . $this->t('Folders') . '</div>' . $this->renderer->render($navbar),
      'folders' => $this->renderer->render($folders),
      'up_button' => $up,
      'breadcrumb' => $this->getBreadcrumb($folder),
      'form' => Markup::create($this->getForm()),
      'title' => $this->getTitle($folder),
      'board_class' => $board_class,
      'fid' => $folder_id,
    ];
  }

  /**
   * Builds an AJAX response for media details.
   *
   * Generates the response for an AJAX request to display the details
   * of a specific media entity. It renders the media entity using the
   * `media_library` view mode.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity for which details are being requested.
   *
   * @return array
   *   An array containing the rendered media details.
   */
  public function ajaxMediaDetail(MediaInterface $media) {
    $view_builder = $this->entityTypeManager->getViewBuilder('media');
    $preview = $view_builder->view($media, 'media_library');

    return [
      'render' => $this->renderer->render($preview),
    ];
  }

  /**
   * Orders items based on the specified widget settings.
   *
   * This method sorts an array of items (folders or files) according to the
   * specified view order in the widget settings. Supported sorting options
   * include alphabetic and date-based.
   *
   * @param array $items
   *   The array of items to be ordered. Each one should have properties such as
   *   `#title` or `#weight` for sorting.
   * @param array|null $widget_settings
   *   (Optional) The widget settings array containing the `view_order` key. If
   *   NULL, the default view order is retrieved from cookies or configuration.
   *
   * @return array
   *   The ordered array of items.
   */
  public function itemsOrder(array $items, $widget_settings = NULL) {
    $view_order = ($widget_settings) ? $widget_settings['view_order'] : $this->getCookie('view-order', $this->mediaFoldersSettings->get('default_order'));

    if ($view_order == 'za' || $view_order == 'az') {
      uasort($items, [SortArray::class, 'sortByTitleProperty']);
    }
    elseif ($view_order == 'date-asc' || $view_order == 'date-desc') {
      uasort($items, [SortArray::class, 'sortByWeightProperty']);
    }

    if ($view_order == 'za' || $view_order == 'date-desc') {
      return array_reverse($items);
    }

    return $items;
  }

  /**
   * Retrieves the value of a cookie.
   *
   * This method checks if a specific cookie exists and retrieves its value.
   * If the cookie does not exist, a default value is returned.
   *
   * @param string $name
   *   The name of the cookie to retrieve.
   * @param mixed $default
   *   The default value to return if the cookie does not exist.
   *
   * @return mixed
   *   The value of the cookie if it exists, or the default value otherwise.
   */
  private function getCookie($name, $default) : mixed {
    $cookies = $this->request->cookies;
    if ($cookies->has($name)) {
      return $cookies->get($name);
    }

    return $default;
  }

  /**
   * Builds a nested array structure for a folder tree.
   *
   * This method recursively builds a nested array structure representing a
   * folder tree. Each folder includes its children, links, and additional
   * metadata such as CSS classes.
   *
   * @param array &$build
   *   The array to which the folder tree structure will be added.
   * @param array $tree
   *   The array of folder terms to process.
   * @param int $folder_id
   *   The ID of the currently active folder.
   */
  public function treeNestedArray(array &$build, array $tree, $folder_id) : void {
    foreach ($tree as $term) {
      $key = strtolower($term->getName()) . '-' . $term->id();
      $link = Link::fromTextAndUrl($term->getName(), Url::fromRoute('media_folders.collection.folder', ['folder' => $term->id()]));

      $folder_class = ['navbar-folder'];
      if (!$this->hasMediaCreateAccess(NULL, NULL, TRUE)) {
        $folder_class[] = 'not-droppable';
      }

      $build[$key] = [
        '#title' => $term->getName(),
        '#weight' => $term->changed->value,
        'id' => $term->id(),
        'link' => $link,
        'class' => ($folder_id == $term->id()) ? 'active' : '',
        'folder_class' => implode(' ', $folder_class),
        'children' => [],
      ];
      $children = $this->entityTypeManager->getStorage('taxonomy_term')->getChildren($term);
      if (!empty($children)) {
        $build[$key]['class'] .= ' has-children';
        $this->treeNestedArray($build[$key]['children'], $children, $folder_id);
      }
    }
    $build = $this->itemsOrder($build);
  }

  /**
   * Marks the active trail in a nested folder tree.
   *
   * This method recursively traverses a nested folder tree and marks the active
   * trail by adding the `active-trail` CSS class to the relevant folders. The
   * active trail represents the path from the root folder to the currently
   * active folder.
   *
   * @param array &$build
   *   The nested folder tree array to be updated.
   * @param int $folder_id
   *   The ID of the currently active folder.
   * @param array|bool $paths
   *   (Optional) An array of paths representing the active trail. If FALSE, the
   *   paths will be calculated using `treeNestedArrayActiveTrailPaths()`.
   */
  public function treeNestedArrayActiveTrail(array &$build, $folder_id, $paths = FALSE) {
    if ($paths === FALSE) {
      $paths = $this->treeNestedArrayActiveTrailPaths($build, $folder_id);
    }

    if (!empty($paths)) {
      foreach ($paths as $path) {
        if (!empty($build[$path])) {
          $build[$path]['class'] .= ' active-trail';
          $this->treeNestedArrayActiveTrail($build[$path]['children'], $folder_id, $paths);
        }
      }
    }
  }

  /**
   * Retrieves the active trail paths in a nested folder tree.
   *
   * This method recursively traverses a nested folder tree to identify the
   * active trail paths leading to the specified folder. The active trail
   * represents the path from the root folder to the currently active folder.
   *
   * @param array $build
   *   The nested folder tree array to traverse.
   * @param int $folder_id
   *   The ID of the currently active folder.
   *
   * @return array
   *   An array of paths representing the active trail.
   */
  public function treeNestedArrayActiveTrailPaths(array $build, $folder_id) : array {
    $paths = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($build), \RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $v) {
      if (is_array($v) && !empty($v['id']) && $v['id'] == $folder_id) {
        for ($p = [], $i = 0, $z = $iterator->getDepth(); $i <= $z; $i++) {
          $p = $iterator->getSubIterator($i)->key();
          if ($p != 'children') {
            $paths[] = $p;
          }
        }
      }
    }

    return $paths;
  }

  /**
   * Retrieves the full folder tree for all user-accessible folders.
   *
   * This method generates a nested array structure representing the folder tree
   * for all user-accessible folders, starting from the root. It includes links,
   * metadata, and active trail markings for the currently active folder.
   *
   * @param int $folder_id
   *   The ID of the currently active folder.
   *
   * @return array
   *   A nested array structure representing the folder tree.
   */
  public function geAllUserFoldersTree($folder_id) : array {
    $folders = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('media_folders_folder', 0, 1, TRUE);
    $build = [];

    $folder_class = ['navbar-folder'];
    if (!$this->hasMediaCreateAccess(NULL, NULL, TRUE)) {
      $folder_class[] = 'not-droppable';
    }
    $classes = [
      'root',
      'active-trail',
      'has-children',
    ];
    if ($folder_id === 0) {
      $classes[] = 'active';
    }
    $build['root'] = [
      'id' => 0,
      'link' => Link::fromTextAndUrl($this->t('Root'), Url::fromRoute('media_folders.collection')),
      'class' => implode(' ', $classes),
      'folder_class' => implode(' ', $folder_class),
      'children' => [],
    ];

    $this->treeNestedArray($build['root']['children'], $folders, $folder_id);
    $this->treeNestedArrayActiveTrail($build, $folder_id);

    return $build;
  }

  /**
   * Retrieves and processes files for the media folders widget.
   *
   * This method retrieves files associated with a folder and processes them
   * into a format suitable for rendering in the media folders widget. It
   * includes metadata such as file size, type, and actions.
   *
   * @param mixed $folder
   *   The folder entity. If NULL, files from the root folder are retrieved.
   * @param array &$folders
   *   A reference to the folders array where the processed files will be added.
   * @param array $opener_parameters
   *   Parameters for the widget opener, including type, bundle, and name.
   * @param array $widget_settings
   *   The widget settings array, including view type and other configurations.
   */
  public function getWidgetFolderFiles($folder, array &$folders, array $opener_parameters, array $widget_settings) : void {
    $files = [];
    $folder_id = ($folder) ? $folder->id() : 0;
    $bundle_fields = $this->fieldManager->getFieldDefinitions($opener_parameters['entity_type_id'], $opener_parameters['bundle']);
    $settings = $bundle_fields[$opener_parameters['field_name']]->getSettings();
    $target_bundles = array_values($settings['handler_settings']['target_bundles']);
    $view_type = $widget_settings['view_type'];
    $count = $this->getFileCount($folder_id, $target_bundles);
    $values = $this->getFolderEntities($folder, $widget_settings['view_order'], $target_bundles);
    $cache_tags = [];
    foreach ($values as $file_entity) {
      $field = static::getFolderEntitiesFileField($file_entity->bundle());
      if (!empty($file_entity->$field->entity)) {
        $this->buildEntitiesFileField($files, $file_entity, $field, $view_type, TRUE, FALSE, $cache_tags, $widget_settings);
      }
      else {
        $this->buildEntitiesLinkField($files, $file_entity, $view_type, TRUE, FALSE, $cache_tags, $widget_settings);
      }
    }

    $folders = array_merge($folders, $files);
    $this->addLoadMore($folder_id, $folders, count($values), $count);
  }

  /**
   * Retrieves and processes files for the media folders editor UI.
   *
   * This method retrieves files associated with a folder and processes them
   * into a format suitable for rendering in the media folders editor UI. It
   * includes metadata such as file size, type, and thumbnail images.
   *
   * @param mixed $folder
   *   The folder entity. If NULL, files from the root folder are retrieved.
   * @param array &$folders
   *   A reference to the folders array where the processed files will be added.
   * @param array $target_bundles
   *   An array of target media bundles to filter the files.
   * @param array $widget_settings
   *   The widget settings array, including view type and other configurations.
   */
  public function getEditorFolderFiles($folder, array &$folders, array $target_bundles, array $widget_settings) :void {
    $files = [];
    $folder_id = ($folder) ? $folder->id() : 0;
    $target_bundles = array_values($target_bundles);
    $count = $this->getFileCount($folder_id, $target_bundles);
    $view_type = $widget_settings['view_type'];
    $values = $this->getFolderEntities($folder, $widget_settings['view_order'], $target_bundles);
    foreach ($values as $file_entity) {
      $field = static::getFolderEntitiesFileField($file_entity->bundle());
      if (!empty($file_entity->$field->entity)) {
        $this->buildEntitiesFileField($files, $file_entity, $field, $view_type);
      }
      else {
        $this->buildEntitiesLinkField($files, $file_entity, $view_type);
      }
    }

    $folders = array_merge($folders, $files);
    $this->addLoadMore($folder_id, $folders, count($values), $count);
  }

  /**
   * Retrieves and processes folders for the media folders UI.
   *
   * This method retrieves folders associated with a parent folder and processes
   * them into a format suitable for rendering in the media folders UI. It
   * includes metadata such as name, description, file count, and actions.
   *
   * @param int $folder_id
   *   The ID of the parent folder. Use 0 for the root folder.
   * @param string $view_type
   *   The view type (e.g., "list" or "grid") for rendering the folders.
   * @param array &$cache_tags
   *   A reference to the cache tags array, which will be updated with tags for
   *   the retrieved folders.
   * @param bool $show_action
   *   (Optional) Whether to include actions for the folders. Defaults to TRUE.
   * @param array $opener_parameters
   *   (Optional) An array of parameters for the widget opener, including
   *   entity type, bundle, and field name.
   *
   * @return array
   *   An array of folders formatted for rendering in the media folders UI.
   */
  private function getFolderFolders($folder_id, $view_type, array &$cache_tags, $show_action = TRUE, array $opener_parameters = []) : array {
    $folders = [];
    $target_bundles = [];
    if (!empty($opener_parameters)) {
      $bundle_fields = $this->fieldManager->getFieldDefinitions($opener_parameters['entity_type_id'], $opener_parameters['bundle']);
      $settings = $bundle_fields[$opener_parameters['field_name']]->getSettings();
      $target_bundles = array_values($settings['handler_settings']['target_bundles']);
    }
    $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('media_folders_folder', $folder_id, 1, TRUE);
    foreach ($tree as $term) {
      $classes = [
        'folder-icon',
        'folder-icon-folder-empty',
      ];

      $wrapper_classes = ['folder-folder'];
      if (!$this->hasMediaCreateAccess(NULL, NULL, TRUE)) {
        $wrapper_classes[] = 'not-droppable';
      }
      $file_count = $this->getFileCount($term->id(), $target_bundles, TRUE);

      $folders[] = [
        '#theme' => 'media_folders_folder',
        '#title' => $term->getName(),
        '#weight' => $term->changed->value,
        '#desc' => (!empty($term->getDescription())) ? $term->getDescription() : '',
        '#link' => Url::fromRoute('media_folders.collection.folder', ['folder' => $term->id()]),
        '#classes' => implode(' ', $classes),
        '#wrapper_classes' => implode(' ', $wrapper_classes),
        '#attr' => Markup::create('data-count="' . $file_count . '"'),
        '#size' => ($view_type == 'list') ? $file_count . ' ' . $this->t('items') : '',
        '#ftype' => ($view_type == 'list') ? $this->t('folder') : '',
        '#actions' => ($show_action) ? Markup::create($this->getItemActions($term)) : '',
        '#fid' => $term->id(),
        '#uuid' => $term->uuid(),
        '#cache' => [
          'tags' => [
            'taxonomy_term:' . $term->id(),
          ],
        ],
      ];
      $cache_tags[] = 'taxonomy_term:' . $term->id();
    }

    return $folders;
  }

  /**
   * Retrieves and processes files for the media folders UI.
   *
   * This method retrieves files associated with a folder and processes them
   * into a format suitable for rendering in the media folders UI. It includes
   * metadata such as file size, type, and actions.
   *
   * @param mixed $folder
   *   The folder entity. If NULL, files from the root folder are retrieved.
   * @param array &$folders
   *   A reference to the folders array where the processed files will be added.
   * @param array &$cache_tags
   *   A reference to the cache tags array, which will be updated with tags for
   *   the retrieved files.
   */
  private function getFolderFiles($folder, array &$folders, array &$cache_tags) : void {
    $view_type = $this->getCookie('view-type', $this->mediaFoldersSettings->get('default_view'));
    $view_order = $this->getCookie('view-order', $this->mediaFoldersSettings->get('default_order'));

    $folder_id = ($folder) ? $folder->id() : 0;
    $count = $this->getFileCount($folder_id);

    $values = $this->getFolderEntities($folder, $view_order);
    $files = $this->buildFolderFiles($values, $view_type, $cache_tags);
    $folders = array_merge($folders, $files);

    $this->addLoadMore($folder_id, $folders, count($values), $count);
  }

  /**
   * Adds the load more button when needed.
   *
   * @param mixed $folder_id
   *   The folder id.
   * @param array &$folders
   *   A reference to the folders array where the button will be added.
   * @param int $count
   *   The number of files already displayed.
   * @param int $max
   *   The total number of files inside the folder.
   */
  private function addLoadMore($folder_id, array &$folders, int $count, int $max) : void {
    if ($count < $max) {
      $folders[] = [
        "#theme" => "media_folders_load_more",
        "#title" => $this->t('Load more'),
        "#link" => Url::fromRoute('media_folders.collection.folder.load_more.ajax', ['folder' => $folder_id])->toString(),
      ];
    }
  }

  /**
   * Processes files for rendering in the media folders UI.
   *
   * This method processes a list of file entities and formats them into a
   * structure suitable for rendering in the media folders UI. It includes
   * metadata such as file size, type, thumbnail images, and actions.
   *
   * @param array $values
   *   An array of file entities to process.
   * @param string $view_type
   *   The view type (e.g., "list" or "grid") for rendering the files.
   * @param array &$cache_tags
   *   A reference to the cache tags array, which will be updated with tags for
   *   the processed files.
   *
   * @return array
   *   An array of files formatted for rendering in the media folders UI.
   */
  public function buildFolderFiles(array $values, $view_type, array &$cache_tags) : array {
    $files = [];
    foreach ($values as $file_entity) {
      $field = static::getFolderEntitiesFileField($file_entity->bundle());
      if (!empty($file_entity->$field->entity)) {
        $this->buildEntitiesFileField($files, $file_entity, $field, $view_type, TRUE, FALSE, $cache_tags);
      }
      else {
        $this->buildEntitiesLinkField($files, $file_entity, $view_type, TRUE, FALSE, $cache_tags);
      }
    }

    return $files;
  }

  /**
   * Builds a thumbnail for an image file.
   *
   * This method generates a render array for an image thumbnail if the file
   * entity is of the `image` bundle and thumbnails are enabled in the settings.
   *
   * @param \Drupal\media\MediaInterface $file_entity
   *   The media entity associated with the file.
   * @param \Drupal\file\FileInterface $file
   *   The file entity for which the thumbnail is being generated.
   *
   * @return array|null
   *   A render array for the image thumbnail, or NULL if thumbnails are not
   *   enabled or the file is not an image.
   */
  private function getImageThumbnail(MediaInterface $file_entity, FileInterface $file) : array|null {
    $source_type = $file_entity->getSource()?->getPluginId();

    return ($this->mediaFoldersSettings->get('show_thumbnails') && $source_type === 'image') ? [
      '#theme' => 'image_style',
      '#style_name' => 'thumbnail',
      '#uri' => $file->getFileUri(),
    ] : NULL;
  }

  /**
   * Retrieves media entities associated with a folder.
   *
   * This method retrieves media entities that are associated with a specific
   * folder or the root folder if no folder is provided. It supports filtering
   * by target media bundles.
   *
   * @param mixed $folder
   *   The folder. If NULL, media entities from the root folder are retrieved.
   * @param string $view_order
   *   The view order.
   * @param array|null $target_bundles
   *   (Optional) An array of target media bundles to filter. If NULL,
   *   no bundle filtering is applied.
   * @param int $first
   *   The first item in the database range statement.
   *
   * @return array
   *   An array of media entities associated with the folder.
   */
  public function getFolderEntities($folder, $view_order, $target_bundles = NULL, $first = 0) : array {
    $folder_id = ($folder) ? $folder->id() : 0;
    $cid = is_null($target_bundles) ? 'media_folders:files:' . $first . ':' . $view_order . ':' . $folder_id : 'media_folders:files:' . $first . ':' . $view_order . ':' . implode('_', $target_bundles) . ':' . $folder_id;
    if ($cache = $this->cache->get($cid)) {
      $response = $cache->data;
    }
    else {
      $query = $this->entityTypeManager->getStorage('media')->getQuery();
      if (!is_null($folder)) {
        $query->condition('field_folders_folder', $folder->id());
      }
      else {
        $query->notExists('field_folders_folder');
      }
      if (!is_null($target_bundles)) {
        $query->condition('bundle', $target_bundles, 'IN');
      }
      $query->condition('status', 1);

      if ($view_order == 'az') {
        $query->sort('name', 'ASC');
      }
      elseif ($view_order == 'za') {
        $query->sort('name', 'DESC');
      }
      elseif ($view_order == 'date-asc') {
        $query->sort('created', 'ASC');
      }
      elseif ($view_order == 'date-desc') {
        $query->sort('created', 'DESC');
      }

      $query->range($first, $this->pageLimit);
      $entity_ids = $query->accessCheck(FALSE)->execute();

      if (!empty($entity_ids)) {
        $response = $this->entityTypeManager->getStorage('media')->loadMultiple($entity_ids);
      }
      else {
        $response = [];
      }

      $this->cache->set($cid, $response, CacheBackendInterface::CACHE_PERMANENT, [
        'taxonomy_term_list:media_folders_folder',
        'media_list',
      ]);
    }

    return $response;
  }

  /**
   * Retrieves and processes folders matching a search query.
   *
   * This method retrieves folders associated with a parent folder and filters
   * them based on a search query. It processes the folders into a format
   * suitable for rendering in the media folders UI, including metadata such as
   * name, description, file count, and actions.
   *
   * @param int $folder_id
   *   The ID of the parent folder. Use 0 for the root folder.
   * @param string $search
   *   The search query to filter the folders.
   * @param array &$cache_tags
   *   A reference to the cache tags array, which will be updated with tags for
   *   the retrieved folders.
   *
   * @return array
   *   An array of folders matching the search query, formatted for rendering in
   *   the media folders UI.
   */
  private function getSearchFolderFolders($folder_id, $search, array &$cache_tags) : array {
    $folders = [];
    $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('media_folders_folder', $folder_id, NULL, TRUE);

    foreach ($tree as $term) {
      if (stripos($term->getName(), $search) === FALSE) {
        continue;
      }
      $classes = [
        'folder-icon',
        'folder-icon-folder-empty',
      ];

      $wrapper_classes = ['folder-folder', 'not-droppable', 'not-draggable'];
      $file_count = $this->getFileCount($term->id(), [], TRUE);
      $location = (empty($term->parent->entity)) ? $this->t('Root') : $term->parent->entity->getName();

      $folders[] = [
        '#theme' => 'media_folders_folder',
        '#title' => $this->highlightSearchResult($search, $term->getName()),
        '#weight' => $term->changed->value,
        '#desc' => (!empty($term->getDescription())) ? $term->getDescription() : '',
        '#link' => Url::fromRoute('media_folders.collection.folder', ['folder' => $term->id()]),
        '#classes' => implode(' ', $classes),
        '#wrapper_classes' => implode(' ', $wrapper_classes),
        '#attr' => Markup::create('data-count="' . $file_count . '"'),
        '#size' => $location,
        '#ftype' => $this->t('folder'),
        '#fid' => $term->id(),
        '#uuid' => $term->uuid(),
        '#cache' => [
          'tags' => [
            'taxonomy_term:' . $term->id(),
          ],
        ],
      ];
      $cache_tags[] = 'taxonomy_term:' . $term->id();
    }

    return $folders;
  }

  /**
   * Retrieves and processes files matching a search query for the UI.
   *
   * This method retrieves files associated with a folder and filters them based
   * on a search query. It processes the files into a rendering format
   * in the UI, including metadata such as file size, type, and location.
   *
   * @param mixed $folder
   *   The folder entity. If NULL, files from the root folder are retrieved.
   * @param string $search
   *   The search query to filter the files.
   * @param array &$folders
   *   A reference to the folders array where the processed files will be added.
   * @param array &$cache_tags
   *   A reference to the cache tags array, which will be updated with tags for
   *   the retrieved files.
   * @param array|null $target_bundles
   *   (Optional) An array of target media bundles to filter. If NULL,
   *   no bundle filtering is applied.
   */
  private function getSearchFolderFiles($folder, $search, array &$folders, array &$cache_tags, array|null $target_bundles = NULL) : void {
    $values = $this->getSearchFolderEntities($folder, $search, $target_bundles);
    $files = $this->buildSearchFolderFiles($values, $search, $cache_tags);

    $folders = array_merge($folders, $files);
  }

  /**
   * Retrieves media entities matching a search query within a folder.
   *
   * This method retrieves media entities associated with a folder and filters
   * them based on a search query. It supports searching within subfolders and
   * the root folder if no specific folder is provided.
   *
   * @param mixed $folder
   *   The folder. If NULL, media entities from the root folder are retrieved.
   * @param string $search
   *   The search query to filter the media entities.
   * @param array|null $target_bundles
   *   (Optional) An array of target media bundles to filter. If NULL,
   *   no bundle filtering is applied.
   *
   * @return array
   *   An array of media entities matching the search query.
   */
  public function getSearchFolderEntities($folder, $search, array|null $target_bundles = NULL) : array {
    $folder_id = ($folder) ? $folder->id() : 0;
    $cid = 'media_folders:files:search:' . $folder_id . '.' . $search;
    if ($cache = $this->cache->get($cid)) {
      $response = $cache->data;
    }
    else {
      $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('media_folders_folder', $folder_id, NULL, FALSE);
      $folders = [];
      foreach ($tree as $term) {
        $folders[] = $term->tid;
      }

      $query = $this->entityTypeManager->getStorage('media')->getQuery();
      if (!is_null($folder)) {
        $folders[] = $folder_id;
        $query->condition('field_folders_folder', $folders, 'IN');
      }
      else {
        $or_condition = $query->orConditionGroup()
          ->notExists('field_folders_folder')
          ->condition('field_folders_folder', $folders, 'IN');
        $query->condition($or_condition);
      }
      if (!is_null($target_bundles)) {
        $query->condition('bundle', $target_bundles, 'IN');
      }
      $query->condition('name', '%' . $search . '%', 'LIKE');
      $query->condition('status', 1);
      $query->sort('created', 'DESC');
      $entity_ids = $query->accessCheck(FALSE)->execute();

      if (!empty($entity_ids)) {
        $response = $this->entityTypeManager->getStorage('media')->loadMultiple($entity_ids);
      }
      else {
        $response = [];
      }

      $this->cache->set($cid, $response, CacheBackendInterface::CACHE_PERMANENT, [
        'taxonomy_term_list:media_folders_folder',
        'media_list',
      ]);
    }

    return $response;
  }

  /**
   * Processes files matching a search query for rendering in the UI.
   *
   * This method processes a list of file entities that match a search query and
   * formats them into a structure suitable for rendering in the UI.
   * Includes metadata such as size, type, location, and highlighted terms.
   *
   * @param array $values
   *   An array of file entities to process.
   * @param string $search
   *   The search query used to filter the files.
   * @param array &$cache_tags
   *   A reference to the cache tags array, which will be updated with tags for
   *   the processed files.
   *
   * @return array
   *   An array of files formatted for rendering in the media folders UI.
   */
  public function buildSearchFolderFiles(array $values, $search, array &$cache_tags) : array {
    $files = [];
    foreach ($values as $file_entity) {
      $field = static::getFolderEntitiesFileField($file_entity->bundle());
      if (!empty($file_entity->$field->entity)) {
        $this->buildEntitiesFileField($files, $file_entity, $field, 'search-list', FALSE, $search, $cache_tags);
      }
      else {
        $this->buildEntitiesLinkField($files, $file_entity, 'search-list', FALSE, $search, $cache_tags);
      }
    }

    return $files;
  }

  /**
   * Highlights search terms in a string.
   *
   * This method highlights occurrences of a search term within a given string
   * by wrapping the text in `<strong>` tags. Search is case-insensitive.
   *
   * @param string $search
   *   The search term to highlight.
   * @param string $string
   *   The string in which to highlight the search term.
   *
   * @return \Drupal\Core\Render\Markup
   *   A Markup object containing the string with highlighted search terms.
   */
  public function highlightSearchResult($search, $string) : Markup {
    $safe = Html::escape($string);
    $pattern = '/(' . preg_quote(Html::escape($search), '/') . ')/i';
    return Markup::create(preg_replace($pattern, '<strong>$1</strong>', $safe));
  }

  /**
   * Retrieves the file field name for a given media bundle.
   *
   * This method identifies the file or image field associated with a specific
   * media bundle by inspecting its field definitions. Returns the first field
   * that matches the `file` or `image` field type.
   *
   * @param string $bundle
   *   The media bundle for which the file field is being retrieved.
   *
   * @return string|false
   *   The name of the field if found, or FALSE if no such field exists.
   */
  public static function getFolderEntitiesFileField($bundle) : string|false {
    $fieldDefinitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('media', $bundle);
    foreach ($fieldDefinitions as $field_name => $field_config) {
      if (str_starts_with($field_name, 'field_')) {
        $type = $field_config->getFieldStorageDefinition()->getType();
        if ($type === 'image' || $type === 'file' || $type === 'svg_image_field') {
          return $field_name;
        }
      }
    }

    return FALSE;
  }

  /**
   * Retrieves the link field name for a given media bundle.
   *
   * This method identifies the file or image field associated with a specific
   * media bundle by inspecting its field definitions. Returns the first field
   * that matches the `string` field type.
   *
   * @param string $bundle
   *   The media bundle for which the file field is being retrieved.
   *
   * @return string|false
   *   The name of the field if found, or FALSE if no such field exists.
   */
  public static function getFolderEntitiesLinkField($bundle) : string|false {
    $fieldDefinitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('media', $bundle);
    foreach ($fieldDefinitions as $field_name => $field_config) {
      if (str_starts_with($field_name, 'field_')) {
        $type = $field_config->getFieldStorageDefinition()->getType();
        if ($type == 'string') {
          return $field_name;
        }
      }
    }

    return FALSE;
  }

  /**
   * Adds a table header to the folders array.
   *
   * This method prepends a table header row to the folders array, which is used
   * for rendering the media folders UI. The header includes columns for name,
   * size, type, and actions.
   *
   * @param array &$folders
   *   A reference to the folders array where the table header will be added.
   */
  public function addTableHeader(array &$folders) : void {
    $header = [
      '#theme' => 'media_folders_folder',
      '#title' => $this->t('Name'),
      '#weight' => -1,
      '#wrapper_classes' => 'table-header',
      '#size' => $this->t('Size'),
      '#ftype' => $this->t('Type'),
    ];
    array_unshift($folders, $header);
  }

  /**
   * Adds a search-specific table header to the folders array.
   *
   * This method prepends a table header row to the folders array, specifically
   * for rendering search results in the media folders UI. The header includes
   * columns for search results, location, type, and actions.
   *
   * @param array &$folders
   *   A reference to the array where the search table header will be added.
   */
  public function addSearchTableHeader(array &$folders) : void {
    $header = [
      '#theme' => 'media_folders_folder',
      '#title' => $this->t('Search results'),
      '#weight' => -1,
      '#wrapper_classes' => 'table-header',
      '#size' => $this->t('Location'),
      '#ftype' => $this->t('Type'),
    ];
    array_unshift($folders, $header);
  }

  /**
   * Build the render array for file field entities.
   *
   * @param array $files
   *   A reference to the files array where the processed files will be added.
   * @param \Drupal\media\Entity\Media $file_entity
   *   The media entity to be processed.
   * @param string $field
   *   The file field name.
   * @param string $view_type
   *   The view_type.
   * @param bool $actions
   *   Boolean to show or not the actions, defaults FALSE.
   * @param string|false $search
   *   The search query, defaults FALSE.
   * @param array $cache_tags
   *   The cache tags array, defaults to an empty array.
   * @param array $widget_settings
   *   The widget settings array, defaults to an empty array.
   */
  private function buildEntitiesFileField(array &$files, Media $file_entity, $field, $view_type, $actions = FALSE, $search = FALSE, array &$cache_tags = [], array $widget_settings = []) : void {
    $file = $file_entity->$field->entity;
    $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    $type = $this->humanFilesize($file->getSize());
    $wrapper_classes = 'folder-file';
    $title = $file_entity->getName();
    $size = '';
    $ftype = '';

    if ($view_type == 'list') {
      $size = $type;
      $ftype = $ext;
    }
    elseif ($view_type == 'search-list') {
      $size = (empty($file_entity->field_folders_folder->entity)) ? $this->t('Root') : $file_entity->field_folders_folder->entity->getName();
      $ftype = $ext;
    }

    if ($search) {
      $title = $this->highlightSearchResult($search, $file_entity->getName());
      $wrapper_classes .= ' not-draggable';
    }

    try {
      $link = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    }
    catch (\Throwable $th) {
      $link = NULL;
    }

    $files[] = [
      '#theme' => 'media_folders_folder',
      '#title' => $title,
      '#weight' => $file_entity->created->value,
      '#icon' => $this->getImageThumbnail($file_entity, $file),
      '#link' => $link,
      '#classes' => $this->getFileClasses($file),
      '#wrapper_classes' => $wrapper_classes,
      '#attr' => Markup::create('download data-ext=".' . $ext . '" data-size="' . $type . '"'),
      '#size' => $size,
      '#ftype' => $ftype,
      '#actions' => ($actions) ? Markup::create($this->getItemActions(FALSE, $file_entity, $widget_settings)) : NULL,
      '#fid' => $file_entity->id(),
      '#uuid' => $file_entity->uuid(),
      '#cache' => [
        'tags' => [
          'media:' . $file_entity->id(),
        ],
      ],
    ];
    $cache_tags[] = 'media:' . $file_entity->id();
  }

  /**
   * Build the render array for link field entities.
   *
   * @param array $files
   *   A reference to the files array where the processed files will be added.
   * @param \Drupal\media\Entity\Media $file_entity
   *   The media entity to be processed.
   * @param string $view_type
   *   The view_type.
   * @param bool $actions
   *   Boolean to show or not the actions, defaults FALSE.
   * @param string|false $search
   *   The search query, defaults FALSE.
   * @param array $cache_tags
   *   The cache tags array, defaults to an empty array.
   * @param array $widget_settings
   *   The widget settings array, defaults to an empty array.
   */
  private function buildEntitiesLinkField(array &$files, Media $file_entity, $view_type, $actions = FALSE, $search = FALSE, array &$cache_tags = [], array $widget_settings = []) : void {
    $field = static::getFolderEntitiesLinkField($file_entity->bundle());
    if (!empty($file_entity->$field->value)) {
      $size = '';
      $ftype = '';
      $wrapper_classes = 'folder-file';
      $title = $file_entity->getName();

      if ($view_type == 'list') {
        $size = '0 bytes';
        $ftype = 'link';
      }
      elseif ($view_type == 'search-list') {
        $size = (empty($file_entity->field_folders_folder->entity)) ? $this->t('Root') : $file_entity->field_folders_folder->entity->getName();
        $ftype = 'link';
      }

      if ($search) {
        $title = $this->highlightSearchResult($search, $file_entity->getName());
        $wrapper_classes .= ' not-draggable';
      }

      $files[] = [
        '#theme' => 'media_folders_folder',
        '#title' => $title,
        '#weight' => $file_entity->created->value,
        '#link' => $file_entity->$field->value,
        '#classes' => 'folder-icon folder-icon-file',
        '#wrapper_classes' => $wrapper_classes,
        '#attr' => Markup::create('download data-ext="link" data-size="0 bytes"'),
        '#size' => $size,
        '#ftype' => $ftype,
        '#actions' => ($actions) ? Markup::create($this->getItemActions(FALSE, $file_entity, $widget_settings)) : NULL,
        '#fid' => $file_entity->id(),
        '#uuid' => $file_entity->uuid(),
        '#cache' => [
          'tags' => [
            'media:' . $file_entity->id(),
          ],
        ],
      ];
      $cache_tags[] = 'media:' . $file_entity->id();
    }
  }

  /**
   * Generates the "Up" button for navigating to the parent folder.
   *
   * This method creates a link to the parent folder or the root folder if no
   * parent exists. It is used for navigation in the media folders UI.
   *
   * @param mixed $parent
   *   The parent folder entity. If NULL, the root folder is assumed.
   * @param bool $search
   *   (Optional) If TRUE, the button is generated for search results.
   *
   * @return array|\Drupal\Core\Render\Markup
   *   A render array for the "Up" button or an empty span if no parent exists.
   */
  public function getUpButton($parent, $search = FALSE) : array|Markup {
    if ($search) {
      if ($parent) {
        $link = Link::fromTextAndUrl('', Url::fromRoute('media_folders.collection.folder', ['folder' => $parent->id()]))->toRenderable();
      }
      else {
        $link = Link::fromTextAndUrl('', Url::fromRoute('media_folders.collection'))->toRenderable();
      }
      $link['#attributes']['title'] = $this->t('Parent folder');
      $link['#attributes']['data-label'] = $this->t('Parent folder');

      return $link;
    }
    elseif (isset($parent->parent->target_id) && $parent->parent->target_id == 0) {
      $link = Link::fromTextAndUrl('', Url::fromRoute('media_folders.collection'))->toRenderable();
      $link['#attributes']['title'] = $this->t('Parent folder');
      $link['#attributes']['data-label'] = $this->t('Parent folder');

      return $link;
    }
    elseif (isset($parent->parent->target_id)) {
      $link = Link::fromTextAndUrl('', Url::fromRoute('media_folders.collection.folder', ['folder' => $parent->parent->target_id]))->toRenderable();
      $link['#attributes']['title'] = $this->t('Parent folder');
      $link['#attributes']['data-label'] = $this->t('Parent folder');

      return $link;
    }

    return Markup::create('<span></span>');
  }

  /**
   * Generates the breadcrumb for the media folders UI.
   *
   * This method creates a breadcrumb trail for navigation in the UI.
   * It supports both standard folder navigation and search result contexts.
   *
   * @param mixed $parent
   *   The parent folder. If NULL, the breadcrumb will represent the root.
   * @param string|bool $search
   *   (Optional) The search query. If provided, the breadcrumb will represent
   *   search results within the specified folder.
   *
   * @return \Drupal\Core\Render\Markup
   *   A Markup object containing the breadcrumb trail.
   */
  public function getBreadcrumb($parent, $search = FALSE) : Markup {
    if ($search) {
      return Markup::create('<span class="search">' . $this->t('Search results for "<strong>@search</strong>" in "<strong>@folder</strong>"', [
        '@search' => $search,
        '@folder' => ($parent) ? $parent->getName() : $this->t('Root'),
      ]) . '</span>');
    }
    elseif (isset($parent->parent->target_id)) {
      $parents = [];
      $term = clone $parent;
      $parents[$parent->id()] = '<span>' . Unicode::truncate($parent->getName(), 15, FALSE, TRUE) . '</span>';
      while (!empty($term->parent->target_id)) {
        $term = $term->parent->entity;
        $parents[$term->id()] = Link::fromTextAndUrl(Unicode::truncate($term->getName(), 15, FALSE, TRUE), Url::fromRoute('media_folders.collection.folder', ['folder' => $term->id()]))->toString();
      }
      $parents[0] = Link::fromTextAndUrl($this->t('Root'), Url::fromRoute('media_folders.collection'))->toString();
      $parents = array_reverse($parents);

      return Markup::create(implode('<i></i>', $parents));
    }

    return Markup::create('<span class="root">' . $this->t('Root') . '</span>');
  }

  /**
   * Retrieves the total count of files and subfolders within a folder.
   *
   * This method calculates the total number of files and subfolders associated
   * with a given folder. It queries the media entities and subfolder terms
   * linked to the folder.
   *
   * @param int $folder
   *   The folder ID for which the file and subfolder count is being retrieved.
   * @param array|null $target_bundles
   *   The target accepted bundles of files to count.
   * @param bool $count_folders
   *   Count sub folders also.
   *
   * @return int
   *   The total count of files and subfolders within the folder.
   */
  public function getFileCount($folder, array|null $target_bundles = [], bool $count_folders = FALSE) : int {
    $sub_folder_count = ($count_folders) ? $this->entityTypeManager->getStorage('taxonomy_term')->loadChildren($folder) : [];
    $query = $this->entityTypeManager->getStorage('media')->getQuery();
    if (!empty($folder)) {
      $query->condition('field_folders_folder', $folder);
    }
    else {
      $query->notExists('field_folders_folder');
    }
    if (!empty($target_bundles)) {
      $query->condition('bundle', array_values($target_bundles), 'IN');
    }
    $query->condition('status', 1);
    $entity_ids = $query->accessCheck(FALSE)->execute();

    return count($sub_folder_count) + count($entity_ids);
  }

  /**
   * Checks if the user has permission to perform an action on taxonomy terms.
   *
   * This method verifies whether the current user has the necessary permissions
   * to perform a specific action (e.g., create, edit, delete) on taxonomy terms
   * within the `media_folders_folder` vocabulary.
   *
   * @param string $action
   *   The action to check permissions for (e.g., 'create', 'edit', 'delete').
   * @param bool $bool
   *   (Optional) If TRUE, returns a boolean value. If FALSE, returns an
   *   \Drupal\Core\Access\AccessResult object.
   *
   * @return bool|\Drupal\Core\Access\AccessResult
   *   Returns TRUE or an AccessResult object if the user has permission,
   *   otherwise FALSE or an AccessResult object indicating forbidden access.
   */
  public function hasTermPermission($action, $bool = TRUE) : bool|AccessResult {
    if ($this->currentUser->hasPermission('administer taxonomy')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }
    if ($this->currentUser->hasPermission($action . ' terms in media_folders_folder')) {
      return ($bool) ? TRUE : AccessResult::allowed()->cachePerPermissions();
    }

    return ($bool) ? FALSE : AccessResult::forbidden()->cachePerPermissions();
  }

  /**
   * Retrieves action links for a folder or file.
   *
   * This method generates action links (e.g., edit, delete) for a given folder
   * or file. The actions are rendered as dialog links for use in the media
   * folders UI.
   *
   * @param mixed $folder
   *   (Optional) The folder entity for which actions are being generated. If
   *   provided, folder-specific actions will be included.
   * @param mixed $file
   *   (Optional) The file entity for which actions are being generated. If
   *   provided, file-specific actions will be included.
   * @param array $widget_settings
   *   The widget settings array, defaults to an empty array.
   *
   * @return string
   *   A string containing the rendered action links.
   */
  private function getItemActions(mixed $folder = FALSE, mixed $file = FALSE, array $widget_settings = []) : string {
    $items = $render_items = [];

    if ($folder) {
      if ($this->hasTermPermission('edit')) {
        $options = ['media-folders-edit' => 1];
        $items['edit'] = $this->createDialogLink($this->t('Edit folder'), Url::fromRoute('media_folders.edit_folder', ['folder' => $folder->id()]), ['edit-action'], $this->t('Edit'), TRUE, '60%', $options);
      }

      if ($this->hasTermPermission('delete')) {
        $items['delete'] = $this->createDialogLink($this->t('Delete folder'), Url::fromRoute('media_folders.delete_folder', ['folder' => $folder->id()]), ['delete-action'], $this->t('Delete'), FALSE, '60%');
      }
    }
    elseif ($file) {
      if ($this->hasMediaEditAccess($file, TRUE)) {
        $config = $this->configFactory->get('media_folders.settings')->get('form_mode');
        $options = ['media-folders-edit' => (!empty($config[$file->bundle()])) ? $config[$file->bundle()] : 'default'];
        if (!empty($widget_settings['form_mode'][$file->bundle()])) {
          $options['media-folders-edit'] = $widget_settings['form_mode'][$file->bundle()];
        }
        if (!empty($widget_settings)) {
          $options['widget-edit'] = 1;
        }
        $items['edit'] = $this->createDialogLink($this->t('Edit media'), Url::fromRoute('entity.media.edit_form', ['media' => $file->id()]), ['edit-action'], $this->t('Edit'), TRUE, '60%', $options);
      }
      if ($this->currentUser->hasPermission('view all media revisions') || $this->currentUser->hasPermission('administer media')) {
        $items['revision'] = $this->createDialogLink($this->t('Media revisions'), Url::fromRoute('entity.media.version_history', ['media' => $file->id()]), ['revisions-action'], $this->t('Revisions'), TRUE, '60%');
      }
      if ($this->hasMediaDeleteAccess($file, TRUE)) {
        $options = [];
        if (!empty($widget_settings)) {
          $options = ['media-folders-delete' => 1];
        }
        $items['delete'] = $this->createDialogLink($this->t('Delete media'), Url::fromRoute('entity.media.delete_form', ['media' => $file->id()]), ['delete-action'], $this->t('Delete'), TRUE, '60%', $options);
      }
    }

    foreach ($items as $item) {
      $render_items[] = $this->renderer->render($item);
    }

    return implode('', $render_items);
  }

  /**
   * Creates a dialog link for use in the media folders UI.
   *
   * This method generates a link that opens in a modal dialog. It is used for
   * actions such as editing or deleting folders and files in the UI.
   *
   * @param string $text
   *   The link text to display.
   * @param \Drupal\Core\Url $url
   *   The URL object for the link.
   * @param array $classes
   *   (Optional) An array of CSS classes to apply to the link.
   * @param string|bool $title
   *   (Optional) The title attribute. If FALSE, the link text is used.
   * @param bool $query
   *   (Optional) Whether to include the destination as a query parameter.
   * @param string $width
   *   (Optional) The width of the modal dialog (e.g., '60%').
   * @param array|null $query_params
   *   (Optional) Whether to include the custom query parameter.
   *
   * @return array
   *   A render array representing the dialog link.
   */
  private function createDialogLink($text, Url $url, array $classes = [], $title = FALSE, $query = FALSE, $width = '60%', $query_params = FALSE) : array {
    $link = $this->createLink($text, $url, $classes, $title, $query, $query_params);
    $link['#attributes']['class'][] = 'use-ajax';
    $link['#attributes']['data-dialog-type'] = 'dialog';
    $link['#attributes']['data-dialog-options'] = Json::encode(['width' => $width]);
    $link['#attached'] = [
      'library' => [
        'core/drupal.dialog.ajax',
      ],
    ];
    return $link;
  }

  /**
   * Creates a link for use in the media folders UI.
   *
   * This method generates a render array for a link with optional CSS classes,
   * title attributes, and query parameters. It is used as a helper method for
   * creating links in the media folders UI.
   *
   * @param string $text
   *   The link text to display.
   * @param \Drupal\Core\Url $url
   *   The URL object for the link.
   * @param array $classes
   *   (Optional) An array of CSS classes to apply to the link.
   * @param string|bool $title
   *   (Optional) The title for the link. If FALSE, the link text is used.
   * @param bool $query
   *   (Optional) Whether to include the destination as a query parameter.
   * @param array|null $query_params
   *   (Optional) Whether to include the custom query parameter.
   *
   * @return array
   *   A render array representing the link.
   */
  private function createLink($text, Url $url, array $classes = [], $title = FALSE, $query = FALSE, $query_params = FALSE) {
    $link = Link::fromTextAndUrl($text, $url)->toRenderable();
    $link['#attributes'] = [
      'class' => $classes,
      'title' => ($title ? $title : $text),
    ];
    if ($query) {
      $destination = $this->redirectDestination->getAsArray();
      if (!empty($destination['destination'])) {
        $url = parse_url($destination['destination']);
        $destination['destination'] = str_replace('/ajax', '', $url['path']);
      }
      if (!empty($query_params)) {
        $destination = array_merge($destination, $query_params);
      }
      $link['#url']->setOption('query', $destination);
    }

    return $link;
  }

  /**
   * Generates the upload action for a folder.
   *
   * This method creates a render array for the "Upload files" action, which
   * allows users to upload files to a specific folder. The action is rendered
   * as a dialog link in the media folders UI.
   *
   * @param mixed $folder
   *   The folder entity. If NULL, the action will target the root folder.
   * @param array $opener_parameters
   *   An array of parameters for the widget opener, including entity type,
   *   bundle, and field name.
   *
   * @return array
   *   A render array for the upload action or an empty string if the user does
   *   not have permission to upload files.
   */
  private function getUploadAction($folder, array $opener_parameters) : array {
    $action_links = [];
    if ($this->hasMediaCreateAccess(NULL, NULL, TRUE)) {
      $action_links['upload'] = [
        'title' => $this->t('Upload files'),
        'url' => Url::fromRoute('media_folders.widget.add_file', [
          'folder' => ($folder) ? $folder->id() : 0,
          'entity_type_id' => $opener_parameters['entity_type_id'],
          'bundle' => $opener_parameters['bundle'],
          'widget_id' => $opener_parameters['field_name'],
        ]),
        'class' => ['upload-file'],
      ];
      $action_links['upload']['attributes']['class'][] = 'use-ajax';
      $action_links['upload']['attributes']['data-dialog-type'] = 'dialog';
      $action_links['upload']['attributes']['data-dialog-options'] = Json::encode(['width' => '60%']);
      $action_links['upload']['#attached'] = [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ];
      $actions = [
        '#type' => 'dropbutton',
        '#links' => $action_links,
      ];

      return $actions;
    }

    return [
      '#markup' => '',
    ];
  }

  /**
   * Generates the new folder action for a folder.
   *
   * This method creates a render array for the "New folder" action, which
   * allows users to add a folder in a specific folder. The action is rendered
   * as a dialog link in the media folders UI.
   *
   * @param mixed $folder
   *   The folder entity. If NULL, the action will target the root folder.
   * @param array $opener_parameters
   *   An array of parameters for the widget opener, including entity type,
   *   bundle, and field name.
   *
   * @return array
   *   A render array for the folder action or an empty string if the user does
   *   not have permission to create folders.
   */
  private function getNewFolderAction($folder, array $opener_parameters) : array {
    $action_links = [];
    if ($this->hasTermPermission('create')) {
      $action_links['new_folder'] = [
        'title' => $this->t('New folder'),
        'url' => Url::fromRoute('media_folders.widget.add_folder', [
          'folder' => ($folder) ? $folder->id() : 0,
          'entity_type_id' => $opener_parameters['entity_type_id'],
          'bundle' => $opener_parameters['bundle'],
          'widget_id' => $opener_parameters['field_name'],
        ]),
        'class' => ['new-folder'],
      ];
      $action_links['new_folder']['attributes']['class'][] = 'use-ajax';
      $action_links['new_folder']['attributes']['data-dialog-type'] = 'dialog';
      $action_links['new_folder']['attributes']['data-dialog-options'] = Json::encode(['width' => '60%']);
      $action_links['new_folder']['#attached'] = [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ];
      $actions = [
        '#type' => 'dropbutton',
        '#links' => $action_links,
      ];

      return $actions;
    }

    return [
      '#markup' => '',
    ];
  }

  /**
   * Retrieves action links for a folder.
   *
   * This method generates action links (e.g., create, edit, delete, manage) for
   * a given folder. The actions are rendered as dialog links for use in the
   * media folders UI.
   *
   * @param mixed $folder
   *   The folder entity. If NULL, actions for the root folder are generated.
   *
   * @return array|string
   *   A render array for the folder actions or an empty string if unavailable.
   */
  private function getFolderActions($folder) : array|string {
    $action_links = [];

    $destination = $this->redirectDestination->getAsArray();
    if (!empty($destination['destination'])) {
      $url = parse_url($destination['destination']);
      $destination['destination'] = str_replace('/ajax', '', $url['path']);
    }

    if (!$folder && $this->hasTermPermission('create')) {
      $action_links['new'] = [
        'title' => $this->t('New folder'),
        'url' => Url::fromRoute('media_folders.add_root_folder', [], ['query' => $destination]),
        'class' => ['new-folder'],
      ];
      $this->transformDialogLink($action_links['new'], $this->t('New folder'), TRUE, '60%');
    }
    elseif ($folder && $this->hasTermPermission('create')) {
      $action_links['new'] = [
        'title' => $this->t('New folder'),
        'url' => Url::fromRoute('media_folders.add_folder', ['folder' => $folder->id()], ['query' => $destination]),
        'class' => ['new-folder'],
      ];
      $this->transformDialogLink($action_links['new'], $this->t('New folder'), TRUE, '60%');
    }

    if ($this->hasMediaCreateAccess(NULL, NULL, TRUE)) {
      $action_links['upload'] = [
        'title' => $this->t('Upload files'),
        'url' => Url::fromRoute('media_folders.add_file', [
          'folder' => ($folder) ? $folder->id() : 0,
        ], ['query' => $destination]),
        'class' => ['upload-file  '],
      ];
      $this->transformDialogLink($action_links['upload'], $this->t('Upload files'), TRUE, '60%');
    }

    if ($folder && $this->hasTermPermission('edit')) {
      $action_links['edit'] = [
        'title' => $this->t('Edit folder'),
        'url' => Url::fromRoute('media_folders.edit_folder', ['folder' => $folder->id()], ['query' => $destination]),
        'class' => ['edit-action'],
      ];
      $this->transformDialogLink($action_links['edit'], $this->t('Edit folder'), TRUE, '60%');
    }

    if ($folder && $this->hasTermPermission('delete')) {
      $action_links['delete'] = [
        'title' => $this->t('Delete folder'),
        'url' => Url::fromRoute('media_folders.delete_folder', ['folder' => $folder->id()]),
        'class' => ['delete-action'],
      ];
      $this->transformDialogLink($action_links['delete'], $this->t('Delete folder'), TRUE, '60%');
    }

    if ($this->currentUser->hasPermission('access taxonomy overview') || $this->currentUser->hasPermission('administer taxonomy')) {
      $action_links['manage'] = [
        'title' => $this->t('Manage folders'),
        'url' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'media_folders_folder'], ['query' => $destination]),
        'class' => ['manage-folder'],
      ];
      $this->transformDialogLink($action_links['manage'], $this->t('Manage folders'), TRUE);
    }

    if (!empty($action_links)) {
      $actions = [
        '#type' => 'dropbutton',
        '#links' => $action_links,
      ];

      return $actions;
    }

    return '';
  }

  /**
   * Transforms a link into a dialog link for use in the media folders UI.
   *
   * This method modifies a link render array to include attributes and settings
   * for opening the link in a modal dialog. It is used to standardize dialog
   * links across the media folders UI.
   *
   * @param array &$link
   *   The link render array to be transformed.
   * @param string|bool $title
   *   (Optional) The title attribute for the link. If FALSE, no title is added.
   * @param bool $query
   *   (Optional) Whether to include the destination as a query parameter.
   * @param string $width
   *   (Optional) The width of the modal dialog (e.g., '60%').
   */
  private function transformDialogLink(array &$link, $title = FALSE, $query = FALSE, $width = '60%') : void {
    $link['attributes']['class'][] = 'use-ajax';
    $link['attributes']['data-dialog-type'] = 'modal';
    $link['attributes']['data-dialog-options'] = Json::encode(['width' => $width]);
    $link['#attached'] = [
      'library' => [
        'core/drupal.dialog.ajax',
      ],
    ];
  }

  /**
   * Converts action links into a raw format for rendering.
   *
   * This method processes a set of action links and transforms them into a raw
   * renderable format. It is used to generate simplified action links for use
   * in the media folders UI.
   *
   * @param array $actions
   *   The action links array.
   *
   * @return array
   *   An array of raw action links, each containing attributes and settings.
   */
  public function getRawActions(array $actions) {
    $actions_raw = [];
    if (!empty($actions['#links'])) {
      foreach ($actions['#links'] as $key => $link) {
        $actions_raw[$key] = [
          '#type'  => 'link',
          '#title' => $link['title'],
          '#url'  => $link['url'],
          '#attributes' => $link['attributes'],
          '#attached' => $link['#attached'],
        ];
        $actions_raw[$key]['#attributes']['class'][] = $link['class'][0];
      }
    }

    return $actions_raw;
  }

  /**
   * Generates the view and sort buttons for the media folders UI.
   *
   * This method creates a render array for the view type buttons
   * and the sort options (e.g., "Alphabetic ascending", "Date descending").
   * These buttons allow users to customize the display of media folders.
   *
   * @return string
   *   A rendered string containing the view and sort buttons.
   */
  private function getButtons() : string {
    $action_links = [];

    $destination = $this->redirectDestination->getAsArray();
    if (!empty($destination['destination'])) {
      $url = parse_url($destination['destination']);
      $destination['destination'] = str_replace('/ajax', '', $url['path']);
    }

    $action_links['sorts'] = [
      'title' => $this->t('Sort by'),
      'url' => Url::fromUri('internal://<nolink>'),
      'attributes' => [
        'class' => ['sorts'],
      ],
    ];
    $action_links['az'] = [
      'title' => $this->t('Alphabetic ascending'),
      'url' => Url::fromRoute('media_folders.cookies', [
        'name' => 'view-order',
        'value' => 'az',
      ], ['query' => $destination]),
      'attributes' => [
        'class' => ['az'],
      ],
    ];
    $action_links['za'] = [
      'title' => $this->t('Alphabetic descending'),
      'url' => Url::fromRoute('media_folders.cookies', [
        'name' => 'view-order',
        'value' => 'za',
      ], ['query' => $destination]),
      'attributes' => [
        'class' => ['za'],
      ],
    ];
    $action_links['date-asc'] = [
      'title' => $this->t('Date ascending'),
      'url' => Url::fromRoute('media_folders.cookies', [
        'name' => 'view-order',
        'value' => 'date-asc',
      ], ['query' => $destination]),
      'attributes' => [
        'class' => ['date-asc'],
      ],
    ];
    $action_links['date-desc'] = [
      'title' => $this->t('Date descending'),
      'url' => Url::fromRoute('media_folders.cookies', [
        'name' => 'view-order',
        'value' => 'date-desc',
      ], ['query' => $destination]),
      'attributes' => [
        'class' => ['date-desc'],
      ],
    ];

    $order = $this->getCookie('view-order', $this->mediaFoldersSettings->get('default_order'));
    if (!empty($order)) {
      $action_links[$order]['attributes']['class'][] = 'active';
      $action_links['sorts']['attributes']['class'][] = $order;
    }

    $actions = [
      '#type' => 'dropbutton',
      '#links' => $action_links,
    ];

    $buttons = $render_buttons = [];
    $buttons['thumbs'] = $this->createLink($this->t('Grid'), Url::fromRoute('media_folders.cookies', [
      'name' => 'view-type',
      'value' => 'thumbs',
    ]), ['thumbs'], $this->t('Folder view'), TRUE);
    $buttons['list'] = $this->createLink($this->t('List'), Url::fromRoute('media_folders.cookies', [
      'name' => 'view-type',
      'value' => 'list',
    ]), ['list'], $this->t('List view'), TRUE);
    $view = $this->getCookie('view-type', $this->mediaFoldersSettings->get('default_view'));
    $buttons[$view]['#attributes']['class'][] = 'active';

    foreach ($buttons as $button) {
      $render_buttons[] = $this->renderer->render($button);
    }

    return $this->renderer->render($actions) . implode('', $render_buttons);
  }

  /**
   * Retrieves and renders the media folders search form.
   *
   * This method generates the search form for the media folders UI by using the
   * form builder service. The form is rendered and returned as a string.
   *
   * @return string
   *   A rendered string of the media folders search form.
   */
  public function getForm() : string {
    $form = $this->formBuilder->getForm('\Drupal\media_folders\Form\MediaFoldersSearchForm');

    return $this->renderer->render($form);
  }

  /**
   * Converts a file size in bytes to a human-readable format.
   *
   * This method takes a file size in bytes and converts it into a more
   * human-readable format, such as kB, MB, or GB, with a specified number
   * of decimal places.
   *
   * @param int $bytes
   *   The file size in bytes.
   *
   * @return string
   *   The human-readable file size, including the appropriate unit.
   */
  public function humanFilesize($bytes) : string {
    return (!empty($bytes)) ? ByteSizeMarkup::create($bytes) : '';
  }

  /**
   * Retrieves CSS classes for a file based on its MIME type.
   *
   * This method determines the appropriate CSS classes to apply to a file
   * based on its MIME type. These classes are used to style file icons in the
   * media folders UI.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity for which the CSS classes are being determined.
   *
   * @return string
   *   A string of CSS classes separated by spaces.
   */
  public function getFileClasses(FileInterface $file) : string {
    $classes = [
      'folder-icon',
      'folder-icon-file',
    ];

    switch ($file->getMimeType()) {
      case 'text/plain':
        $classes[] = 'folder-icon-file-code';
        break;

      case 'application/pdf':
        $classes[] = 'folder-icon-file-pdf';
        break;

      case 'application/msword':
      case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
      case 'application/vnd.oasis.opendocument.text':
        $classes[] = 'folder-icon-file-word';
        break;

      case 'application/vnd.oasis.opendocument.spreadsheet':
      case 'application/vnd.ms-excel':
      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
        $classes[] = 'folder-icon-file-excel';
        break;

      case 'application/vnd.ms-powerpoint':
      case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
      case 'application/vnd.oasis.opendocument.presentation':
        $classes[] = 'folder-icon-file-powerpoint';
        break;

      case 'application/gzip':
      case 'application/x-tar':
      case 'application/rar':
      case 'application/zip':
      case 'application/octet-stream':
      case 'application/x-zip-compressed':
      case 'multipart/x-zip':
      case 'application/x-rar-compressed':
      case 'application/vnd.rar':
      case 'application/zip':
        $classes[] = 'folder-icon-file-zip';
        break;

      case 'image/webp':
      case 'image/png':
      case 'image/gif':
      case 'image/jpeg':
      case 'image/jpg':
      case 'image/bmp':
        $classes[] = 'folder-icon-file-image';
        break;

      case 'video/mp4':
      case 'video/webm':
        $classes[] = 'folder-icon-file-video';
        break;

      case 'audio/mpeg':
      case 'audio/x-wav':
        $classes[] = 'folder-icon-file-audio';
        break;

      default:
        $classes[] = 'folder-icon-doc';
        break;
    }

    return implode(' ', $classes);
  }

}
