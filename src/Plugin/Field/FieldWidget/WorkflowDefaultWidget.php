<?php

/**
 * @file
 * Contains \Drupal\workflow\Plugin\Field\FieldWidget\WorkflowDefaultWidget.
 */

namespace Drupal\workflow\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Render\Element\Container;
use Drupal\field\Entity\FieldConfig;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransitionForm;

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
      'workflow_default' => array(
        'label' => t('Workflow'),
        'field types' => array('workflow'),
        'settings' => array(
          'name_as_title' => 1,
          'comment' => 1,
        ),
      ),
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

    // Do not show the TransitionForm in the 'Default value' of a Field on
    //  page /admin/structure/types/manage/MY_CONTENT_TYPE/fields/MY_FIELD_NAME .
    $url_components = explode('/', $_SERVER['REQUEST_URI']);
    if (isset($url_components[6]) && ('fields' == $url_components[6])) {
      //     $form = array();
      return $element = array();
    };

    /* @var items Drupal\workflow\Plugin\Field\FieldType\WorkflowItem[] */
    $item = $items[$delta];
    /* @var $field_config Drupal\field\Entity\FieldConfig */
    $field_config = $items[$delta]->getFieldDefinition();
    $field_storage = $field_config->getFieldStorageDefinition();
    // $field = $field_storage->get('field');
    $field_name = $field_storage->get('field_name');
    /* @var $entity_type Drupal\Core\Entity\EntityTypeInterface */
    $entity_type = $field_storage->get('entity_type');
    $entity = $item->getEntity();
    $entity_id = $entity->id();

    $wid = $this->getFieldSetting('workflow_type');
    $workflow = Workflow::load($wid);
    if (!$workflow){
      // @todo: add error message.
      return $element;
    }

    // Add the element. Do not use drupal_get_form, or you will have a form in a form.
//D7      workflow_transition_form($form, $form_state, $field, $instance, $entity_type, $entity);

//    if (!isset ($instance['widget']['settings']['submit_function'])) {
//      $instance['widget']['settings']['submit_function'] = 'workflow_transition_form_submit';
//    }

    $user = \Drupal::currentUser();

    $transition = NULL;
    $transition = $form_state->getValue('WorkflowTransition');
    if (isset($transition)) {
//      dpm('TODO D8-port (with transition): test function WorkflowDefaultWidget::' . __FUNCTION__);
      // If provided, get data from WorkflowTransition.
      // This happens when calling entity_ui_get_form(), like in the
      // WorkflowTransition Comment Edit form.
      $transition = $form_state['WorkflowTransition'];

//      $field_name = $transition->field_name;
//      $workflow = $transition->getWorkflow();
//      $wid = $transition->wid;

//      $entity = $this->entity = $transition->getEntity();
//      $entity_type = $this->entity_type = $transition->entity_type;
//      $entity_id = entity_id($entity_type, $entity);

      // Show the current state and the Workflow form to allow state changing.
      // N.B. This part is replicated in hook_node_view, workflow_tab_page, workflow_vbo, transition_edit.
      // @todo: support multiple workflows per entity.
      // For workflow_tab_page with multiple workflows, use a separate view. See [#2217291].
      $field = _workflow_info_field($field_name, $workflow);
//      $field_id = $field['id'];
      $instance = field_info_instance($entity_type, $field_name, $entity_bundle);
    }
    else {
      // Get data from normal parameters.
//      $entity = $this->entity;
//      $entity_type = $this->entity_type;
//      $entity_id = ($entity) ? entity_id($entity_type, $entity) : 0;

//      $field = $this->field;
//      $field_name = $field['field_name'];
//      $field_id = $field['id'];
//      $instance = $this->instance;

//      // $field['settings']['wid'] can be numeric or named.
//      // $wid may not be specified.
//      $wid = $field['settings']['wid'];
//      $workflow = Workflow::load($wid);
    }

    $force = FALSE;

    // Get values.
    // D7: $settings_base = $field['settings']['widget'];
    $settings_base = $workflow->options;
    // Current sid and default value may differ in a scheduled transition.
    // Set 'grouped' option. Only valid for select list and undefined/multiple workflows.
    $settings_options_type = $settings_base['options'];
    $grouped = ($settings_options_type == 'select');
    if ($transition) {
//      dpm('TODO D8-port (with transition): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);

      // If a Transition is passed as parameter, use this.
      $current_state = $transition->getOldState();
      if ($transition->isExecuted()) {
        // The states may not be changed anymore.
        $options = array();
      }
      else {
        $options = $current_state->getOptions($entity_type, $entity, $field_name, $user, $force);
      }
      $show_widget = $current_state->showWidget($entity_type, $entity, $field_name, $user, $force);
      $current_sid = $transition->old_sid;
      $default_value = $transition->new_sid;
      // You may not schedule an existing Transition.
      if ($transition->isExecuted()) {
        $settings_base['schedule'] = FALSE;
      }
    }
    elseif (!$entity) {
//      dpm('TODO D8-port (with transition): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);
      // Sometimes, no entity is given. We encountered the following cases:
      // - the Field settings page,
      // - the VBO action form;
      // - the Advance Action form on admin/config/system/actions;
      // If so, show all options for the given workflow(s).
      $options = workflow_get_workflow_state_names($wid, $grouped, $all = FALSE);
      $show_widget = TRUE;
      $default_value = $current_sid = isset($items[$delta]->value) ? $items[$delta]->value : '0';
    }
    else {
      $current_sid = workflow_node_current_state($entity, $entity_type, $field_name);

      if ($current_state = WorkflowState::load($current_sid)) {
//        dpm('TODO D8-port (with transition): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);
        $options = $current_state->getOptions($entity_type, $entity, $field_name, $user, $force);
        $show_widget = $current_state->showWidget($entity_type, $entity, $field_name, $user, $force);
        // Determine the default value. If we are in CreationState, use a fast alternative for $workflow->getFirstSid().
        $default_value = $current_state->isCreationState() ? key($options) : $current_sid;
      }
      else {
//        dpm('TODO D8-port (with transition): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);
        // We are in trouble! A message is already set in workflow_node_current_state().
        $options = array();
        $show_widget = FALSE;
        $default_value = $current_sid;
      }

      // TODO D8-port: load Scheduled transitions.
      /*
      // Get the scheduling info. This may change the $default_value on the Form.
      // Read scheduled information, only if an entity exists.
      // Technically you could have more than one scheduled, but this will only add the soonest one.
      foreach (WorkflowScheduledTransition::load($entity_type, $entity_id, $field_name, 1) as $transition) {
        $default_value = $transition->new_sid;
        break;
      }
      */

    }

//        dpm('TODO D8-port (create WTransition): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);
    /*
        // Prepare a new transition, if still not provided.
        if (!$transition) {
          $transition = WorkflowTransition::create(array(
            'old_sid' => $default_value,
            'stamp' => REQUEST_TIME,
          ));
        }
    */

    // Fetch the form ID. This is unique for each entity, to allow multiple form per page (Views, etc.).
    // Make it uniquer by adding the field name, or else the scheduling of
    // multiple workflow_fields is not independent of eachother.
    // IF we are truely on a Transition form (so, not a Node Form with widget)
    // then change the form id, too.

    $form_id = $this->getFormId();

    if (!isset($form_state->getValue('build_info')['base_form_id'])) {
      // Strange: on node form, the base_form_id is node_form,
      // but on term form, it is not set.
      // In both cases, it is OK.
    }
    else {
      // TODO D8-port: check usage of form names.
      if ($form_state['build_info']['base_form_id'] == 'workflow_transition_wrapper_form') {
        $form_state['build_info']['base_form_id'] = 'workflow_transition_form';
      }
      if ($form_state['build_info']['base_form_id'] == 'workflow_transition_form') {
        $form_state['build_info']['form_id'] = $form_id;
      }
    }

    $workflow_label = $workflow ? SafeMarkup::checkPlain(t($workflow->label())) : '';

    // TODO D8-port: check below code: ['comment_log_tab']; vs. ['comment_log_node'];
    $settings_base['comment'] = isset($settings_base['comment_log_node']) ? $settings_base['comment_log_node'] : 1; // vs. ['comment_log_tab'];
    // Change settings locally.
    if (!$field_name) {
      // This is a Workflow Node workflow. Set widget options as in v7.x-1.2
      if ($form_state['build_info']['base_form_id'] == 'node_form') {
        $settings_base['comment'] = isset($settings_base['comment_log_node']) ? $settings_base['comment_log_node'] : 1; // vs. ['comment_log_tab'];
        $settings_base['current_status'] = TRUE;
      }
      else {
        $settings_base['comment'] = isset($settings_base['comment_log_tab']) ? $settings_base['comment_log_tab'] : 1; // vs. ['comment_log_node'];
        $settings_base['current_status'] = TRUE;
      }
    }

    // Capture settings to format the form/widget.
    $settings_title_as_name = !empty($settings_base['name_as_title']);
    $settings_options_type = $settings_base['options'];
    // The schedule can be hidden via field settings, ...
    $settings_schedule = !empty($settings_base['schedule']);
    if ($settings_schedule) {
      // TODO D8-port: check below code: form on VBO.
      $step = $form_state->getValue('step');
      if (isset($step) && ($form_state->getValue('step') == 'views_bulk_operations_config_form')) {
        // On VBO 'modify entity values' form, leave field settings.
        $settings_schedule = TRUE;
      }
      else {
        // ... and cannot be shown on a Content add page (no $entity_id),
        // ...but can be shown on a VBO 'set workflow state to..'page (no entity).
        $settings_schedule = !($entity && !$entity_id);
      }
    }
    $settings_schedule_timezone = !empty($settings_base['schedule_timezone']);
    // Show comment, when both Field and Instance allow this.
    $settings_comment = $settings_base['comment'];

    $transition_is_scheduled = ($transition && $transition->isScheduled());

    // Save the current value of the node in the form, for later Workflow-module specific references.
    // We add prefix, since #tree == FALSE.
    $element['workflow']['workflow_entity'] = array(
      '#type' => 'value',
      '#value' => $entity,
    );
    $element['workflow']['workflow_entity_type'] = array(
      '#type' => 'value',
      '#value' => $entity_type,
    );
//    $element['workflow']['workflow_field'] = array(
//      '#type' => 'value',
//      '#value' => $field,
//    );
    $element['workflow']['workflow_field_name'] = array(
      '#type' => 'value',
      '#value' => $field_name,
    );
    $element['workflow']['workflow_transition'] = array(
      '#type' => 'value',
      '#value' => $transition,
    );
//    $element['workflow']['workflow_instance'] = array(
//      '#type' => 'value',
//      '#value' => $instance,
//    );

//    // Save the form_id, so the form values can be retrieved in submit function.
//    $element['workflow']['form_id'] = array(
//      '#type' => 'value',
//      '#value' => $form_id,
//    );

//    // Save the hid, when editing an existing transition.
//    $element['workflow']['workflow_hid'] = array(
//      '#type' => 'hidden',
//      '#value' => $transition->hid,
//    );

//    // Add the default value in the place where normal fields
//    // have it. This is to cater for 'preview' of the entity.
//    $element['#default_value'] = $default_value;


    // Decide if we show a widget or a formatter.
    // There is no need for a widget when the only option is the current sid.

    // TODO D8-port: add workflow_state_formatter
    /*
    // Show state formatter before the rest of the form,
    // when transition is scheduled or widget is hidden.
    if ((!$show_widget) || $transition_is_scheduled) ) {
      $form['workflow_current_state'] = workflow_state_formatter($entity_type, $entity, $field, $instance, $current_sid);
      // Set a proper weight, which works for Workflow Options in select list AND action buttons.
      $form['workflow_current_state']['#weight'] = -0.005;
    }
*/
    if (!$show_widget) {
      // Show no widget.
      $element['workflow']['workflow_sid']['#type'] = 'value';
      $element['workflow']['workflow_sid']['#value'] = $default_value;

      return $element; // <---- exit.
    }
    else {
// TODO: repair the usage of $settings_title_as_name: no container if no details (schedule/comment)
      // Prepare a UI wrapper. This might be a fieldset.
      $element['workflow']['#type'] = 'container'; // 'details';
//      $element['workflow']['#type'] = 'details';
//      $element['workflow']['#description'] = $settings_title_as_name ? t('Change !name state', array('!name' => $workflow_label)) : t('Target state');
//      $element['workflow']['#open'] = TRUE; // Controls the HTML5 'open' attribute. Defaults to FALSE.
      $element['workflow']['#attributes'] = array('class' => array('workflow-form-container'));

      // The 'options' widget. May be removed later if 'Action buttons' are chosen.
      $element['workflow']['workflow_sid'] = array(
        '#type' => $settings_options_type,
        '#title' => $settings_title_as_name ? t('Change !name state', array('!name' => $workflow_label)) : t('Target state'),
        '#options' => $options,
        // '#name' => $workflow_label,
        // '#parents' => array('workflow'),
        '#default_value' => $default_value,
      );
    }

    // Display scheduling form, but only if entity is being edited and user has
    // permission. State change cannot be scheduled at entity creation because
    // that leaves the entity in the (creation) state.

    if (TRUE  ) { // } || $settings_schedule == TRUE && $user->hasPermission('schedule workflow transitions')) {
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// if (variable_get('configurable_timezones', 1) && $user->uid && drupal_strlen($user->timezone)) {
//         $timezone = $user->timezone;
//       }
//       else {
//         $timezone = variable_get('date_default_timezone', 0);
//       }
      $timezone = $user->getTimeZone();

      $timezones = array_combine(timezone_identifiers_list(), timezone_identifiers_list());
      $timestamp = $transition ? $transition->getTimestamp() : REQUEST_TIME;
      $hours = $transition_is_scheduled ? '00:00' : format_date($timestamp, 'custom', 'H:i', $timezone);

      $element['workflow']['workflow_scheduled'] = array(
        '#type' => 'radios',
        '#title' => t('Schedule'),
        '#options' => array(
          '0' => t('Immediately'),
          '1' => t('Schedule for state change'),
        ),
        '#default_value' => $transition_is_scheduled ? '1' : '0',
        '#attributes' => array(
          'id' => 'scheduled_' . $form_id,
        ),
      );
      $element['workflow']['workflow_scheduled_date_time'] = array(
         '#type' => 'details',
        '#title' => t('At'),
        '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
        '#attributes' => array('class' => array('container-inline')),
        '#prefix' => '<div style="margin-left: 1em;">',
        '#suffix' => '</div>',
        '#states' => array(
          'visible' => array(':input[id="' . 'scheduled_' . $form_id . '"]' => array('value' => '1')),
        ),
      );
      $element['workflow']['workflow_scheduled_date_time']['workflow_scheduled_date'] = array(
        '#type' => 'date',
        '#default_value' => array(
          'day' => date('j', $timestamp),
          'month' => date('n', $timestamp),
          'year' => date('Y', $timestamp),
        ),
      );
      $element['workflow']['workflow_scheduled_date_time']['workflow_scheduled_hour'] = array(
        '#type' => 'textfield',
        '#title' => t('Time'),
        '#maxlength' => 7,
        '#size' => 6,
        '#default_value' => $hours,
        '#element_validate' => array('_workflow_transition_form_element_validate_time'),
      );

      $element['workflow']['workflow_scheduled_date_time']['workflow_scheduled_timezone'] = array(
        '#type' => $settings_schedule_timezone ? 'select' : 'hidden',
        '#title' => t('Time zone'),
        '#options' => $timezones,
        '#default_value' => array($timezone => $timezone),
      );
      $element['workflow']['workflow_scheduled_date_time']['workflow_scheduled_help'] = array(
        '#type' => 'item',
        '#prefix' => '<br />',
        '#description' => t('Please enter a time.
          If no time is included, the default will be midnight on the specified date.
          The current time is: @time.', array('@time' => format_date(REQUEST_TIME, 'custom', 'H:i', $timezone))
        ),
      );
    }

    $element['workflow']['workflow_comment'] = array(
      '#type' => $settings_comment == '0' ? 'hidden' : 'textarea',
      '#required' => $settings_comment == '2',
      '#title' => t('Workflow comment'),
      '#description' => t('A comment to put in the workflow log.'),
      '#default_value' => $transition ? $transition->comment : '',
      '#rows' => 2,
    );

    // Add the fields from the WorkflowTransition.
    //    field_attach_form('WorkflowTransition', $transition, $element['workflow'], $form_state);

    // TODO D8-port: test ActionButtons.
    // Finally, add Submit buttons/Action buttons.
    // Either a default 'Submit' button is added, or a button per permitted state.
    if ($settings_options_type == 'buttons') {
      // How do action buttons work? See also d.o. issue #2187151.
      // Create 'action buttons' per state option. Set $sid property on each button.
      // 1. Admin sets ['widget']['options']['#type'] = 'buttons'.
      // 2. This function formElelent() creates 'action buttons' per state option;
      //    sets $sid property on each button.
      // 3. User clicks button.
      // 4. Callback _workflow_transition_form_validate_buttons() sets proper State.
      // 5. Callback _workflow_transition_form_validate_buttons() sets Submit function.
      // @todo: this does not work yet for the Add Comment form.

      // Performance: inform workflow_form_alter() to do its job.
      _workflow_use_action_buttons(TRUE);
    }

    $submit_functions = empty($instance['widget']['settings']['submit_function']) ? array() : array($instance['widget']['settings']['submit_function']);
    if ($settings_options_type == 'buttons' || $submit_functions) {
//        dpm('TODO D8-port (ActioButtons): test function WorkflowDefaultWidget::' . __FUNCTION__ .'/'.__LINE__);
      $element['workflow']['actions']['#type'] = 'actions';
      $element['workflow']['actions']['submit'] = array(
        '#type' => 'submit',
//        '#access' => TRUE,
        '#value' => t('Update workflow'),
        '#weight' => -5,
//        '#submit' => array( isset($instance['widget']['settings']['submit_function']) ? $instance['widget']['settings']['submit_function'] : NULL),
        // '#executes_submit_callback' => TRUE,
        '#attributes' => array('class' => array('form-save-default-button')),
      );

      // The 'add submit' can explicitely set by workflowfield_field_formatter_view(),
      // to add the submit button on the Content view page and the Workflow history tab.
      // Add a submit button, but only on Entity View and History page.
      // Add the submit function only if one provided. Set the submit_callback accordingly.
      if ($submit_functions) {
        $element['workflow']['actions']['submit']['#submit'] = $submit_functions;
      }
      else {
        // '#submit' Must be empty, or else the submit function is not called.
        // $element['workflow']['actions']['submit']['#submit'] = array();
      }
    }
    else {
      // In some cases, no submit callback function is specified. This is
      // explicitly done on e.g., the node edit form, because the workflow form
      // is 'just a field'.
      // So, no Submit button is to be shown.
    }

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
   * It is a replacement of function workflow_transition($node, $new_sid, $force, $field)
   * It performs the following actions;
   * - save a scheduled action
   * - update history
   * - restore the normal $items for the field.
   * @todo: remove update of {node_form} table. (separate task, because it has features, too)
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
//    public function submitForm(array &$form, FormStateInterface $form_state) {
//    dpm('TODO D8-port (old D7-function): test function WorkflowDefaultWidget::' . __FUNCTION__);

    $user = \Drupal::currentUser(); // @todo #2287057: verify if submit() really is only used for UI. If not, $user must be passed.

    /* @var $entity_type Drupal\Core\Entity\EntityTypeInterface */
    $entity_type = $values[0]['workflow']['workflow_entity_type'];
    $entity = $values[0]['workflow']['workflow_entity'];
    $field_name = $values[0]['workflow']['workflow_field_name'];
    // $field = $values[0]['workflow']['workflow_field'];
    $transition = $values[0]['workflow']['workflow_transition'];

//    /* @var $entity_type Drupal\Core\Entity\EntityTypeInterface */
//    $entity_type = $field_storage->get('entity_type');
//    $entity = $item->getEntity();
//    $entity_id = $entity->id();

    $wid = $this->getFieldSetting('workflow_type');
    $workflow = Workflow::load($wid);

//    $field = $this->field;
//    $field_id = $field['id'];
//    $instance = $this->instance;

    // TODO D8-port:     // Determine if the transition is forced (on the form).
    /*
        // Retrieve the data from the form.
        if (isset($form_state['values']['workflow_field'])) {
          // If $entity filled: We are on a Entity View page or Workflow History Tab page.
          // If $entity empty: We are on an Advanced Action page.
    //      $field = $form_state['values']['workflow_field'];
    //      $instance = $form_state['values']['workflow_instance'];
    //      $entity_type = $form_state['values']['workflow_entity_type'];
    //      $entity = $form_state['values']['workflow_entity'];
    //      $field_name = $field['field_name'];
        }
        elseif (isset($form_state['triggering_element'])) {
          // We are on an Entity/Node/Comment Form page.
          $field_name = $form_state['triggering_element']['#workflow_field_name'];
        }
        else {
          // We are on a Comment Form page.
        }
    */

    // TODO D8-port:     // Determine if the transition is forced (on the form).
    /*
        // Determine if the transition is forced (on the form).
        // This can be set by a 'workflow_vbo action' in an additional form element.
        $force = isset($form_state['input']['workflow_force']) ? $form_state['input']['workflow_force'] : FALSE;
    */

    // Set language.
    $langcode = $entity->language()->getId();

//    unset($values[0]['workflow']['workflow_entity']);
//    unset($values[0]['workflow']['workflow_transition']);
//    dpm($values);
//    dpm($values[0]['workflow']);

//    unset($values);

    // Set the new value.
    // Beware: We presume cardinality = 1 !!
    // The widget form element type has transformed the value to a
    // WorkflowTransition object at this point. We need to convert it
    // back to the regular 'value' string format.
    foreach ($values as &$item) {
      if (!empty($item ['workflow']) ) { // } && $item ['value'] instanceof DrupalDateTime) {
        $form_transition = $item['workflow'];
        $new_sid = $item['workflow']['workflow_sid'];

        //  unset($item['workflow']);

    /*
        if (!$entity) {
          // E.g., on VBO form.
        }
        elseif ($field_name) {
          // Save the entity, but only if we were not in edit mode.
          // Perhaps there is a better way, but for now we use 'changed' property.
          // Also test for 'is_new'. When Migrating content, the 'changed' property may be set externally.
          // Caveat: Some entities do not have 'changed' property set.
          if ((!empty($entity->is_new)) || (isset($entity->changed) && $entity->changed == REQUEST_TIME)) {
            // We are in edit mode. No need to save the entity explicitly.

    //        // Add the $form_state to the $items, so we can do a getTransition() later on.
    //        $items[0]['workflow'] = $form_state['input'];
    //        // Create a Transition. The Widget knows if it is scheduled.
    //        $widget = WorkflowDefaultWidget::create($field, $instance, $entity_type, $entity);
    //        $new_sid = $widget->submit($form, $form_state, $items, $force);
          }
          elseif (isset($form_state['input'])) {
            // Save $entity, but only if sid has changed.
            // Use field_attach_update for this? Save always?
            $entity->{$field_name}[$langcode][0]['workflow'] = $form_state['input'];
            $entity->save();

            return; // <---- exit!
          }
          else {
            // We are saving a node from a comment.
            $entity->{$field_name}[$langcode] = $items;
            $entity->save();

            return; // <---- exit!
          }
        }
    */

    // TODO D8-port: Save the WorkflowNodeHistory.
/*
    // Extract the data from $items, depending on the type of widget.
    $old_sid = workflow_node_previous_state($entity, $entity_type, $field_name);
    if (!$old_sid) {
      // At this moment, $old_sid should have a value. If the content does not
      // have a state yet, old_sid contains '(creation)' state. But if the
      // content is not associated to a workflow, old_sid is now 0. This may
      // happen in workflow_vbo, if you assign a state to non-relevant nodes.
      $entity_id = entity_id($entity_type, $entity);
      drupal_set_message(t('Error: content !id has no workflow attached. The data is not saved.', array('!id' => $entity_id)), 'error');
      // The new state is still the previous state.
      $new_sid = $old_sid;
    }

    // Now, save/execute the transition.
    $transition = $this->getTransition($old_sid, $items, $field_name, $user);
    $force = $force || $transition->isForced();

    // Try to execute the transition. Return $old_sid when error.
    if (!$transition) {
      // This should only happen when testing/developing.
      drupal_set_message(t('Error: the transition from %old_sid to %new_sid could not be generated.'), 'error');
      // The current value is still the previous state.
      $new_sid = $old_sid;
    }
    elseif (!$transition_is_scheduled) {
      // Now the data is captured in the Transition, and before calling the
      // Execution, restore the default values for Workflow Field.
      // For instance, workflow_rules evaluates this.
      if ($field_name) {
        // $items = array();
        // $items[0]['value'] = $old_sid;
        // $entity->{$field_name}[$transition->language] = $items;
      }

      // It's an immediate change. Do the transition.
      // - validate option; add hook to let other modules change comment.
      // - add to history; add to watchdog
      // Return the new State ID. (Execution may fail and return the old Sid.)
      $new_sid = $transition->execute($force);
    }
    else {
      // A scheduled transition must only be saved to the database.
      // The entity is not changed.
      $transition->save();

      // The current value is still the previous state.
      $new_sid = $old_sid;
    }

    // The entity is still to be saved, so set to a 'normal' value.
    if ($field_name) {
      $items = array();
      $items[0]['value'] = $new_sid;
      $entity->{$field_name}[$transition->language] = $items;
    }
*/
        // Set the value at the proper location.
        $item ['value'] = $new_sid;
      }
    }
    return $values;

  }

  /**
   * Extract WorkflowTransition or WorkflowScheduledTransition from the form.
   *
   * This merely extracts the transition from the form/widget. No validation.
   */
  public function getTransition($old_sid, array $items, $field_name, stdClass $user) {
//    dpm('TODO D8-port: test function WorkflowTransitionForm::' . __FUNCTION__);

    $entity_type = $this->entity_type;
    $entity = $this->entity;
    // $entity_id = entity_id($entity_type, $entity);
    $field_name = !empty($this->field) ? $this->field['field_name'] : '';

    if (isset($items[0]['transition'])) {
      // a complete transition was already passed on.
      $transition = $items[0]['transition'];
    }
    else {
      // Get the new Transition properties. First the new State ID.
      if (isset($items[0]['workflow']['workflow_sid'])) {
        // We have shown a workflow form.
        $new_sid = $items[0]['workflow']['workflow_sid'];
      }
      elseif (isset($items[0]['value'])) {
        // We have shown a core options widget (radios, select).
        $new_sid = $items[0]['value'];
      }
      else {
        // This may happen if only 1 option is left, and a formatter is shown.
        $state = workflow_state_load_single($old_sid);
        if (!$state->isCreationState()) {
          $new_sid = $old_sid;
        }
        else {
          // This only happens on workflows, when only one transition from
          // '(creation)' to another state is allowed.
          $workflow = $state->getWorkflow();
          $new_sid = $workflow->getFirstSid($this->entity_type, $this->entity, $field_name, $user, FALSE);
        }
      }
      // If an existing Transition has been edited, $hid is set.
      $hid = isset($items[0]['workflow']['workflow_hid']) ? $items[0]['workflow']['workflow_hid'] : '';
      // Get the comment.
      $comment = isset($items[0]['workflow']['workflow_comment']) ? $items[0]['workflow']['workflow_comment'] : '';
      // Remember, the workflow_scheduled element is not set on 'add' page.
      $scheduled = !empty($items[0]['workflow']['workflow_scheduled']);
      if ($hid) {
        // We are editing an existing transition. Only comment may be changed.
        $transition = workflow_transition_load($hid);
        $transition->comment = $comment;
      }
      elseif (!$scheduled) {
        $transition = WorkflowTransition::create();
        $transition->setValues($entity_type, $entity, $field_name, $old_sid, $new_sid, $user->uid, REQUEST_TIME, $comment);
      }
      else {
        // Schedule the time to change the state.
        // If Field Form is used, use plain values;
        // If Node Form is used, use fieldset 'workflow_scheduled_date_time'.
        $schedule = isset($items[0]['workflow']['workflow_scheduled_date_time']) ? $items[0]['workflow']['workflow_scheduled_date_time'] : $items[0]['workflow'];
        if (!isset($schedule['workflow_scheduled_hour'])) {
          $schedule['workflow_scheduled_hour'] = '00:00';
        }

        $scheduled_date_time
          = $schedule['workflow_scheduled_date']['year']
          . substr('0' . $schedule['workflow_scheduled_date']['month'], -2, 2)
          . substr('0' . $schedule['workflow_scheduled_date']['day'], -2, 2)
          . ' '
          . $schedule['workflow_scheduled_hour']
          . ' '
          . $schedule['workflow_scheduled_timezone'];

        if ($timestamp = strtotime($scheduled_date_time)) {
          $transition = WorkflowScheduledTransition::create();
          $transition->setValues($entity_type, $entity, $field_name, $old_sid, $new_sid, $user->uid, $timestamp, $comment);
        }
        else {
          $transition = NULL;
        }
      }
    }
    return $transition;
  }

}
