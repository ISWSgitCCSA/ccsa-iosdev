<?php

namespace Drupal\media_folders\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form builder.
 */
class EditFolderForm extends FormBase {

  /**
   * The Get EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
  public function getFormId() {
    return 'add-folder-form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, mixed $folder = NULL) {
    $form['errors'] = [
      '#markup' => '<div id="folders-form-errors"></div>',
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Name'),
      '#default_value' => $folder->getName(),
      '#maxlength' => 40,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#required' => FALSE,
      '#title' => $this->t('Description'),
      '#default_value' => (!empty($folder->description->value)) ? strip_tags($folder->description->value) : '',
    ];

    $form['folder'] = [
      '#type' => 'hidden',
      '#value' => $folder->id(),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($values['folder']);
    _media_folders_validate_folder_name($form_state, $term->parent->target_id, $values['name'], $term->id());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->disableRedirect();
    $values = $form_state->getValues();
    $response = new AjaxResponse();

    $errors = $form_state->getErrors();
    if (!empty($errors)) {
      foreach ($errors as $error) {
        $response->addCommand(new MessageCommand($error, '#folders-form-errors', ['type' => 'error']));
      }
      $this->messenger()->deleteAll();
      return $response;
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($values['folder']);
    $term->name->value = $values['name'];
    $term->description->value = $values['description'];
    $term->save();

    $response->addCommand(new MessageCommand($this->t('Folder saved'), '#folders-messages'));
    $target = (!empty($this->getRequest()->query->get('media-folders-edit'))) ? $term->parent->target_id : $term->id();
    $response->addCommand(new InvokeCommand('.navbar-folder[data-id=' . $target . '] a', 'click'));
    $response->addCommand(new CloseDialogCommand('.ui-dialog:has(.add-folder-form) .ui-dialog-content'));

    return $response;
  }

}
