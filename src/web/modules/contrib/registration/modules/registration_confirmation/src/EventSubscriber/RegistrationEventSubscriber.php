<?php

namespace Drupal\registration_confirmation\EventSubscriber;

use Drupal\Core\Action\ActionManager;
use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration\Event\RegistrationEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'plugin.manager.action')]
    protected ActionManager $actionManager,
    #[Autowire(service: 'registration_confirmation.logger')]
    protected LoggerInterface $logger,
  ) {}

  /**
   * Process update.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function onUpdate(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $from_state = isset($registration->original) ? $registration->original->getState()->id() : NULL;
    $to_state = $registration->getState()->id();
    // Send a confirmation email for newly completed registrations if this is
    // enabled for the registration type.
    if (($from_state !== $to_state) && $registration->isComplete()) {
      $registration_type = $registration->getType();
      if ($registration_type->getThirdPartySetting('registration_confirmation', 'enable')) {
        $configuration['recipient'] = $registration->getEmail();
        $configuration['subject'] = $registration_type->getThirdPartySetting('registration_confirmation', 'subject');
        $configuration['message'] = $registration_type->getThirdPartySetting('registration_confirmation', 'message');
        $configuration['mail_tag'] = 'registration_confirmation';
        $configuration['log_message'] = FALSE;
        $action = $this->actionManager->createInstance('registration_send_email_action');
        $action->setConfiguration($configuration);
        if ($action->execute($registration)) {
          $this->logger->info('Sent registration confirmation email to %recipient', [
            '%recipient' => $configuration['recipient'],
          ]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_INSERT => 'onUpdate',
      RegistrationEvents::REGISTRATION_UPDATE => 'onUpdate',
    ];
  }

}
