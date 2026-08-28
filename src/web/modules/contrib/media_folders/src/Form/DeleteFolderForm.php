<?php

namespace Drupal\media_folders\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form builder.
 */
class DeleteFolderForm extends ConfirmFormBase {

  /**
   * The Get EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Folder.
   *
   * @var \Drupal\taxonomy\Entity\TaxonomyTerm
   */
  protected $folder;

  /**
   * Sub folders.
   *
   * @var array
   */
  protected $subFolders;

  /**
   * Files.
   *
   * @var array
   */
  protected $files;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFolderChildren(&$children, &$files, $parent) {
    $files = $this->entityTypeManager->getStorage('media')->loadByProperties([
      'field_folders_folder' => $parent,
    ]);
    if (!empty($files)) {
      foreach ($files as $file) {
        $files[$file->id()] = $file->label();
      }
    }

    $folders = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'parent' => $parent,
    ]);
    if (!empty($folders)) {
      foreach ($folders as $child) {
        $children[$child->id()] = $child->getName();
        $this->getFolderChildren($children, $files, $child->id());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $folder = NULL) {
    $this->folder = $folder;
    $this->subFolders = [];
    $this->files = [];

    $this->getFolderChildren($this->subFolders, $this->files, $this->folder->id());
    if (!empty($this->subFolders)) {
      $form['children'] = [
        '#title' => $this->t('The following sub folders will also be deleted'),
        '#theme' => 'item_list',
        '#items' => $this->subFolders,
      ];
    }
    if (!empty($this->files)) {
      $form['files'] = [
        '#title' => $this->t('The following files will moved to root'),
        '#theme' => 'item_list',
        '#items' => $this->files,
      ];
    }

    $form = parent::buildForm($form, $form_state);
    $form['actions']['cancel']['#ajax'] = [
      'callback' => '::cancelForm',
      'event' => 'click',
    ];
    $form['actions']['submit']['#ajax'] = [
      'callback' => '::submitForm',
      'event' => 'click',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    foreach (array_keys($this->files) as $file_id) {
      try {
        $file = $this->entityTypeManager->getStorage('media')->load($file_id);
        $file->field_folders_folder->target_id = NULL;
        $file->save();
      }
      catch (\Throwable $th) {

      }
    }
    foreach (array_keys($this->subFolders) as $folder_id) {
      try {
        $sub_folder = $this->entityTypeManager->getStorage('taxonomy_term')->load($folder_id);
        $sub_folder->delete();
      }
      catch (\Throwable $th) {

      }
    }
    $this->folder->delete();
    $response->addCommand(new MessageCommand($this->t('Folder deleted'), '#folders-messages'));
    $response->addCommand(new InvokeCommand('.navbar-folder[data-id=' . $this->folder->parent->target_id . '] a', 'click'));
    $response->addCommand(new CloseDialogCommand('.ui-dialog:has(.delete-folder-form) .ui-dialog-content'));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand('.ui-dialog:has(.delete-folder-form) .ui-dialog-content'));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "delete_folder_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('media_folders.collection.folder', [
      'folder' => $this->folder->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to delete folder @name?', ['@name' => $this->folder->getName()]);
  }

}
