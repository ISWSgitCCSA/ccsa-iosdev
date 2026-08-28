<?php

namespace Drupal\media_folders\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Form\WorkspaceSafeFormTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form to confirm deletion of something by id.
 */
class UpdateMultipleActionForm extends ConfirmFormBase {

  use WorkspaceSafeFormTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The tempstore.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The selection, in the entity_id => langcodes format.
   *
   * @var array
   */
  protected $selection = [];

  /**
   * The entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * Constructs a new DeleteMultiple object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $temp_store_factory, MessengerInterface $messenger) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->tempStore = $temp_store_factory->get('media_folders_update_multiple_confirm');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->selection = $this->tempStore->get($this->currentUser->id() . ':media');

    if (empty($this->selection)) {
      return new RedirectResponse($this->getCancelUrl()
        ->setAbsolute()
        ->toString());
    }

    $items = [];
    foreach ($this->selection as $id => $entity) {
      if (!isset($items[$id])) {
        $items[$id] = $entity->label();
      }
    }

    $form['entities'] = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];

    $folder_array = [0 => $this->t('Root')];
    $folders = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('media_folders_folder', 0, NULL, FALSE);
    foreach ($folders as $folder) {
      $depth = ($folder->depth + 1);
      $folder_array[$folder->tid] = str_repeat('-', $depth) . ' ' . $folder->name;
    }

    $form['target_folder'] = [
      '#type' => 'select',
      '#options' => $folder_array,
      '#title' => $this->t('Target folder'),
      '#required' => TRUE,
    ];
    $build = parent::buildForm($form, $form_state);
    unset($build['description']);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $target = $form_state->getValue('target_folder');
    $total_count = 0;
    $update_entities = [];
    $inaccessible_entities = [];

    foreach ($this->selection as $id => $entity) {
      if (!$entity->access('update', $this->currentUser)) {
        $inaccessible_entities[] = $entity;
        continue;
      }
      if (!isset($update_entities[$id])) {
        $entity->field_folders_folder->target_id = (!empty($target)) ? $target : NULL;
        $entity->save();
        $update_entities[$id] = $entity;
        $total_count++;
      }
    }

    if ($total_count) {
      $this->messenger->addStatus($this->getUpdatedMessage($total_count));
    }
    if ($inaccessible_entities) {
      $this->messenger->addWarning($this->getInaccessibleMessage(count($inaccessible_entities)));
    }
    $this->tempStore->delete($this->currentUser->id());
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_delete_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $entity_type = $this->entityTypeManager->getDefinition('media');
    return $this->formatPlural(count($this->selection), 'Choose the target folder for this @item?', 'Choose the target folder for these @items?', [
      '@item' => $entity_type->getSingularLabel(),
      '@items' => $entity_type->getPluralLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $entity_type = $this->entityTypeManager->getDefinition('media');
    if ($entity_type->hasLinkTemplate('collection')) {
      return new Url('entity.media.collection');
    }
    else {
      return new Url('<front>');
    }
  }

  /**
   * Returns the message to show the user after an item was updated.
   *
   * @param int $count
   *   Count of updated items.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The item deleted message.
   */
  protected function getUpdatedMessage($count) {
    return $this->formatPlural($count, 'Updated @count item.', 'Updated @count items.');
  }

  /**
   * Returns the message to show the user when an item has not been updated.
   *
   * @param int $count
   *   Count of deleted translations.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The item inaccessible message.
   */
  protected function getInaccessibleMessage($count) {
    return $this->formatPlural($count, "@count item has not been updated because you do not have the necessary permissions.", "@count items have not been updated because you do not have the necessary permissions.");
  }

}
