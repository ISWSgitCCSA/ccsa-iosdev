<?php

namespace Drupal\registration_waitlist_test_event\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for registration wait list event testing.
 */
class RegistrationWaitlistTestEventHooks {

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    // Add a base registration field to node entities.
    if ($entity_type->id() === 'node') {
      $default_settings = [
        'status' => [
          'value' => TRUE,
        ],
        'capacity' => [
          0 => [
            'value' => 5,
          ],
        ],
        'maximum_spaces' => [
          0 => [
            'value' => 5,
          ],
        ],
        'from_address' => [
          0 => [
            'value' => 'test@example.com',
          ],
        ],
        'registration_waitlist_enable' => [
          'value' => TRUE,
        ],
        'registration_waitlist_capacity' => [
          0 => [
            'value' => 10,
          ],
        ],
        'multiple_registrations' => [
          'value' => TRUE,
        ],
        'registration_waitlist_autofill' => [
          'value' => TRUE,
        ],
        'registration_waitlist_autofill_state' => [
          0 => [
            'value' => 'complete',
          ],
        ],
      ];
      $fields['event_registration'] = BaseFieldDefinition::create('registration')
        ->setLabel(t('Registration'))
        ->setDefaultValue([
          'registration_settings' => serialize($default_settings),
        ]);
    }
    return $fields;
  }

}
