<?php

/**
 * @file
 * Views integration for Entity Registration.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\registration\Hook\RegistrationViewsHooks;

/**
 * Implements hook_views_data().
 */
#[LegacyHook]
function registration_views_data() {
  return \Drupal::service(RegistrationViewsHooks::class)->viewsData();
}

/**
 * Implements hook_views_data_alter().
 */
#[LegacyHook]
function registration_views_data_alter(array &$data) {
  \Drupal::service(RegistrationViewsHooks::class)->viewsDataAlter($data);
}
