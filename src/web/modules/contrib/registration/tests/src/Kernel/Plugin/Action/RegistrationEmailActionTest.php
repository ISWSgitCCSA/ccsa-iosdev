<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Action;

use Drupal\Core\Action\ActionManager;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Plugin\Action\RegistrationEmailAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the registration 'send email' action.
 */
#[CoversClass(RegistrationEmailAction::class)]
#[Group('registration')]
class RegistrationEmailActionTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The action manager.
   */
  protected ActionManager $actionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('filter');

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->actionManager = $this->container->get('plugin.manager.action');
  }

  /**
   * Tests access to and execution of the 'send email' action.
   */
  public function testEmailAction() {
    $action = $this->actionManager->createInstance('registration_send_email_action');
    $action->setConfiguration([
      'subject' => 'My test email',
      'message' => [
        'value' => 'This is a test email message.',
        'format' => 'plain_text',
      ],
    ]);

    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', 1);
    $registration->save();
    $this->assertTrue($action->access($registration));

    $result = $action->execute($registration);
    $this->assertTrue($result);
  }

}
