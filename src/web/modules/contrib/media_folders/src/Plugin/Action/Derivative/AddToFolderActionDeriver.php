<?php

namespace Drupal\media_folders\Plugin\Action\Derivative;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides an action deriver that finds entity types with delete form.
 */
class AddToFolderActionDeriver extends AddToFolderActionDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (empty($this->derivatives)) {
      $definitions = [];
      foreach ($this->getApplicableEntityTypes() as $entity_type_id => $entity_type) {
        $definition = $base_plugin_definition;
        $definition['type'] = $entity_type_id;
        $definition['label'] = $this->t('Add media to folder');
        $definition['confirm_form_route_name'] = 'media_folders.update_multiple_action';
        $definitions[$entity_type_id] = $definition;
      }
      $this->derivatives = $definitions;
    }

    return $this->derivatives;
  }

  /**
   * {@inheritdoc}
   */
  protected function isApplicable(EntityTypeInterface $entity_type) {
    return $entity_type->hasLinkTemplate('delete-multiple-form');
  }

}
