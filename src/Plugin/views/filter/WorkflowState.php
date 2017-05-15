<?php

namespace Drupal\workflow\Plugin\views\filter;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;

/**
 * Filter handler which uses workflow_state as options.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("workflow_state")
 */
class WorkflowState extends ManyToOne {

  use FieldAPIHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $wid = isset($this->definition['wid']) ? $this->definition['wid'] : '';
    $grouped = $options['group_info']['widget'] == 'select';
    $this->valueOptions = workflow_get_workflow_state_names($wid, $grouped);
  }
}
