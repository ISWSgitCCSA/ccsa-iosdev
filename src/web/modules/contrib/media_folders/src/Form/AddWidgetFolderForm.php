<?php

namespace Drupal\media_folders\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Form builder.
 */
class AddWidgetFolderForm extends FormBase {

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
      '#default_value' => '',
      '#maxlength' => 40,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#required' => FALSE,
      '#title' => $this->t('Description'),
      '#default_value' => '',
    ];

    $form['parent'] = [
      '#type' => 'hidden',
      '#value' => ($folder) ? $folder : 0,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save'] = [
      '#type' => 'button',
      '#ajax' => [
        'callback' => '::submitCallback',
        'wrapper' => 'add-folder-form',
      ],
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    _media_folders_validate_folder_name($form_state, $values['parent'], $values['name']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
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

    $term_values = [
      'parent' => [$values['parent']],
      'name' => $values['name'],
      'description' => $values['description'],
      'vid' => 'media_folders_folder',
    ];

    $term = Term::create($term_values)->save();
    if ($term) {
      $response->addCommand(new MessageCommand($this->t('Folder created'), '#folders-messages'));
      $response->addCommand(new InvokeCommand('.navbar-folder[data-id=' . $values['parent'] . '] a', 'click'));
    }
    $response->addCommand(new CloseDialogCommand('.ui-dialog:has(.add-folder-form) .ui-dialog-content'));

    return $response;
  }

}
