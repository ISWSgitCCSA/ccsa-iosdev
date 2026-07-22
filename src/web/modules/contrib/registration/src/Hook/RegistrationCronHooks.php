<?php

namespace Drupal\registration\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\registration\Cron\ExpireHeldRegistrations;
use Drupal\registration\Cron\SendReminders;
use Drupal\registration\Cron\SetAndForget;

/**
 * Cron hook implementations for registration.
 */
class RegistrationCronHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ExpireHeldRegistrations $expireHeldRegistrations,
    protected SendReminders $sendReminders,
    // @phpstan-ignore parameter.deprecatedClass
    protected SetAndForget $setAndForget,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->sendReminders->run();
    $this->expireHeldRegistrations->run();

    // The "set and forget" functionality is deprecated.
    // @see https://www.drupal.org/node/3506953
    $config = $this->configFactory->get('registration.settings');
    if ($config->get('set_and_forget')) {
      // @phpstan-ignore method.deprecatedClass
      $this->setAndForget->run();
    }
  }

}
