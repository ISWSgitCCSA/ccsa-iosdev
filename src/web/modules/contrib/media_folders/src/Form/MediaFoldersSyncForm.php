<?php

namespace Drupal\media_folders\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sync form.
 */
class MediaFoldersSyncForm extends FormBase {

  /**
   * The Get EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $bundle_info,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfo = $bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_folders_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['sync'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('This will create a folder for each media type and add all media of the type into this folder.'),
    ];
    $form['sync']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Sync'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operations = [];

    $bundles = $this->bundleInfo->getBundleInfo('media');
    foreach ($bundles as $bundle_id => $bundle) {
      $folder_exists = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'media_folders_folder',
        'name' => $bundle['label'],
        'parent' => [0],
      ]);

      if ($folder_exists) {
        continue;
      }

      $term_values = [
        'parent' => [0],
        'name' => $bundle['label'],
        'description' => '',
        'vid' => 'media_folders_folder',
      ];
      $term = Term::create($term_values);
      $term->save();

      $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['bundle' => $bundle_id]);
      if (!empty($media)) {
        foreach ($media as $media_item) {
          $operations[] = [
            '\Drupal\media_folders\Form\MediaFoldersSyncForm::syncItem',
            [
              $media_item,
              $term->id(),
            ],
          ];
        }
      }
    }

    if (!empty($operations)) {
      $batch = [
        'title' => $this->t('Syncing media items'),
        'operations' => $operations ,
        'init_message'     => $this->t('Commencing'),
        'progress_message' => $this->t('Processed @current out of @total.'),
        'error_message'    => $this->t('An error occurred during processing'),
        'finished' => '\Drupal\media_folders\Form\MediaFoldersSyncForm::syncMediaFinishedCallback',
      ];
      batch_set($batch);
    }
    else {
      $this->messenger()->addWarning($this->t('No media items to sync!'));
    }

    $form_state->setRedirect('media_folders.collection');
  }

  /**
   * {@inheritdoc}
   */
  public static function syncItem($media_item, $term_id, &$context) {
    $media_item->field_folders_folder->target_id = $term_id;
    $media_item->save();

    $context['results']['mids'][$media_item->id()] = $media_item->id();
    $context['message'] = t('Running media "@id"', ['@id' => $media_item->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public static function syncMediaFinishedCallback($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      if (!empty($results['mids'])) {
        $messenger->addMessage(t('@count items processed.', [
          '@count' => count($results['mids']),
        ]));
      }
    }
    else {
      $error_operation = reset($operations);
      $messenger->addMessage(t('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]));
    }
  }

}
