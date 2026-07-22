<?php

namespace Drupal\Tests\registration_purger\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\user\UserInterface;

/**
 * Provides a base class for Registration Scheduled Action kernel tests.
 */
abstract class RegistrationPurgerKernelTestBase extends RegistrationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_purger',
  ];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
    $this->adminUser = $admin_user;

    $this->installConfig('registration_purger');
  }

}
