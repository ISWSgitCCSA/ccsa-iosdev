<?php

/**
 * @file
 * Builds placeholder replacement tokens for registration-related data.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\registration\Hook\RegistrationTokensHooks;

/**
 * Implements hook_token_info().
 */
#[LegacyHook]
function registration_token_info(): array {
  return \Drupal::service(RegistrationTokensHooks::class)->tokenInfo();
}

/**
 * Implements hook_token_info_alter().
 */
#[LegacyHook]
function registration_token_info_alter(&$info): void {
  \Drupal::service(RegistrationTokensHooks::class)->tokenInfoAlter($info);
}

/**
 * Implements hook_tokens().
 */
#[LegacyHook]
function registration_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  return \Drupal::service(RegistrationTokensHooks::class)->tokens($type, $tokens, $data, $options, $bubbleable_metadata);
}
