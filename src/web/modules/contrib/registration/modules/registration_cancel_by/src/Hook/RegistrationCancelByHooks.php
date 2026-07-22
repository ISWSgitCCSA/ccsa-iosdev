<?php

namespace Drupal\registration_cancel_by\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for registration cancel by.
 */
class RegistrationCancelByHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    if ($entity_type->id() === 'registration_settings') {
      $fields['cancel_by'] = BaseFieldDefinition::create('datetime')
        ->setLabel(t('Cancel by date'))
        ->setDescription($this->t('When registrations must be canceled by.'))
        ->setRequired(FALSE)
        ->setDisplayOptions('form', [
          'type' => 'datetime_default',
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }
    return $fields;
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    // Validate the "cancel by" date in registration settings.
    $entity_types['registration_settings']->addConstraint('CancelByConstraint');
  }

}
