<?php

namespace Drupal\ios_importer\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the IOS CSV importer form.
 */
class ImporterForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The key/value store factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValueFactory;

  /**
   * Constructs the form.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    KeyValueFactoryInterface $key_value_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->keyValueFactory = $key_value_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('keyvalue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ios_importer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Upload a CSV export to create or update conference content.') . '</p>',
    ];

    $form['import_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => [
        'speaker' => $this->t('Speaker'),
        'session' => $this->t('Session'),
        'sponsor' => $this->t('Sponsor'),
      ],
      '#empty_option' => $this->t('- Select a content type -'),
      '#required' => TRUE,
    ];

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV file'),
      '#description' => $this->t('Upload the CSV export for the selected content type.'),
      '#upload_location' => 'temporary://ios_importer/',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'csv',
        ],
      ],
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import CSV'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $file_ids = $form_state->getValue('csv_file');

    if (empty($file_ids[0])) {
      $form_state->setErrorByName('csv_file', $this->t('Please upload a CSV file.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $import_type = (string) $form_state->getValue('import_type');
    $file_ids = $form_state->getValue('csv_file');

    if (empty($file_ids[0])) {
      return;
    }

    // Only supported content types may reach an importer.
    if (!in_array($import_type, ['speaker', 'session', 'sponsor'], TRUE)) {
      $this->messenger()->addError(
        $this->t('@type importing has not been implemented yet. No content was changed.', [
          '@type' => ucfirst($import_type),
        ]),
      );
      $form_state->setRedirect('ios_importer.import');
      return;
    }

    $file = File::load($file_ids[0]);

    if (!$file) {
      $this->messenger()->addError($this->t('The uploaded CSV file could not be loaded.'));
      $form_state->setRedirect('ios_importer.import');
      return;
    }

    $handle = fopen($file->getFileUri(), 'r');

    if ($handle === FALSE) {
      $this->messenger()->addError($this->t('The uploaded CSV file could not be opened.'));
      $form_state->setRedirect('ios_importer.import');
      return;
    }

    $raw_headers = fgetcsv($handle, 0, ',', '"', '');

    if ($raw_headers === FALSE || empty($raw_headers)) {
      fclose($handle);
      $this->messenger()->addError($this->t('The CSV file does not contain a valid header row.'));
      $form_state->setRedirect('ios_importer.import');
      return;
    }

    $headers = $this->normalizeHeaders($raw_headers);

    if (count($headers) !== count(array_unique($headers))) {
      fclose($handle);
      $this->messenger()->addError(
        $this->t('The CSV contains duplicate column names. No rows were imported.'),
      );
      $form_state->setRedirect('ios_importer.import');
      return;
    }

    if ($import_type === 'session') {
      $this->processSessionCsv($handle, $headers);
      fclose($handle);
      $form_state->setRedirect('ios_importer.import');
      return;
    }

    if ($import_type === 'sponsor') {
      $this->processSponsorCsv($handle, $headers);
      fclose($handle);
      $form_state->setRedirect('ios_importer.import');
      return;
    }

    if (!in_array('speaker_id', $headers, TRUE)) {
      fclose($handle);
      $this->messenger()->addError(
        $this->t('Required column "speaker_id" is missing. No rows were imported.'),
      );
      $form_state->setRedirect('ios_importer.import');
      return;
    }

    $created_ids = [];
    $updated_ids = [];
    $seen_language_rows = [];
    $row_errors = [];
    $data_row_count = 0;
    $csv_row_number = 1;

    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== FALSE) {
      $csv_row_number++;

      if (!$this->rowHasContent($values)) {
        continue;
      }

      $data_row_count++;
      $row = $this->combineRowWithHeaders($headers, $values);

      if ($row === NULL) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'speaker_id' => '',
          'message' => (string) $this->t('The number of values does not match the CSV header.'),
        ];
        continue;
      }

      $speaker_id = trim((string) ($row['speaker_id'] ?? ''));

      if ($speaker_id === '') {
        $row_errors[] = [
          'row' => $csv_row_number,
          'speaker_id' => '',
          'message' => (string) $this->t('Speaker ID is required.'),
        ];
        continue;
      }

      $langcode = strtolower(trim((string) ($row['language'] ?? '')));
      $langcode = $langcode === '' ? 'en' : $langcode;

      if (!in_array($langcode, ['en', 'fr'], TRUE)) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'speaker_id' => $speaker_id,
          'message' => (string) $this->t('Language must be "en", "fr", or blank.'),
        ];
        continue;
      }

      $language_key = $speaker_id . '|' . $langcode;

      if (isset($seen_language_rows[$language_key])) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'speaker_id' => $speaker_id,
          'message' => (string) $this->t(
            'This CSV already contains a @language row for this Speaker ID.',
            ['@language' => strtoupper($langcode)],
          ),
        ];
        continue;
      }

      $seen_language_rows[$language_key] = TRUE;

      $result = $this->importSpeakerRow($row, $speaker_id, $langcode);

      if (!$result['success']) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'speaker_id' => $speaker_id,
          'message' => $result['message'],
        ];
        continue;
      }

      if ($result['created']) {
        $created_ids[$speaker_id] = TRUE;
        unset($updated_ids[$speaker_id]);
      }
      elseif (!isset($created_ids[$speaker_id])) {
        $updated_ids[$speaker_id] = TRUE;
      }
    }

    fclose($handle);

    $touched_ids = array_values(array_unique(array_merge(
      array_keys($created_ids),
      array_keys($updated_ids),
    )));
    $pending_count = $this->countPendingSessionReferencesForSpeakers($touched_ids);

    if (!empty($created_ids) || !empty($updated_ids)) {
      $this->messenger()->addStatus(
        $this->t(
          'Import complete. @created speakers created. @updated speakers updated. @pending Session relationships are waiting for their Sessions to be imported.',
          [
            '@created' => count($created_ids),
            '@updated' => count($updated_ids),
            '@pending' => $pending_count,
          ],
        ),
      );
    }

    if (!empty($row_errors)) {
      $this->messenger()->addWarning(
        $this->formatPlural(
          count($row_errors),
          '1 row was not imported.',
          '@count rows were not imported.',
        ),
      );

      foreach ($row_errors as $error) {
        $speaker_part = $error['speaker_id'] !== ''
          ? ' - ' . $error['speaker_id']
          : '';

        $this->messenger()->addError(
          $this->t('Row @row@id - @message', [
            '@row' => $error['row'],
            '@id' => $speaker_part,
            '@message' => $error['message'],
          ]),
        );
      }
    }

    if ($data_row_count === 0 && empty($row_errors)) {
      $this->messenger()->addWarning($this->t('The CSV did not contain any data rows.'));
    }

    // Post/Redirect/Get clears the selected type and uploaded file after import.
    $form_state->setRedirect('ios_importer.import');
  }

  /**
   * Imports one Speaker CSV row.
   */
  protected function importSpeakerRow(array $row, string $speaker_id, string $langcode): array {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $matches = $node_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'speaker')
      ->condition('field_speaker_id', $speaker_id)
      ->execute();

    if (count($matches) > 1) {
      return [
        'success' => FALSE,
        'created' => FALSE,
        'message' => (string) $this->t('More than one Drupal Speaker node uses this Speaker ID.'),
      ];
    }

    $created = count($matches) === 0;
    $title = trim((string) ($row['title'] ?? ''));

    if ($created && ($title === '' || $title === '[clear]')) {
      return [
        'success' => FALSE,
        'created' => FALSE,
        'message' => (string) $this->t('A new Speaker requires a title/name.'),
      ];
    }

    if (!$created && $title === '[clear]') {
      return [
        'success' => FALSE,
        'created' => FALSE,
        'message' => (string) $this->t('The Speaker title cannot be cleared.'),
      ];
    }

    // Validate taxonomy before changing the node.
    $speaker_type_tid = NULL;
    $speaker_type = array_key_exists('speaker_type', $row)
      ? trim((string) $row['speaker_type'])
      : NULL;

    if ($speaker_type !== NULL && $speaker_type !== '' && $speaker_type !== '[clear]') {
      $term_result = $this->findSpeakerTypeTermId($speaker_type);

      if (!$term_result['success']) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => $term_result['message'],
        ];
      }

      $speaker_type_tid = $term_result['tid'];
    }

    // Validate/resolve Session references before changing the node.
    $session_result = [
      'success' => TRUE,
      'changed' => FALSE,
      'clear' => FALSE,
      'desired_ids' => [],
      'resolved_nids' => [],
      'missing_ids' => [],
      'message' => '',
    ];

    if (array_key_exists('session_ids', $row)) {
      $session_result = $this->resolveSessionIds(trim((string) $row['session_ids']));

      if (!$session_result['success']) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => $session_result['message'],
        ];
      }
    }

    // Validate social links before changing the node.
    $social_links_result = [
      'success' => TRUE,
      'changed' => FALSE,
      'clear' => FALSE,
      'values' => [],
      'message' => '',
    ];

    if (array_key_exists('social_links', $row)) {
      $social_links_result = $this->prepareSocialLinks(trim((string) $row['social_links']));

      if (!$social_links_result['success']) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => $social_links_result['message'],
        ];
      }
    }

    if ($created) {
      // English is the base language. Both EN and FR translations are created.
      $node = $node_storage->create([
        'type' => 'speaker',
        'langcode' => 'en',
        'title' => $title,
        'field_speaker_id' => $speaker_id,
        'status' => 0,
      ]);

      if (!$node->hasTranslation('fr')) {
        $node->addTranslation('fr', ['title' => $title, 'status' => 0]);
      }
    }
    else {
      $node = $node_storage->load(reset($matches));

      if (!$node) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t('The matching Speaker node could not be loaded.'),
        ];
      }

      if (!$node->hasTranslation($langcode)) {
        $node->addTranslation($langcode, [
          'title' => $node->label(),
          'status' => 0,
        ]);
      }
    }

    $translation = $node->getTranslation($langcode);

    try {
      // Stable ID is entity-level and comes from the CSV.
      $node->set('field_speaker_id', $speaker_id);

      // Blank means leave unchanged. A supplied title updates this translation.
      if ($title !== '') {
        $translation->setTitle($title);
      }

      $this->applyTextField(
        $node,
        $translation,
        'field_organization_role',
        $row['organization'] ?? NULL,
      );

      $this->applyTextField(
        $node,
        $translation,
        'field_role',
        $row['role'] ?? NULL,
      );

      $this->applyBodyField(
        $node,
        $translation,
        $row['body'] ?? NULL,
      );

      if ($speaker_type !== NULL) {
        if ($speaker_type === '[clear]') {
          $this->setFieldValue($node, $translation, 'field_speaker_type', []);
        }
        elseif ($speaker_type !== '') {
          $this->setFieldValue(
            $node,
            $translation,
            'field_speaker_type',
            [['target_id' => $speaker_type_tid]],
          );
        }
      }

      if ($social_links_result['changed']) {
        $this->setFieldValue(
          $node,
          $translation,
          'field_social_links',
          $social_links_result['clear'] ? [] : $social_links_result['values'],
        );
      }

      if ($session_result['changed']) {
        $this->replaceSpeakerSessionReferences(
          $node,
          $session_result['clear'] ? [] : $session_result['resolved_nids'],
        );
      }

      $node->save();

      if ($session_result['changed']) {
        $pending_store = $this->keyValueFactory->get('ios_importer.pending_session_references');

        if ($session_result['clear'] || empty($session_result['missing_ids'])) {
          $pending_store->delete($speaker_id);
        }
        else {
          $pending_store->set($speaker_id, [
            'speaker_id' => $speaker_id,
            'desired_session_ids' => $session_result['desired_ids'],
            'missing_session_ids' => $session_result['missing_ids'],
          ]);
        }
      }
    }
    catch (\Throwable $exception) {
      return [
        'success' => FALSE,
        'created' => FALSE,
        'message' => (string) $this->t('Drupal could not save this row: @message', [
          '@message' => $exception->getMessage(),
        ]),
      ];
    }

    return [
      'success' => TRUE,
      'created' => $created,
      'message' => '',
    ];
  }

  /**
   * Processes a bilingual Sponsor CSV without modifying manually managed fields.
   *
   * Required columns: sponsor_id, title, participation_role.
   * Optional columns: language (defaults to en), body.
   */
  protected function processSponsorCsv($handle, array $headers): void {
    foreach (['sponsor_id', 'title', 'participation_role'] as $required) {
      if (!in_array($required, $headers, TRUE)) {
        $this->messenger()->addError(
          $this->t('Required column "@column" is missing. No rows were imported.', [
            '@column' => $required,
          ]),
        );
        return;
      }
    }

    $created_ids = [];
    $updated_ids = [];
    $seen_language_rows = [];
    $row_errors = [];
    $data_row_count = 0;
    $csv_row_number = 1;

    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== FALSE) {
      $csv_row_number++;

      if (!$this->rowHasContent($values)) {
        continue;
      }

      $data_row_count++;
      $row = $this->combineRowWithHeaders($headers, $values);

      if ($row === NULL) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'sponsor_id' => '',
          'message' => (string) $this->t('The number of values does not match the CSV header.'),
        ];
        continue;
      }

      $sponsor_id = trim((string) ($row['sponsor_id'] ?? ''));

      if ($sponsor_id === '') {
        $row_errors[] = [
          'row' => $csv_row_number,
          'sponsor_id' => '',
          'message' => (string) $this->t('Sponsor ID is required.'),
        ];
        continue;
      }

      $langcode = strtolower(trim((string) ($row['language'] ?? '')));
      $langcode = $langcode === '' ? 'en' : $langcode;

      if (!in_array($langcode, ['en', 'fr'], TRUE)) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'sponsor_id' => $sponsor_id,
          'message' => (string) $this->t('Language must be "en", "fr", or blank.'),
        ];
        continue;
      }

      $language_key = $sponsor_id . '|' . $langcode;

      if (isset($seen_language_rows[$language_key])) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'sponsor_id' => $sponsor_id,
          'message' => (string) $this->t(
            'This CSV already contains a @language row for this Sponsor ID.',
            ['@language' => strtoupper($langcode)],
          ),
        ];
        continue;
      }

      $seen_language_rows[$language_key] = TRUE;
      $result = $this->importSponsorRow($row, $sponsor_id, $langcode);

      if (!$result['success']) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'sponsor_id' => $sponsor_id,
          'message' => $result['message'],
        ];
        continue;
      }

      if ($result['created']) {
        $created_ids[$sponsor_id] = TRUE;
        unset($updated_ids[$sponsor_id]);
      }
      elseif (!isset($created_ids[$sponsor_id])) {
        $updated_ids[$sponsor_id] = TRUE;
      }
    }

    if (!empty($created_ids) || !empty($updated_ids)) {
      $this->messenger()->addStatus(
        $this->t('Import complete. @created Sponsors created. @updated Sponsors updated.', [
          '@created' => count($created_ids),
          '@updated' => count($updated_ids),
        ]),
      );
    }

    if (!empty($row_errors)) {
      $this->messenger()->addWarning(
        $this->formatPlural(
          count($row_errors),
          '1 row was not imported.',
          '@count rows were not imported.',
        ),
      );

      foreach ($row_errors as $error) {
        $id_part = $error['sponsor_id'] !== ''
          ? ' - ' . $error['sponsor_id']
          : '';

        $this->messenger()->addError(
          $this->t('Row @row@id - @message', [
            '@row' => $error['row'],
            '@id' => $id_part,
            '@message' => $error['message'],
          ]),
        );
      }
    }

    if ($data_row_count === 0 && empty($row_errors)) {
      $this->messenger()->addWarning($this->t('The CSV did not contain any data rows.'));
    }
  }

  /**
   * Creates or updates a Sponsor and the specified language translation.
   *
   * Other Sponsor fields, especially commercial/package information and
   * Speaker references, are never modified by this importer.
   */
  protected function importSponsorRow(array $row, string $sponsor_id, string $langcode): array {
    try {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $matches = $node_storage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'sponsor')
        ->condition('field_sponsor_id', $sponsor_id)
        ->execute();

      if (count($matches) > 1) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t(
            'More than one Drupal Sponsor node uses this Sponsor ID.',
          ),
        ];
      }

      $created = count($matches) === 0;
      $title = trim((string) ($row['title'] ?? ''));

      if ($created && ($title === '' || $title === '[clear]')) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t('A new Sponsor requires a title.'),
        ];
      }

      if (!$created && $title === '[clear]') {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t('The Sponsor title cannot be cleared.'),
        ];
      }

      // Validate both taxonomy labels before writing anything to the node.
      // Only the existing Sponsor and Exhibitor terms are accepted.
      $role_value = trim((string) ($row['participation_role'] ?? ''));
      $role_tids = [];

      if ($created && ($role_value === '' || $role_value === '[clear]')) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t(
            'A new Sponsor requires a Participation Role: Sponsor, Exhibitor, or both.',
          ),
        ];
      }

      if ($role_value !== '' && $role_value !== '[clear]') {
        $role_labels = $this->parsePipeSeparatedValues($role_value);

        if (count($role_labels) === 0) {
          return [
            'success' => FALSE,
            'created' => FALSE,
            'message' => (string) $this->t(
              'Participation Role must be Sponsor, Exhibitor, or Sponsor|Exhibitor.',
            ),
          ];
        }

        foreach ($role_labels as $label) {
          if (!in_array(mb_strtolower($label), ['sponsor', 'exhibitor'], TRUE)) {
            return [
              'success' => FALSE,
              'created' => FALSE,
              'message' => (string) $this->t(
                'Unknown Participation Role "@role". Use Sponsor or Exhibitor.',
                ['@role' => $label],
              ),
            ];
          }

          $term_result = $this->findTaxonomyTermIdForField(
            'sponsor',
            'field_participation_role',
            $label,
          );

          if (!$term_result['success']) {
            return [
              'success' => FALSE,
              'created' => FALSE,
              'message' => $term_result['message'],
            ];
          }

          $role_tids[(string) $term_result['tid']] = [
            'target_id' => $term_result['tid'],
          ];
        }
      }

      if ($created) {
        // English base + French translation shell, both unpublished.
        $node = $node_storage->create([
          'type' => 'sponsor',
          'langcode' => 'en',
          'title' => $title,
          'field_sponsor_id' => $sponsor_id,
          'status' => 0,
        ]);

        if (!$node->hasTranslation('fr')) {
          $node->addTranslation('fr', ['title' => $title, 'status' => 0]);
        }
      }
      else {
        $node = $node_storage->load(reset($matches));

        if (!$node) {
          return [
            'success' => FALSE,
            'created' => FALSE,
            'message' => (string) $this->t('The matching Sponsor node could not be loaded.'),
          ];
        }

        if (!$node->hasTranslation($langcode)) {
          $node->addTranslation($langcode, [
            'title' => $node->label(),
            'status' => 0,
          ]);
        }
      }

      $translation = $node->getTranslation($langcode);
      $node->set('field_sponsor_id', $sponsor_id);

      if ($title !== '') {
        $translation->setTitle($title);
      }

      $this->applyBodyField($node, $translation, $row['body'] ?? NULL);

      // Blank preserves the existing role; [clear] removes all role terms.
      if ($role_value === '[clear]') {
        $this->setFieldValue($node, $translation, 'field_participation_role', []);
      }
      elseif ($role_value !== '') {
        $this->setFieldValue(
          $node,
          $translation,
          'field_participation_role',
          array_values($role_tids),
        );
      }

      $node->save();
    }
    catch (\Throwable $exception) {
      return [
        'success' => FALSE,
        'created' => FALSE,
        'message' => (string) $this->t(
          'Drupal could not save this Sponsor row: @message',
          ['@message' => $exception->getMessage()],
        ),
      ];
    }

    return [
      'success' => TRUE,
      'created' => $created,
      'message' => '',
    ];
  }

  /**
   * Processes a Session CSV after the header row has been read.
   *
   * Imports the Session's stable ID, translated content, existing taxonomy
   * references, and scheduled start/end times. Existing published content
   * retains its publication state on update; new content starts unpublished.
   */
  protected function processSessionCsv($handle, array $headers): void {
    if (!in_array('session_number', $headers, TRUE)) {
      $this->messenger()->addError(
        $this->t('Required column "session_number" is missing. No rows were imported.'),
      );
      return;
    }

    $created_ids = [];
    $updated_ids = [];
    $seen_language_rows = [];
    $row_errors = [];
    $data_row_count = 0;
    $csv_row_number = 1;

    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== FALSE) {
      $csv_row_number++;

      if (!$this->rowHasContent($values)) {
        continue;
      }

      $data_row_count++;
      $row = $this->combineRowWithHeaders($headers, $values);

      if ($row === NULL) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'session_number' => '',
          'message' => (string) $this->t('The number of values does not match the CSV header.'),
        ];
        continue;
      }

      $session_number = trim((string) ($row['session_number'] ?? ''));

      if ($session_number === '') {
        $row_errors[] = [
          'row' => $csv_row_number,
          'session_number' => '',
          'message' => (string) $this->t('Session Number is required.'),
        ];
        continue;
      }

      $langcode = strtolower(trim((string) ($row['language'] ?? '')));
      $langcode = $langcode === '' ? 'en' : $langcode;

      if (!in_array($langcode, ['en', 'fr'], TRUE)) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'session_number' => $session_number,
          'message' => (string) $this->t('Language must be "en", "fr", or blank.'),
        ];
        continue;
      }

      $language_key = $session_number . '|' . $langcode;

      if (isset($seen_language_rows[$language_key])) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'session_number' => $session_number,
          'message' => (string) $this->t(
            'This CSV already contains a @language row for this Session Number.',
            ['@language' => strtoupper($langcode)],
          ),
        ];
        continue;
      }

      $seen_language_rows[$language_key] = TRUE;

      $result = $this->importSessionRow($row, $session_number, $langcode);

      if (!$result['success']) {
        $row_errors[] = [
          'row' => $csv_row_number,
          'session_number' => $session_number,
          'message' => $result['message'],
        ];
        continue;
      }

      if ($result['created']) {
        $created_ids[$session_number] = TRUE;
        unset($updated_ids[$session_number]);
      }
      elseif (!isset($created_ids[$session_number])) {
        $updated_ids[$session_number] = TRUE;
      }
    }

    if (!empty($created_ids) || !empty($updated_ids)) {
      $this->messenger()->addStatus(
        $this->t(
          'Import complete. @created Sessions created. @updated Sessions updated.',
          [
            '@created' => count($created_ids),
            '@updated' => count($updated_ids),
          ],
        ),
      );
    }

    if (!empty($row_errors)) {
      $this->messenger()->addWarning(
        $this->formatPlural(
          count($row_errors),
          '1 row was not imported.',
          '@count rows were not imported.',
        ),
      );

      foreach ($row_errors as $error) {
        $session_part = $error['session_number'] !== ''
          ? ' - ' . $error['session_number']
          : '';

        $this->messenger()->addError(
          $this->t('Row @row@id - @message', [
            '@row' => $error['row'],
            '@id' => $session_part,
            '@message' => $error['message'],
          ]),
        );
      }
    }

    if ($data_row_count === 0 && empty($row_errors)) {
      $this->messenger()->addWarning($this->t('The CSV did not contain any data rows.'));
    }
  }

  /**
   * Imports one Session CSV row.
   */
  protected function importSessionRow(array $row, string $session_number, string $langcode): array {
    try {
      $node_storage = $this->entityTypeManager->getStorage('node');

      $matches = $node_storage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'session')
        ->condition('field_session_number', $session_number)
        ->execute();

      if (count($matches) > 1) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t(
            'More than one Drupal Session node uses this Session Number.',
          ),
        ];
      }

      $created = count($matches) === 0;
      $title = trim((string) ($row['title'] ?? ''));

      if ($created && ($title === '' || $title === '[clear]')) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t('A new Session requires a title.'),
        ];
      }

      if (!$created && $title === '[clear]') {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t('The Session title cannot be cleared.'),
        ];
      }

      // Taxonomy terms must already exist. The importer never creates them.
      $activity_type = array_key_exists('activity_type', $row)
        ? trim((string) $row['activity_type'])
        : NULL;
      $activity_type_tid = NULL;

      if ($activity_type !== NULL && $activity_type !== '' && $activity_type !== '[clear]') {
        $term_result = $this->findTaxonomyTermIdForField(
          'session',
          'field_activity_type',
          $activity_type,
        );

        if (!$term_result['success']) {
          return [
            'success' => FALSE,
            'created' => FALSE,
            'message' => $term_result['message'],
          ];
        }

        $activity_type_tid = $term_result['tid'];
      }

      $concurrent_session = array_key_exists('concurrent_session', $row)
        ? trim((string) $row['concurrent_session'])
        : NULL;
      $concurrent_session_tid = NULL;

      if (
        $concurrent_session !== NULL &&
        $concurrent_session !== '' &&
        $concurrent_session !== '[clear]'
      ) {
        $term_result = $this->findTaxonomyTermIdForField(
          'session',
          'field_concurrent_session',
          $concurrent_session,
        );

        if (!$term_result['success']) {
          return [
            'success' => FALSE,
            'created' => FALSE,
            'message' => $term_result['message'],
          ];
        }

        $concurrent_session_tid = $term_result['tid'];
      }

      // Validate CSV dates before writing anything to the Session node.
      $start_date = $this->parseSessionDateTime(
        $row['start_date_time'] ?? NULL,
        'start_date_time',
      );
      if (!$start_date['success']) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => $start_date['message'],
        ];
      }

      $end_date = $this->parseSessionDateTime(
        $row['end_date_time'] ?? NULL,
        'end_date_time',
      );
      if (!$end_date['success']) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => $end_date['message'],
        ];
      }

      // If both dates are supplied, they must form a valid time range.
      if (
        $start_date['value'] !== NULL &&
        $end_date['value'] !== NULL &&
        $end_date['timestamp'] < $start_date['timestamp']
      ) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t(
            'End Date/Time must be on or after Start Date/Time.',
          ),
        ];
      }

      if ($created) {
        $node = $node_storage->create([
          'type' => 'session',
          'langcode' => 'en',
          'title' => $title,
          'field_session_number' => $session_number,
          'status' => 0,
        ]);

        if (!$node->hasTranslation('fr')) {
          $node->addTranslation('fr', ['title' => $title, 'status' => 0]);
        }
      }
      else {
        $node = $node_storage->load(reset($matches));

        if (!$node) {
          return [
            'success' => FALSE,
            'created' => FALSE,
            'message' => (string) $this->t('The matching Session node could not be loaded.'),
          ];
        }

        if (!$node->hasTranslation($langcode)) {
          $node->addTranslation($langcode, [
            'title' => $node->label(),
            'status' => 0,
          ]);
        }
      }

      $translation = $node->getTranslation($langcode);

      $node->set('field_session_number', $session_number);

      if ($title !== '') {
        $translation->setTitle($title);
      }

      $this->applyBodyField(
        $node,
        $translation,
        $row['body'] ?? NULL,
      );

      // Import room text from the CSV's "room" column.
      // A blank cell preserves the current room; [clear] removes it.
      $this->applyTextField(
        $node,
        $translation,
        'field_room',
        $row['room'] ?? NULL,
      );

      if ($activity_type !== NULL) {
        if ($activity_type === '[clear]') {
          $this->setFieldValue($node, $translation, 'field_activity_type', []);
        }
        elseif ($activity_type !== '') {
          $this->setFieldValue(
            $node,
            $translation,
            'field_activity_type',
            [['target_id' => $activity_type_tid]],
          );
        }
      }

      if ($concurrent_session !== NULL) {
        if ($concurrent_session === '[clear]') {
          $this->setFieldValue($node, $translation, 'field_concurrent_session', []);
        }
        elseif ($concurrent_session !== '') {
          $this->setFieldValue(
            $node,
            $translation,
            'field_concurrent_session',
            [['target_id' => $concurrent_session_tid]],
          );
        }
      }

      // Blank date cells preserve existing values. [clear] removes them.
      if ($start_date['changed']) {
        $this->setFieldValue(
          $node,
          $translation,
          'field_start_date_time',
          $start_date['clear'] ? [] : ['value' => $start_date['value']],
        );
      }

      if ($end_date['changed']) {
        $this->setFieldValue(
          $node,
          $translation,
          'field_end_date_time',
          $end_date['clear'] ? [] : ['value' => $end_date['value']],
        );
      }

      // Also validate against any preserved date from an earlier import.
      $effective_start = $this->getSessionStoredTimestamp(
        $translation,
        'field_start_date_time',
      );
      $effective_end = $this->getSessionStoredTimestamp(
        $translation,
        'field_end_date_time',
      );

      if (
        $effective_start !== NULL &&
        $effective_end !== NULL &&
        $effective_end < $effective_start
      ) {
        return [
          'success' => FALSE,
          'created' => FALSE,
          'message' => (string) $this->t(
            'End Date/Time must be on or after Start Date/Time.',
          ),
        ];
      }

      $node->save();
    }
    catch (\Throwable $exception) {
      return [
        'success' => FALSE,
        'created' => FALSE,
        'message' => (string) $this->t(
          'Drupal could not save this Session row: @message',
          ['@message' => $exception->getMessage()],
        ),
      ];
    }

    return [
      'success' => TRUE,
      'created' => $created,
      'message' => '',
    ];
  }

  /**
   * Parses the time supplied by registration into Drupal's UTC storage format.
   *
   * Supported formats:
   * - YYYY-MM-DD HH:MM (preferred for future exports)
   * - M/D/YYYY H:MM (the existing sample CSV, month first)
   *
   * Missing/blank means preserve the current value. [clear] removes it.
   */
  protected function parseSessionDateTime(mixed $raw_value, string $column): array {
    $unchanged = [
      'success' => TRUE,
      'changed' => FALSE,
      'clear' => FALSE,
      'value' => NULL,
      'timestamp' => NULL,
      'message' => '',
    ];

    if ($raw_value === NULL || trim((string) $raw_value) === '') {
      return $unchanged;
    }

    $value = trim((string) $raw_value);

    if ($value === '[clear]') {
      return array_replace($unchanged, [
        'changed' => TRUE,
        'clear' => TRUE,
      ]);
    }

    // Parse in the Drupal site's configured timezone, not the server's UTC.
    $site_timezone = (string) (\Drupal::config('system.date')->get('timezone.default') ?: 'UTC');

    try {
      $timezone = new \DateTimeZone($site_timezone);
    }
    catch (\Throwable $exception) {
      return array_replace($unchanged, [
        'success' => FALSE,
        'message' => (string) $this->t(
          'The site timezone is invalid. Check Configuration > Regional and language > Regional settings.',
        ),
      ]);
    }

    $date = FALSE;
    $matches = [];

    if (
      preg_match(
        '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/',
        $value,
        $matches,
      )
    ) {
      [$full, $year, $month, $day, $hour, $minute] = $matches;
      if (
        checkdate((int) $month, (int) $day, (int) $year) &&
        (int) $hour < 24 &&
        (int) $minute < 60
      ) {
        $date = \DateTimeImmutable::createFromFormat(
          '!Y-m-d H:i',
          $value,
          $timezone,
        );
      }
    }
    elseif (
      preg_match(
        '/^(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{2})$/',
        $value,
        $matches,
      )
    ) {
      [$full, $month, $day, $year, $hour, $minute] = $matches;
      if (
        checkdate((int) $month, (int) $day, (int) $year) &&
        (int) $hour < 24 &&
        (int) $minute < 60
      ) {
        $date = \DateTimeImmutable::createFromFormat(
          '!n/j/Y G:i',
          $value,
          $timezone,
        );
      }
    }

    $parse_errors = \DateTimeImmutable::getLastErrors();
    if (
      $date === FALSE ||
      (
        is_array($parse_errors) &&
        ($parse_errors['error_count'] || $parse_errors['warning_count'])
      )
    ) {
      return array_replace($unchanged, [
        'success' => FALSE,
        'message' => (string) $this->t(
          'Invalid @column date/time "@value". Use YYYY-MM-DD HH:MM or M/D/YYYY H:MM (month first).',
          [
            '@column' => $column,
            '@value' => $value,
          ],
        ),
      ]);
    }

    $utc = $date->setTimezone(new \DateTimeZone('UTC'));

    return [
      'success' => TRUE,
      'changed' => TRUE,
      'clear' => FALSE,
      'value' => $utc->format('Y-m-d\TH:i:s'),
      'timestamp' => $utc->getTimestamp(),
      'message' => '',
    ];
  }

  /**
   * Reads the effective stored UTC datetime on the current translation.
   */
  protected function getSessionStoredTimestamp($translation, string $field_name): ?int {
    if (!$translation->hasField($field_name) || $translation->get($field_name)->isEmpty()) {
      return NULL;
    }

    $value = (string) $translation->get($field_name)->value;
    $date = \DateTimeImmutable::createFromFormat(
      '!Y-m-d\TH:i:s',
      $value,
      new \DateTimeZone('UTC'),
    );

    return $date === FALSE ? NULL : $date->getTimestamp();
  }

  /**
   * Finds a taxonomy term by any of its translated labels.
   *
   * The CSV can use one canonical taxonomy label in both language rows.
   * Drupal stores the term reference, and the term itself can render its
   * translated label on the translated page.
   */
  protected function findTaxonomyTermIdForField(
    string $bundle,
    string $field_name,
    string $label,
  ): array {
    $field_definitions = $this->entityFieldManager
      ->getFieldDefinitions('node', $bundle);

    if (!isset($field_definitions[$field_name])) {
      return [
        'success' => FALSE,
        'tid' => NULL,
        'message' => (string) $this->t(
          'Required taxonomy field "@field" does not exist on the @bundle content type.',
          [
            '@field' => $field_name,
            '@bundle' => ucfirst($bundle),
          ],
        ),
      ];
    }

    $handler_settings = $field_definitions[$field_name]
      ->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];

    if (empty($target_bundles)) {
      return [
        'success' => FALSE,
        'tid' => NULL,
        'message' => (string) $this->t(
          'Taxonomy field "@field" is not configured with a vocabulary.',
          ['@field' => $field_name],
        ),
      ];
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $wanted = mb_strtolower(trim($label));
    $matched_tids = [];

    foreach (array_keys($target_bundles) as $vocabulary_id) {
      $terms = $term_storage->loadTree($vocabulary_id, 0, NULL, TRUE);

      foreach ($terms as $term) {
        $labels = [$term->label()];

        if ($term->isTranslatable()) {
          foreach ($term->getTranslationLanguages() as $term_langcode => $language) {
            if ($term->hasTranslation($term_langcode)) {
              $labels[] = $term->getTranslation($term_langcode)->label();
            }
          }
        }

        foreach (array_unique($labels) as $candidate) {
          if (mb_strtolower(trim((string) $candidate)) === $wanted) {
            $matched_tids[(string) $term->id()] = (int) $term->id();
            break;
          }
        }
      }
    }

    if (count($matched_tids) === 0) {
      return [
        'success' => FALSE,
        'tid' => NULL,
        'message' => (string) $this->t(
          'Taxonomy value "@value" does not match an existing term for @field.',
          [
            '@value' => $label,
            '@field' => $field_name,
          ],
        ),
      ];
    }

    if (count($matched_tids) > 1) {
      return [
        'success' => FALSE,
        'tid' => NULL,
        'message' => (string) $this->t(
          'Taxonomy value "@value" matches more than one term for @field.',
          [
            '@value' => $label,
            '@field' => $field_name,
          ],
        ),
      ];
    }

    return [
      'success' => TRUE,
      'tid' => reset($matched_tids),
      'message' => '',
    ];
  }

  /**
   * Finds a Speaker Type taxonomy term by its English or French label.
   */
  protected function findSpeakerTypeTermId(string $label): array {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', 'speaker');

    if (!isset($field_definitions['field_speaker_type'])) {
      return [
        'success' => FALSE,
        'tid' => NULL,
        'message' => (string) $this->t('The Speaker Type field does not exist on the Speaker content type.'),
      ];
    }

    $handler_settings = $field_definitions['field_speaker_type']->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];

    if (empty($target_bundles)) {
      return [
        'success' => FALSE,
        'tid' => NULL,
        'message' => (string) $this->t('The Speaker Type field is not configured with a taxonomy vocabulary.'),
      ];
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $wanted = mb_strtolower(trim($label));
    $matched_tids = [];

    foreach (array_keys($target_bundles) as $vocabulary_id) {
      $terms = $term_storage->loadTree($vocabulary_id, 0, NULL, TRUE);

      foreach ($terms as $term) {
        $labels = [$term->label()];

        if ($term->isTranslatable()) {
          foreach ($term->getTranslationLanguages() as $langcode => $language) {
            if ($term->hasTranslation($langcode)) {
              $labels[] = $term->getTranslation($langcode)->label();
            }
          }
        }

        foreach (array_unique($labels) as $candidate) {
          if (mb_strtolower(trim((string) $candidate)) === $wanted) {
            $matched_tids[(string) $term->id()] = (int) $term->id();
            break;
          }
        }
      }
    }

    if (count($matched_tids) === 0) {
      return [
        'success' => FALSE,
        'tid' => NULL,
        'message' => (string) $this->t(
          'Speaker Type "@type" does not match an existing taxonomy term.',
          ['@type' => $label],
        ),
      ];
    }

    if (count($matched_tids) > 1) {
      return [
        'success' => FALSE,
        'tid' => NULL,
        'message' => (string) $this->t(
          'Speaker Type "@type" matches more than one taxonomy term.',
          ['@type' => $label],
        ),
      ];
    }

    return [
      'success' => TRUE,
      'tid' => reset($matched_tids),
      'message' => '',
    ];
  }

  /**
   * Resolves Session Numbers. Missing Sessions become pending references.
   */
  protected function resolveSessionIds(string $value): array {
    if ($value === '') {
      return [
        'success' => TRUE,
        'changed' => FALSE,
        'clear' => FALSE,
        'desired_ids' => [],
        'resolved_nids' => [],
        'missing_ids' => [],
        'message' => '',
      ];
    }

    if ($value === '[clear]') {
      return [
        'success' => TRUE,
        'changed' => TRUE,
        'clear' => TRUE,
        'desired_ids' => [],
        'resolved_nids' => [],
        'missing_ids' => [],
        'message' => '',
      ];
    }

    $desired_ids = $this->parsePipeSeparatedValues($value);
    $node_storage = $this->entityTypeManager->getStorage('node');
    $resolved_nids = [];
    $missing_ids = [];

    foreach ($desired_ids as $session_number) {
      $matches = $node_storage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'session')
        ->condition('field_session_number', $session_number)
        ->execute();

      if (count($matches) > 1) {
        return [
          'success' => FALSE,
          'changed' => FALSE,
          'clear' => FALSE,
          'desired_ids' => [],
          'resolved_nids' => [],
          'missing_ids' => [],
          'message' => (string) $this->t(
            'Session Number "@session" is used by more than one Session node in Drupal.',
            ['@session' => $session_number],
          ),
        ];
      }

      if (count($matches) === 1) {
        $resolved_nids[] = (int) reset($matches);
      }
      else {
        $missing_ids[] = $session_number;
      }
    }

    return [
      'success' => TRUE,
      'changed' => TRUE,
      'clear' => FALSE,
      'desired_ids' => $desired_ids,
      'resolved_nids' => $resolved_nids,
      'missing_ids' => $missing_ids,
      'message' => '',
    ];
  }

  /**
   * Replaces Session references while preserving any Activity references.
   */
  protected function replaceSpeakerSessionReferences($node, array $session_nids): void {
    $values = [];

    if ($node->hasField('field_activity_session')) {
      foreach ($node->get('field_activity_session')->referencedEntities() as $referenced_node) {
        if ($referenced_node->bundle() !== 'session') {
          $values[] = ['target_id' => $referenced_node->id()];
        }
      }
    }

    foreach (array_unique($session_nids) as $session_nid) {
      $values[] = ['target_id' => $session_nid];
    }

    $node->set('field_activity_session', $values);
  }

  /**
   * Prepares a pipe-separated list of social links.
   */
  protected function prepareSocialLinks(string $value): array {
    if ($value === '') {
      return [
        'success' => TRUE,
        'changed' => FALSE,
        'clear' => FALSE,
        'values' => [],
        'message' => '',
      ];
    }

    if ($value === '[clear]') {
      return [
        'success' => TRUE,
        'changed' => TRUE,
        'clear' => TRUE,
        'values' => [],
        'message' => '',
      ];
    }

    $links = $this->parsePipeSeparatedValues($value);
    $values = [];

    foreach ($links as $link) {
      if (!preg_match('#^https?://#i', $link)) {
        return [
          'success' => FALSE,
          'changed' => FALSE,
          'clear' => FALSE,
          'values' => [],
          'message' => (string) $this->t(
            'Social link "@link" must begin with http:// or https://.',
            ['@link' => $link],
          ),
        ];
      }

      $values[] = [
        'uri' => $link,
        'title' => '',
        'options' => [],
      ];
    }

    return [
      'success' => TRUE,
      'changed' => TRUE,
      'clear' => FALSE,
      'values' => $values,
      'message' => '',
    ];
  }

  /**
   * Applies a plain-text CSV value.
   *
   * Blank leaves the existing value alone. [clear] clears the field.
   */
  protected function applyTextField($node, $translation, string $field_name, mixed $raw_value): void {
    if ($raw_value === NULL) {
      return;
    }

    $value = trim((string) $raw_value);

    if ($value === '') {
      return;
    }

    if ($value === '[clear]') {
      $this->setFieldValue($node, $translation, $field_name, []);
      return;
    }

    $this->setFieldValue($node, $translation, $field_name, $value);
  }

  /**
   * Applies Body as plain text.
   */
  protected function applyBodyField($node, $translation, mixed $raw_value): void {
    if ($raw_value === NULL) {
      return;
    }

    $value = trim((string) $raw_value);

    if ($value === '') {
      return;
    }

    if ($value === '[clear]') {
      $this->setFieldValue($node, $translation, 'body', []);
      return;
    }

    $this->setFieldValue($node, $translation, 'body', [
      'value' => $value,
      'format' => 'plain_text',
    ]);
  }

  /**
   * Sets a field on the correct translation scope.
   */
  protected function setFieldValue($node, $translation, string $field_name, mixed $value): void {
    if (!$node->hasField($field_name)) {
      throw new \RuntimeException(sprintf(
        'Field "%s" does not exist on the %s content type.',
        $field_name,
        $node->bundle(),
      ));
    }

    $definition = $node->getFieldDefinition($field_name);

    if ($definition->isTranslatable()) {
      $translation->set($field_name, $value);
    }
    else {
      $node->set($field_name, $value);
    }
  }

  /**
   * Counts pending Session references for Speakers touched in this import.
   */
  protected function countPendingSessionReferencesForSpeakers(array $speaker_ids): int {
    $store = $this->keyValueFactory->get('ios_importer.pending_session_references');
    $count = 0;

    foreach ($speaker_ids as $speaker_id) {
      $pending = $store->get($speaker_id);

      if (
        is_array($pending) &&
        !empty($pending['missing_session_ids']) &&
        is_array($pending['missing_session_ids'])
      ) {
        $count += count($pending['missing_session_ids']);
      }
    }

    return $count;
  }

  /**
   * Parses pipe-separated values and removes duplicates.
   */
  protected function parsePipeSeparatedValues(string $value): array {
    $values = array_map('trim', explode('|', $value));
    $values = array_filter($values, static fn(string $item): bool => $item !== '');
    return array_values(array_unique($values));
  }

  /**
   * Normalizes CSV header names.
   */
  protected function normalizeHeaders(array $headers): array {
    $normalized = [];

    foreach ($headers as $index => $header) {
      $header = (string) $header;

      if ($index === 0) {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
      }

      $header = strtolower(trim($header));
      $header = str_replace([' ', '-'], '_', $header);
      $normalized[] = $header;
    }

    return $normalized;
  }

  /**
   * Combines one CSV row with its normalized headers.
   */
  protected function combineRowWithHeaders(array $headers, array $values): ?array {
    if (count($headers) !== count($values)) {
      return NULL;
    }

    return array_combine($headers, $values);
  }

  /**
   * Determines whether a CSV row contains data.
   */
  protected function rowHasContent(array $row): bool {
    foreach ($row as $value) {
      if (trim((string) $value) !== '') {
        return TRUE;
      }
    }

    return FALSE;
  }

}
