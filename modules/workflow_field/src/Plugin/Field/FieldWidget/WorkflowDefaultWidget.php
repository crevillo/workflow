<?php

/**
 * @file
 * Contains \Drupal\workflowfield\Plugin\Field\FieldWidget\WorkflowDefaultWidget.
 */

namespace Drupal\workflowfield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\workflow\Element\WorkflowTransitionElement;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowTransition;

/**
 * Plugin implementation of the 'workflow_default' widget.
 *
 * @FieldWidget(
 *   id = "workflow_default",
 *   label = @Translation("Workflow transition form"),
 *   field_types = {
 *     "workflow"
 *   },
 * )
 */
class WorkflowDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
//      'workflow_default' => array(
//        'label' => t('Workflow'),
//        'field types' => array('workflow'),
//        'settings' => array(
//          'name_as_title' => 1,
//          'comment' => 1,
//        ),
//      ),
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = array();
    // There are no settings. All is done at Workflow level.
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    // There are no settings. All is done at Workflow level.
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

    // Field ID contains entity_type, bundle, field_name.
    $field_id = $this->fieldDefinition->id();
    $entity_id = '';  // TODO D8-port

    $form_id = implode('_', array('workflow_transition_form', $entity_id, $field_id));
    return $form_id;
  }

  /**
   * {@inheritdoc}
   *
   * Be careful: Widget may be shown in very different places. Test carefully!!
   *  - On a entity add/edit page
   *  - On a entity preview page
   *  - On a entity view page
   *  - On a entity 'workflow history' tab
   *  - On a comment display, in the comment history
   *  - On a comment form, below the comment history
   *
   * @todo D8: change "array $items" to "FieldInterface $items"
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $wid = $this->getFieldSetting('workflow_type');
    $workflow = Workflow::load($wid);
    if (!$workflow){
      // @todo: add error message.
      return $element;
    }

    /* @var $items \Drupal\workflowfield\Plugin\Field\FieldType\WorkflowItem[] */
    /* @var $item \Drupal\workflowfield\Plugin\Field\FieldType\WorkflowItem */
    $item = $items[$delta];
    /* @var $field_config \Drupal\field\Entity\FieldConfig */
    $field_config = $items[$delta]->getFieldDefinition();
    /* @var $field_storage \Drupal\field\Entity\FieldStorageConfig */
    $field_storage = $field_config->getFieldStorageDefinition();
    // $field = $field_storage->get('field');
    $field_name = $field_storage->get('field_name');
    $entity = $item->getEntity();

    /* @var \Drupal\Core\Session\AccountProxyInterface */
    $user = \Drupal::currentUser();

    /* @var $transition WorkflowTransition */
    $transition = NULL;

    // Prepare a new transition, if still not provided.
    if (!$transition) {
      $transition = WorkflowTransition::create();
      $transition->setValues($entity, $field_name,
        $from_sid = workflow_node_current_state($entity, $field_name),
        $to_sid = $default_value = '',
        $user->id(),
        REQUEST_TIME,
        $comment = ''
      );
    }

    // @TODO D8-port: use a proper WorkflowTransitionElement call.
    $element['#default_value'] = $transition;
    $element += WorkflowTransitionElement::transitionElement($element, $form_state, $form);

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Implements workflow_transition() -> WorkflowDefaultWidget::submit().
   *
   * Overrides submit(array $form, array &$form_state).
   * Contains 2 extra parameters for D7
   *
   * @param array $form
   * @param array $form_state
   * @param array $items
   *   The value of the field.
   * @param bool $force
   *   TRUE if all access must be overridden, e.g., for Rules.
   *
   * @return int
   *   If update succeeded, the new State Id. Else, the old Id is returned.
   *
   * This is called from function _workflowfield_form_submit($form, &$form_state)
   * It is a replacement of function workflow_transition($entity, $to_sid, $force, $field)
   * It performs the following actions;
   * - save a scheduled action
   * - update history
   * - restore the normal $items for the field.
   * @todo: remove update of {node_form} table. (separate task, because it has features, too)
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    /* @var \Drupal\Core\Session\AccountProxyInterface */
    $user = \Drupal::currentUser(); // @todo #2287057: verify if submit() really is only used for UI. If not, $user must be passed.

    // Set the new value.
    // Beware: We presume cardinality = 1 !!
    // The widget form element type has transformed the value to a
    // WorkflowTransition object at this point. We need to convert it
    // back to the regular 'value' string format.
    foreach ($values as &$item) {
      if (!empty($item ['workflow']) ) { // } && $item ['value'] instanceof DrupalDateTime) {

        // The following can NOT be retrieved from the WorkflowTransition.
        /* @var $entity EntityInterface */
        $entity = $form_state->getFormObject()->getEntity();
        $field_name = $item['workflow']['workflow_field_name'];
        /* @var $transition \Drupal\workflow\Entity\WorkflowTransitionInterface */
        $transition = $item['workflow']['workflow_transition'];
        // N.B. Use a proprietary version of copyFormValuesToEntity,
        // where $entity/$transition is passed by reference.
        // $this->copyFormValuesToEntity($entity, $form, $form_state);
        /* @var $transition \Drupal\workflow\Entity\WorkflowTransitionInterface */
        $transition = WorkflowTransitionElement::copyFormItemValuesToEntity($transition, $form, $item);

        $force = FALSE; // @TODO D8-port: add to form for usage in VBO.

        // Now, save/execute the transition.
        $from_sid = $transition->getFromSid();
        $force = $force || $transition->isForced();

        // Try to execute the transition. Return $from_sid when error.
        if (!$transition) {
          workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.

          // This should only happen when testing/developing.
          drupal_set_message(t('Error: the transition from %from_sid to %to_sid could not be generated.'), 'error');
          // The current value is still the previous state.
          $to_sid = $from_sid;
        }
//        elseif ($transition->isScheduled()) {
//          /*
//           * A scheduled transition must only be saved to the database.
//           * The entity is not changed.
//           */
//          $transition->save();
//
//          // The current value is still the previous state.
//          $to_sid = $from_sid;
//        }
        else {
          // It's an immediate change. Do the transition.
          // - validate option; add hook to let other modules change comment.
          // - add to history; add to watchdog
          // Return the new State ID. (Execution may fail and return the old Sid.)

          if (!$transition->isAllowed([], $user, $force)) {
            // Transition is not allowed.
            $to_sid = $from_sid;
          }
          elseif (!$entity || !$entity->id()) {
            // Entity is inserted. The Id is not yet known.
            // So we can't yet save the transition right now, but must rely on
            // function/hook workflow_entity_insert($entity) in file workflow.module.
            // $to_sid = $transition->execute($force);
            $to_sid = $transition->getToSid();
          }
          else {
            // Entity is updated. To stay in sync with insert, we rely on
            // function/hook workflow_entity_update($entity) in file workflow.module.
            // $to_sid = $transition->execute($force);
            $to_sid = $transition->getToSid();
          }
        }

        // Now the data is captured in the Transition, and before calling the
        // Execution, restore the default values for Workflow Field.
        // For instance, workflow_rules evaluates this.
        //
        // Set the transition back, to be used in hook_entity_update().
        $item['workflow']['workflow_transition'] = $transition;
        //
        // Set the value at the proper location.
        $item['value'] = $to_sid;
      }
    }
    return $values;
  }

}