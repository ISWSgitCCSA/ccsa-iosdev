<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\FieldType;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Plugin\Field\FieldType\HostEntityItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'registration_host_entity' field type.
 */
#[CoversClass(HostEntityItem::class)]
#[Group('registration')]
class HostEntityItemTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * Tests a host entity item.
   */
  public function testHostEntityItem() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $node = $registration->getHostEntity()->getEntity();

    // Get the host entity.
    $item = $registration->get('host_entity')->first();
    $this->assertFalse($item->isEmpty());
    $this->assertSame($node, $item->entity);
    $this->assertSame($node, $item->getValue());

    // Delete the host entity.
    $node->delete();
    $item->reset();
    $this->assertTrue($item->isEmpty());
    $this->assertNull($item->getValue());

    // Set the host entity.
    $node2 = $this->createAndSaveNode();
    $registration2 = $this->createAndSaveRegistration($node2);
    $node2 = $registration2->getHostEntity()->getEntity();
    $item2 = $registration2->get('host_entity')->first();
    $items = $registration->get('host_entity');
    // Set the value via the item list.
    $items->setValue($node2);
    $this->assertSame($items->first()->getValue(), $item2->getValue());
    $item = $items->first();
    $this->assertSame($item->getValue(), $item2->getValue());
    // Set the value directly.
    $item->reset();
    $item->setValue($node2);
    $this->assertSame($item->getValue(), $item2->getValue());
  }

}
