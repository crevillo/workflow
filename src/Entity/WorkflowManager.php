<?php

/**
 * @file
 * Contains Drupal\workflow\Entity\WorkflowManager.
 */

namespace Drupal\workflow\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;

/**
 * Manages entity type plugin definitions.
 *
 */
class WorkflowManager implements WorkflowManagerInterface { // extends EntityManager {

  /**
   * Constructs a new Entity plugin manager.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  function executeScheduledTransitionsBetween($start = 0, $end = 0) {
    $clear_cache = FALSE;

    // If the time now is greater than the time to execute a transition, do it.
    foreach (WorkflowScheduledTransition::loadBetween($start, $end) as $scheduled_transition) {
      $field_name = $scheduled_transition->getFieldName();
      $entity = $scheduled_transition->getEntity();

      // If user didn't give a comment, create one.
      $comment = $scheduled_transition->getComment();
      if (empty($comment)) {
        $scheduled_transition->addDefaultComment();
      }


      // Make sure transition is still valid: the entity must still be in
      // the state it was in, when the transition was scheduled.
      $current_sid = workflow_node_current_state($entity, $field_name);
      if ($current_sid == $scheduled_transition->getFromSid()) {

        // Do transition. Force it because user who scheduled was checked.
        // The scheduled transition is not scheduled anymore, and is also deleted from DB.
        // A watchdog message is created with the result.
        workflow_execute_transition($scheduled_transition, TRUE);

        if (!$field_name) {
          $clear_cache = TRUE;
        }
      }
      else {
        // Entity is not in the same state it was when the transition
        // was scheduled. Defer to the entity's current state and
        // abandon the scheduled transition.
        $scheduled_transition->delete();
      }
    }

    if ($clear_cache) {
      // Clear the cache so that if the transition resulted in a entity
      // being published, the anonymous user can see it.
      Cache::invalidateTags(array('rendered'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeTransitionsOfEntity(EntityInterface $entity) {
    // Avoid this hook on workflow objects.
    if (in_array($entity->getEntityTypeId(), [
      'workflow_workflow',
      'workflow_state',
      'workflow_config_transition',
      'workflow_transition',
      'workflow_scheduled_transition',
    ])) {
      return;
    }

    foreach (_workflow_info_fields($entity) as $field_name => $field_info) {
      /* @var $transition WorkflowTransitionInterface */
      $transition = $entity->$field_name->workflow['workflow_transition'];
      // Set the just-saved entity explicitly. Not necessary for update,
      // but upon insert, the old version didn't have an ID, yet.
      if ($transition) {
        // We come from Content edit page, from widget.
        $transition->setEntity($entity);
        $transition->execute();
      }
      else {
        // We come from WorkflowTransitionForm, which explicitly save the entity.
        // The transiition is executed by  the form.
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUser(AccountInterface $account) {
    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
    self::cancelUser([], $account, 'user_cancel_delete');
  }

  /**
   * {@inheritdoc}
   */
  public function cancelUser($edit, AccountInterface $account, $method) {

    switch ($method) {
      case 'user_cancel_block': // Disable the account and keep its content.
      case 'user_cancel_block_unpublish': // Disable the account and unpublish its content.
        // Do nothing.
        break;
      case 'user_cancel_reassign': // Delete the account and make its content belong to the Anonymous user.
      case 'user_cancel_delete': // Delete the account and its content.

        // Update tables for deleted account, move account to user 0 (anon.)
        // ALERT: This may cause previously non-Anonymous posts to suddenly
        // be accessible to Anonymous.

        /**
         * Given a user id, re-assign history to the new user account. Called by user_delete().
         */
        $uid = $account->id();
        $new_uid = 0;

        db_update('workflow_transition_history')
          ->fields(array('uid' => $new_uid))
          ->condition('uid', $uid, '=')
          ->execute();
        db_update('workflow_transition_schedule')
          ->fields(array('uid' => $new_uid))
          ->condition('uid', $uid, '=')
          ->execute();

        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  function insertUserRole(Role $role) {
    user_role_change_permissions($role->id(), array('participate in workflow' => 1));
  }

  /**
   * {@inheritdoc}
   */
  function getCurrentStateId(EntityInterface $entity, $field_name = '') {
    $sid = FALSE;

    if (!$entity) {
      return $sid;
    }

    // If $field_name is not known, yet, determine it.
    $field_name = workflow_get_field_name($entity);
    // If $field_name is found, get more details.
    if (!$field_name) {
      // Return the initial value.
    }
    else {
      // Normal situation: get the value.
      if (!$sid) {
        $sid = $entity->$field_name->value;
      }
      // No current state. Use creation state.
      // (E.g., content was created before adding workflow.)
      if (!$sid) {
        $sid = $this->getCreationStateId($entity, $field_name);
      }
    }
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  function getPreviousStateId(EntityInterface $entity, $field_name = '') {
    $sid = FALSE;

    if (!$entity) {
      return $sid;
    }

    // If $field_name is not known, yet, determine it.
    $field_name = workflow_get_field_name($entity, $field_name);
    // If $field_name is found, get more details.
    if (!$field_name) {
      // Return the initial value.
    }
    else {
      $entity_type = $entity->getEntityTypeId();
      $langcode = $entity->language()->getId();

      if (isset($entity->original)) {
        // A changed node.
        workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
      }

      // A node may not have a Workflow attached.
      if (!$sid) {
        if ($entity->isNew()) {
          // A new Node. D7: $is_new is not set when saving terms, etc.
          $sid = $this->getCreationStateId($entity, $field_name);
        }
        elseif (!$sid) {
          // Read the history with an explicit langcode.
          if ($last_transition = WorkflowTransition::loadByProperties($entity_type, $entity->id(), [], $field_name, $langcode, 'DESC')) {
            $sid = $last_transition->getFromSid();
          }
        }
      }

      if (!$sid) {
        workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
        // No history found on an existing entity.
        $sid = $this->getCreationStateId($entity, $field_name);
      }
    }

    return $sid;
  }

  /**
   * Gets the creation sid for a given $entity and $field_name.
   *
   * Is a helper function for:
   * - workflow_node_current_state()
   * - workflow_node_previous_state()
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $field_name
   *
   * @return string $sid
   *   The ID of the creation State for the Workflow of the field.
   */
  private function getCreationStateId($entity, $field_name) {
    $sid = '';

    $field_config = $entity->get($field_name)->getFieldDefinition();
    $field_storage = $field_config->getFieldStorageDefinition();
    $wid = $field_storage->getSetting('workflow_type');
    $workflow = Workflow::load($wid);

    if ($workflow) {
      $sid = $workflow->getCreationSid();
    }
    else {
      drupal_set_message(t('Workflow !wid cannot be loaded. Contact your system administrator.', array('!wid' => $wid)), 'error');
    }
    return $sid;
  }

}
