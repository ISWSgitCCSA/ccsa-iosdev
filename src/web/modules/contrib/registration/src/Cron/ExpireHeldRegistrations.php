<?php

namespace Drupal\registration\Cron;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Queue registrations for possible hold expiration.
 *
 * @see \Drupal\registration\Plugin\QueueWorker\ExpireHeldRegistrations
 */
class ExpireHeldRegistrations {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Run this task.
   */
  public function run() {
    $queue = $this->queueFactory->get('registration.expire_held_registrations');

    // Clear existing queue items to avoid reprocessing.
    $queue->deleteQueue();

    // Re-fill the queue with any held registrations.
    $registration_type_storage = $this->entityTypeManager->getStorage('registration_type');
    $registration_types = $registration_type_storage->loadMultiple();

    // Processed per type, since the states could act differently
    // for different types if they are using different workflows.
    foreach ($registration_types as $registration_type) {
      /** @var \Drupal\registration\Entity\RegistrationTypeInterface $registration_type **/
      $held_states = $registration_type->getHeldStates();
      if (!empty($held_states) && $registration_type->getHeldExpirationTime()) {
        $registration_storage = $this->entityTypeManager->getStorage('registration');
        $registrations = $registration_storage->loadByProperties([
          'type' => $registration_type->id(),
          'workflow' => $registration_type->getWorkflowId(),
          'state' => array_keys($held_states),
        ]);
        foreach ($registrations as $registration) {
          $item = $registration->id();
          $queue->createItem($item);
        }
      }
    }
  }

}
