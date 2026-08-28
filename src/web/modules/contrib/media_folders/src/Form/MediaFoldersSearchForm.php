<?php

namespace Drupal\media_folders\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder.
 */
class MediaFoldersSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media-folders-search-form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['search'] = [
      '#type' => 'textfield',
      '#default_value' => '',
      '#title' => $this->t('Search folder'),
      '#title_display' => 'hidden',
      '#attributes' => [
        'placeholder' => $this->t('Search folder'),
      ],
    ];

    $form['search_submit'] = [
      '#type' => 'button',
      '#ajax' => [
        'callback' => '::submitCallback',
        'wrapper' => 'search-form',
        'message' => $this->t('Searching...'),
      ],
      '#value' => $this->t('Full search'),
    ];

    return $form;
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
    if (empty($values['search'])) {
      $response->addCommand(new MessageCommand($this->t('Please enter some search terms.'), '#folders-messages', ['type' => 'error']));
    }
    else {
      $response->addCommand(new InvokeCommand('#board', 'ajaxSearchRecursive', [$values['search']]));
    }

    return $response;
  }

}
