<?php

declare(strict_types=1);

namespace Drupal\registration\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Theme hook implementations for registration.
 */
class RegistrationThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'registration_form' => [
        'render element' => 'form',
      ],
      'registration' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessRegistration',
      ],
    ];
  }

  /**
   * Prepares variables for a registration.
   *
   * Default template: registration.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessRegistration(array &$variables): void {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $variables['elements']['#registration'];

    $variables['registration_entity'] = $registration;
    $variables['registration'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['registration'][$key] = $variables['elements'][$key];
    }
    // Override the label for the host entity to be more descriptive.
    // For example, display "Event:" instead of "Host entity:", if
    // the registration is for a node instance of type "Event".
    if (!empty($variables['registration']['host_entity'])) {
      if (isset($variables['registration']['host_entity']['#object'])) {
        $entity = $variables['registration']['host_entity']['#object'];
        if ($entity instanceof RegistrationInterface) {
          if ($label = $entity->getHostEntityTypeLabel()) {
            $variables['registration']['host_entity']['#title'] = $label;
          }
        }
      }
    }
  }

}
