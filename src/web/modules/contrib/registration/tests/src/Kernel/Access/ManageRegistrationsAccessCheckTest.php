<?php

namespace Drupal\Tests\registration\Kernel\Access;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\node\NodeInterface;
use Drupal\registration\Access\ManageRegistrationsAccessCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests the 'manage registrations' access check.
 */
#[CoversClass(ManageRegistrationsAccessCheck::class)]
#[Group('registration')]
class ManageRegistrationsAccessCheckTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;

  /**
   * The host entity.
   */
  protected NodeInterface $node;

  /**
   * The access checker.
   */
  protected ManageRegistrationsAccessCheck $accessChecker;

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->node = $this->createAndSaveNode();
    $this->accessChecker = new ManageRegistrationsAccessCheck($this->container->get('registration.manager'));
  }

  /**
   * Data provider for testManageRegistrationsAccess.
   */
  public static function manageRegistrationsAccessProvider(): array {
    $manage = self::basicManageScenarios('manage');
    $manage += [
      'manage: manage conference registration' => [
        'permissions' => ['manage conference registration'],
        'host_access' => FALSE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
      'manage: manage own conference registration without host update' => [
        'permissions' => ['manage own conference registration'],
        'host_access' => FALSE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'manage: manage own conference registration with host update' => [
        'permissions' => ['manage own conference registration'],
        'host_access' => TRUE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
    ];

    $settings = self::basicManageScenarios('settings');
    $settings += self::specificManageScenarios('settings');

    $broadcast = self::basicManageScenarios('broadcast');
    $broadcast += self::specificManageScenarios('broadcast');

    return array_merge($manage, $settings, $broadcast);
  }

  /**
   * Get basic manage scenarios relevant to all manage routes.
   *
   * @param string $route_name
   *   The route name.
   *
   * @return array
   *   The scenarios.
   */
  protected static function basicManageScenarios(string $route_name): array {
    $basic_scenarios = [
      'administer registration' => [
        'permissions' => ['administer registration'],
        'host_access' => FALSE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
      'administer conference registration' => [
        'permissions' => ['administer conference registration'],
        'host_access' => FALSE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
      'administer own conference registration without host update' => [
        'permissions' => ['administer own conference registration'],
        'host_access' => FALSE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'administer own conference registration with host update' => [
        'permissions' => ['administer own conference registration'],
        'host_access' => TRUE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'administer own conference registration settings without host update' => [
        'permissions' => ['administer own conference registration settings'],
        'host_access' => FALSE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'administer own conference registration settings with host update' => [
        'permissions' => ['administer own conference registration settings'],
        'host_access' => TRUE,
        'route' => 'manage',
        'expected' => TRUE,
      ],
      'update conference registration' => [
        'permissions' => ['update any conference registration'],
        'host_access' => TRUE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'view conference registration' => [
        'permissions' => ['view any conference registration'],
        'host_access' => TRUE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
      'no permissions manage' => [
        'permissions' => [],
        'host_access' => TRUE,
        'route' => 'manage',
        'expected' => FALSE,
      ],
    ];

    $route_scenarios = [];
    foreach ($basic_scenarios as $key => $scenario) {
      $new_key = $route_name . ': ' . $key;
      $scenario['route'] = $route_name;
      $route_scenarios[$new_key] = $scenario;
    }
    return $route_scenarios;
  }

  /**
   * Get specific manage scenarios relevant to a given route.
   *
   * @param string $route_name
   *   The route name.
   *
   * @return array
   *   The scenarios.
   */
  protected static function specificManageScenarios(string $route_name): array {
    return [
      "$route_name: manage conference registration" => [
        'permissions' => ['manage conference registration'],
        'host_access' => FALSE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage own conference registration without host update" => [
        'permissions' => ['manage own conference registration'],
        'host_access' => FALSE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage own conference registration with host update" => [
        'permissions' => ['manage own conference registration'],
        'host_access' => TRUE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage conference registration $route_name" => [
        'permissions' => ["manage conference registration $route_name"],
        'host_access' => FALSE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage conference registration" => [
        'permissions' => ["manage conference registration", "manage conference registration $route_name"],
        'host_access' => FALSE,
        'route' => $route_name,
        'expected' => TRUE,
      ],
      "$route_name: manage own conference registration without host update" => [
        'permissions' => ['manage own conference registration', "manage conference registration $route_name"],
        'host_access' => FALSE,
        'route' => $route_name,
        'expected' => FALSE,
      ],
      "$route_name: manage own conference registration with host update" => [
        'permissions' => ['manage own conference registration', "manage conference registration $route_name"],
        'host_access' => TRUE,
        'route' => $route_name,
        'expected' => TRUE,
      ],
    ];
  }

  /**
   * Tests the 'manage registrations' access check.
   *
   * @dataProvider manageRegistrationsAccessProvider
   */
  #[DataProvider('manageRegistrationsAccessProvider')]
  public function testManageRegistrationsAccess(array $permissions, bool $host_access, string $route, bool $expected): void {
    if ($route === 'settings') {
      $route = 'registration_settings';
    }
    $route_match = $this->createMock(RouteMatch::class);
    $route_match->method('getParameters')->willReturn(new ParameterBag(['node' => $this->node]));
    $route_match->method('getRouteName')->willReturn("entity.node.registration.$route");

    $user_permissions = $permissions;
    if ($host_access) {
      $user_permissions[] = 'bypass node access';
    }
    $account = $this->createUser($user_permissions);
    $access_result = $this->accessChecker->access($account, $route_match);

    $this->assertSame($expected, $access_result->isAllowed(), "Unexpected result for permissions: " . implode(', ', $permissions) . ", on $route route.");
  }

}
