<?php

namespace Drupal\registration\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Defines the HostEntityCacheContext for "per host entity" caching.
 *
 * Cache context ID: 'host_entity'.
 */
class HostEntityCacheContext implements CacheContextInterface {

  /**
   * The host entity.
   */
  protected HostEntityInterface $hostEntity;

  public function __construct(
    protected ContextRepositoryInterface $contextRepository,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RegistrationManagerInterface $registrationManager,
  ) {
    // Get the contexts for registration enabled entity types.
    $entity_contexts = array_map(function (EntityTypeInterface $entity_type) {
      return 'entity:' . $entity_type->id();
    }, $this->registrationManager->getRegistrationEnabledEntityTypes());

    // Get all populated contexts.
    $context_ids = array_keys($this->contextRepository->getAvailableContexts());
    $populated_contexts = array_filter($this->contextRepository->getRuntimeContexts($context_ids), function (ContextInterface $context) {
      return $context->hasContextValue();
    });

    // Get the host entity for the first populated registration enabled context.
    foreach ($populated_contexts as $context) {
      if (in_array($context->getContextDefinition()->getDataType(), $entity_contexts)) {
        $this->hostEntity = $this->entityTypeManager
          ->getHandler($context->getContextValue()->getEntityTypeId(), 'registration_host_entity')
          ->createHostEntity($context->getContextValue());
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): TranslatableMarkup {
    return new TranslatableMarkup('Host entity');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    if (isset($this->hostEntity)) {
      return 'host_entity:' . $this->hostEntity->getEntityTypeId() . ':' . $this->hostEntity->id();
    }
    return 'host_entity:none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    $cacheable_metadata = new CacheableMetadata();
    if (isset($this->hostEntity)) {
      $cacheable_metadata->setCacheTags($this->hostEntity->getEntity()->getCacheTags());
    }
    return $cacheable_metadata;
  }

}
