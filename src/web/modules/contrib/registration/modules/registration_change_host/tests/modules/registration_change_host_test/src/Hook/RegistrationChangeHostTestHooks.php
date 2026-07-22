<?php

namespace Drupal\registration_change_host_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Hook implementations for registration change host testing.
 */
class RegistrationChangeHostTestHooks {

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    // Add fields to node entities to be used by event subscriber
    // to get parameters of a possible host.
    if ($entity_type->id() === 'node') {
      $fields['host_possible'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Host is possible'));
      $fields['host_violation'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Host violation'));
      $fields['host_label'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Host label'));
      $fields['host_description'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Host description'));
    }
    return $fields;
  }

  /**
   * Implements hook_entity_bundle_field_info().
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, $bundle, &$base_field_definitions) {
    $fields = [];
    if ($entity_type->id() === 'node') {
      // Make the registration bundle name match the node bundle name.
      $field = clone $base_field_definitions['event_registration'];
      $default = $field->getDefaultValueLiteral();
      $default['registration_type'] = $bundle;
      $field->setDefaultValue($default);
      $fields['event_registration'] = $field;
    }
    if ($entity_type->id() === 'registration' && $bundle === 'event') {
      $fields['event_text'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Event text'))
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE);
    }
    if ($entity_type->id() === 'registration' && $bundle === 'conference') {
      $fields['conference_text'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Conference text'))
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE);
    }
    return $fields;
  }

  /**
   * Implements hook_entity_field_storage_info().
   */
  #[Hook('entity_field_storage_info')]
  public function entityFieldStorageInfo(EntityTypeInterface $entity_type) {
    $fields = [];
    if ($entity_type->id() === 'registration') {
      $fields['event_text'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Event text'))
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE);
      $fields['conference_text'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Conference text'))
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
        ])
        ->setDisplayConfigurable('form', TRUE);
    }

    return $fields;
  }

  /**
   * Implements hook_registration_presave().
   */
  #[Hook('registration_presave')]
  public function registrationPresave(RegistrationInterface $registration) {
    if ($this->state->get('registration_change_host_test.throw_exception_on_save', FALSE)) {
      throw new \Exception('Test exception in hook_registration_presave');
    }
  }

}
