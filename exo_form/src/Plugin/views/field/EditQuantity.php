<?php

namespace Drupal\exo_form\Plugin\views\field;

use Drupal\commerce_cart\Plugin\views\field\EditQuantity as CommerceEditQuantity;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form element for editing the order item quantity.
 *
 * @ViewsField("exo_form_commerce_order_item_edit_quantity")
 */
class EditQuantity extends CommerceEditQuantity {

  /**
   * Form constructor for the views form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);

    foreach ($this->view->result as $row_index => $row) {
      if (!$row->_relationship_entities['order_items']->isLocked()) {
        $form[$this->options['id']][$row_index]['#type'] = 'exo_number';
      }
    }
  }

}
