<?php

namespace Drupal\registration\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Mail hook implementations for registration.
 */
class RegistrationMailHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    // Replace tokens and set headers for HTML.
    $langcode = $message['langcode'];
    $variables = $params['token_entities'];
    $options = ['langcode' => $langcode, 'clear' => TRUE];
    $token_service = \Drupal::token();
    $message['subject'] = PlainTextOutput::renderFromHtml($token_service->replace($params['subject'], $variables, $options));
    $message['body'][] = new FormattableMarkup($token_service->replace($params['message'], $variables, $options), []);
    if ($this->configFactory->get('registration.settings')->get('html_email')) {
      $message['headers']['Content-Type'] = 'text/html; charset=UTF-8; format=flowed; delsp=yes';
    }
    // Replace the From header if directed to do so by configuration.
    if ($this->configFactory->get('registration.settings')->get('replace_from_header')) {
      if (!empty($message['params']['from'])) {
        $message['headers']['From'] = $message['params']['from'];
      }
    }
    // Set a basic From header if one is not set yet.
    elseif (!empty($message['params']['from']) && empty($message['headers']['From'])) {
      $message['headers']['From'] = $message['params']['from'];
    }
  }

}
