<?php

namespace Drupal\registration_workflow;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\Transition;
use Drupal\workflows\WorkflowInterface;

/**
 * Validates whether a certain state transition is allowed.
 */
class StateTransitionValidation implements StateTransitionValidationInterface {

  public function __construct(
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(RegistrationInterface $registration, ?AccountInterface $user = NULL): array {
    if (is_null($user)) {
      $user = $this->currentUser;
    }

    $workflow = $registration->getWorkflow();
    $current_state = $registration->getState();

    return array_filter($current_state->getTransitions(), function (Transition $transition) use ($workflow, $user) {
      return $user->hasPermission('use ' . $workflow->id() . ' ' . $transition->id() . ' transition');
    });
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionValid(WorkflowInterface $workflow, StateInterface $original_state, StateInterface $new_state, RegistrationInterface $registration, ?AccountInterface $user = NULL): bool {
    if (is_null($user)) {
      $user = $this->currentUser;
    }

    if ($workflow->getTypePlugin()->hasTransitionFromStateToState($original_state->id(), $new_state->id())) {
      $transition = $workflow->getTypePlugin()->getTransitionFromStateToState($original_state->id(), $new_state->id());
      return $user->hasPermission('use ' . $workflow->id() . ' ' . $transition->id() . ' transition');
    }
    return FALSE;
  }

}
