<?php

namespace Drupal\registration\Cron;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Queue\QueueFactory;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Queue host entities that need reminders.
 *
 * @see \Drupal\registration\Plugin\QueueWorker\SendReminders
 */
class SendReminders {

  public function __construct(
    protected Connection $database,
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Run this task.
   */
  public function run() {
    $queue = $this->queueFactory->get('registration.send_reminders');

    // Establish the current time in the storage timezone and format.
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);
    $now_date = $now->format($storage_format);

    // Set a 2-day range so very old reminders are not sent.
    $minus_2_days = new DrupalDateTime('-2 days', $storage_timezone);
    $minus_2_days_date = $minus_2_days->format($storage_format);

    // Clear existing queue items to avoid reprocessing.
    $queue->deleteQueue();

    // Re-fill the queue with host entities that need reminders.
    $query = $this->database->select('registration_settings_field_data', 'r')
      ->fields('r')
      ->condition('send_reminder', 1)
      ->condition('reminder_date', $now_date, '<=')
      ->condition('reminder_date', $minus_2_days_date, '>');
    $result = $query->execute();

    foreach ($result as $record) {
      $message = [
        'value' => $record->reminder_template__value,
        'format' => $record->reminder_template__format,
      ];
      $item = [
        'entity_type_id' => $record->entity_type_id,
        'entity_id' => $record->entity_id,
        'langcode' => $record->langcode,
        'message' => $message,
      ];
      $queue->createItem($item);
    }
  }

}
