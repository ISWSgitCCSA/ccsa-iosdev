<?php

namespace Drupal\registration_scheduled_action\Cron;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Queue objects with active registration schedules.
 *
 * @see \Drupal\registration_scheduled_action\Plugin\QueueWorker\ScheduledActionWorker
 */
class RegistrationSchedule {

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    // @todo Remove the next line once the module only supports Drupal 11.3+.
    #[Autowire(service: 'keyvalue.expirable')]
    protected KeyValueExpirableFactoryInterface $keyValueFactory,
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Run this task.
   */
  public function run() {
    $scheduled_actions = $this->entityTypeManager
      ->getStorage('registration_scheduled_action')
      ->loadByProperties([
        'status' => TRUE,
      ]);
    foreach ($scheduled_actions as $scheduled_action) {
      $plugin = $scheduled_action->getPlugin();
      $query = $plugin->getQuery($scheduled_action);
      $collection = $plugin->getKeyValueStoreCollectionName();
      $key_value_store = $this->keyValueFactory->get($collection);
      $key_field_name = $plugin->getQueryUniqueKeyColumnName();

      $result = $query->execute();
      foreach ($result as $object) {
        $record = (array) $object;

        // Only queue the item if it wasn't previously processed. The queue
        // worker records each processed item in the key value store.
        $key = $scheduled_action->getKeyValueStoreKeyName($record[$key_field_name]);
        if (!$key_value_store->has($key)) {
          $data = [
            'scheduled_action_id' => $scheduled_action->id(),
            'query_result_record' => $record,
          ];
          $this->queueFactory->get('registration_scheduled_action.process_schedule')->createItem($data);

          // Mark the item as queued to prevent duplicate processing.
          $key_value_store->setWithExpire($key, 'queued', $plugin->getKeyValueStoreExpirationTime());
        }
      }
    }
  }

}
