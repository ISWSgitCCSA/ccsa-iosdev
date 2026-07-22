<?php

/**
 * @file
 * Post update hooks for symfony_mailer_log.
 */

/**
 * Define the 'enable' option.
 */
function symfony_mailer_log_post_update_enable_logging() {
  $config = \Drupal::configFactory()
    ->getEditable('symfony_mailer_log.settings');

  $config
    ->set('enable', TRUE)
    ->save();

  return t('Updated the Symfony Mailer Log configuration for the "enable" option.');
}
