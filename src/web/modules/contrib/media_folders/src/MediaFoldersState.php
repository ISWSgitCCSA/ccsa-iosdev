<?php

namespace Drupal\media_folders;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * {@inheritdoc}
 */
class MediaFoldersState extends ParameterBag implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $parameters = []) {
    $this->validateRequiredParameters($parameters['media_folders_opener_id'], $parameters['media_folders_allowed_types'], $parameters['media_folders_selected_type'], $parameters['media_folders_remaining']);
    $parameters += [
      'media_folders_opener_parameters' => [],
    ];
    parent::__construct($parameters);
    $this->set('hash', $this->getHash());
  }

  /**
   * {@inheritdoc}
   */
  public static function create($opener_id, array $allowed_media_type_ids, $selected_type_id, $remaining_slots, array $opener_parameters = [], $settings = []) {
    $state = new static([
      'media_folders_opener_id' => $opener_id,
      'media_folders_allowed_types' => $allowed_media_type_ids,
      'media_folders_selected_type' => $selected_type_id,
      'media_folders_remaining' => $remaining_slots,
      'media_folders_opener_parameters' => $opener_parameters,
      'widget_settings' => $settings,
    ]);
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromRequest(Request $request) {
    $query = $request->query;

    $state = static::create(
      $query->get('media_folders_opener_id'),
      $query->all('media_folders_allowed_types'),
      $query->get('media_folders_selected_type'),
      $query->get('media_folders_remaining'),
      $query->all('media_folders_opener_parameters'),
      $query->all('widget_settings')
    );

    if (!$state->isValidHash($query->get('hash'))) {
      throw new BadRequestHttpException("Invalid media folders parameters specified.");
    }

    $state->replace($query->all());
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateRequiredParameters($opener_id, array $allowed_media_type_ids, $selected_type_id, $remaining_slots) {
    if (!is_string($opener_id) || empty(trim($opener_id))) {
      throw new \InvalidArgumentException('The opener ID parameter is required and must be a string.');
    }

    if (empty($allowed_media_type_ids) || !is_array($allowed_media_type_ids)) {
      throw new \InvalidArgumentException('The allowed types parameter is required and must be an array of strings.');
    }
    foreach ($allowed_media_type_ids as $allowed_media_type_id) {
      if (!is_string($allowed_media_type_id) || empty(trim($allowed_media_type_id))) {
        throw new \InvalidArgumentException('The allowed types parameter is required and must be an array of strings.');
      }
    }

    if (!is_string($selected_type_id) || empty(trim($selected_type_id))) {
      throw new \InvalidArgumentException('The selected type parameter is required and must be a string.');
    }
    if (!in_array($selected_type_id, $allowed_media_type_ids, TRUE)) {
      throw new \InvalidArgumentException('The selected type parameter must be present in the list of allowed types.');
    }

    if (!is_numeric($remaining_slots)) {
      throw new \InvalidArgumentException('The remaining slots parameter is required and must be numeric.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getHash() {
    $allowed_media_type_ids = array_values($this->getAllowedTypeIds());
    sort($allowed_media_type_ids);
    $opener_parameters = $this->getOpenerParameters();
    ksort($opener_parameters);
    $hash = implode(':', [
      $this->getOpenerId(),
      implode(':', $allowed_media_type_ids),
      $this->getSelectedTypeId(),
      $this->getAvailableSlots(),
      serialize($opener_parameters),
    ]);

    // @phpstan-ignore-next-line
    return Crypt::hmacBase64($hash, \Drupal::service('private_key')->get() . Settings::getHashSalt());
  }

  /**
   * {@inheritdoc}
   */
  public function isValidHash($hash) {
    return hash_equals($this->getHash(), $hash);
  }

  /**
   * {@inheritdoc}
   */
  public function getOpenerId() {
    return $this->get('media_folders_opener_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedTypeIds() {
    return $this->all('media_folders_allowed_types');
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectedTypeId() {
    return $this->get('media_folders_selected_type');
  }

  /**
   * {@inheritdoc}
   */
  public function hasSlotsAvailable() {
    return $this->getAvailableSlots() !== 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableSlots() {
    return $this->getInt('media_folders_remaining');
  }

  /**
   * {@inheritdoc}
   */
  public function getOpenerParameters() {
    return $this->all('media_folders_opener_parameters');
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetSettings() {
    return $this->get('widget_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.query_args'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
