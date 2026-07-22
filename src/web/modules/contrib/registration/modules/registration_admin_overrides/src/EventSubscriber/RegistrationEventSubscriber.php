<?php

namespace Drupal\registration_admin_overrides\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected RegistrationOverrideCheckerInterface $overrideChecker,
  ) {}

  /**
   * Alters the state set by a registration wait list presave.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterWaitListState(RegistrationDataAlterEvent $event) {
    $state = (string) $event->getData();
    $context = $event->getContext();

    // If an existing registration is about to be wait listed, see if an
    // override can place the registration in the originally desired active
    // state instead.
    if ($state == 'waitlist') {
      $registration = $context['registration'];
      if (!$registration->isNew() && $registration->getState()->isActive()) {
        if ($this->canOverride($context, 'capacity')) {
          $event->setData($registration->getState()->id());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'registration_waitlist.registration.presave' => 'alterWaitListState',
    ];
  }

  /**
   * Determines if the current user can override a given registration setting.
   *
   * @param array $context
   *   The event context.
   * @param string $setting
   *   The name of the setting.
   *
   * @return bool
   *   TRUE if the user can override the setting, FALSE otherwise.
   */
  protected function canOverride(array $context, string $setting): bool {
    return $this->overrideChecker->accountCanOverride($context['host_entity'], $this->currentUser, $setting, $context['registration'] ?? NULL);
  }

}
