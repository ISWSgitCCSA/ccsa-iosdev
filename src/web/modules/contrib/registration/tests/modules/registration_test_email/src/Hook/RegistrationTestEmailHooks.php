<?php

namespace Drupal\registration_test_email\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;

/**
 * Hook implementations for registration email testing.
 */
class RegistrationTestEmailHooks {

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_mail_alter().
   */
  #[Hook('mail_alter')]
  public function mailAlter(&$message) {
    // Store the "From" header so assertions can be made against it later.
    $this->state->set('registration_from', $message['headers']['From']);
  }

}
