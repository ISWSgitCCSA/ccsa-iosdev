<?php

namespace Drupal\registration\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Field hook implementations for registration.
 */
class RegistrationFieldHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_field_info_entity_type_ui_definitions_alter().
   */
  #[Hook('field_info_entity_type_ui_definitions_alter')]
  public function fieldInfoEntityTypeUiDefinitionsAlter(array &$ui_definitions, string $entity_type_id): void {
    if ($entity_type_id === 'registration') {
      // Cannot add a registration field to a registration type.
      unset($ui_definitions['registration']);
    }
    // Cannot add a registration field to registration settings.
    if ($entity_type_id === 'registration_settings') {
      unset($ui_definitions['registration']);
    }
    // Cannot add a registration field to an entity type without an "id" key.
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!$entity_type->getKey('id')) {
      unset($ui_definitions['registration']);
    }
  }

  /**
   * Implements hook_field_widget_complete_WIDGET_TYPE_form_alter().
   */
  #[Hook('field_widget_complete_datetime_default_form_alter')]
  public function fieldWidgetCompleteDatetimeDefaultFormAlter(&$field_widget_complete_form, FormStateInterface $form_state, $context): void {
    // Add timezone to the datetime widgets when editing registration settings.
    if ($context['items']
      ->getFieldDefinition()
      ->getTargetEntityTypeId() == 'registration_settings') {
      $field_widget_complete_form['widget'][0]['#description'] .= ' ' . $this->t('(This uses the @timezone timezone.)', [
        '@timezone' => date_default_timezone_get(),
      ]);
    }
  }

}
