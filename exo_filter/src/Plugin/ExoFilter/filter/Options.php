<?php

namespace Drupal\exo_filter\Plugin\ExoFilter\filter;

use Drupal\exo_filter\Plugin\ExoFilterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'boolean' formatter.
 *
 * @ExoFilter(
 *   id = "options",
 *   label = @Translation("Checkboxes/Radios"),
 *   field_types = {
 *     "list_field",
 *     "taxonomy_index_tid",
 *     "search_api_term",
 *     "search_api_options",
 *     "exo_filter_entity_reference",
 *   }
 * )
 */
class Options extends ExoFilterBase {

  /**
   * {@inheritdoc}
   */
  public function exposedElementAlter(&$element, FormStateInterface $form_state, $context) {
    $element_id = $context['id'];
    $user_input = $form_state->getUserInput();
    unset($element['#options']['All']);

    if (empty($element['#multiple'])) {
      $element['#type'] = 'radios';
      if (($user_input[$element_id] ?? '') === 'All') {
        $user_input[$element_id] = '';
        $form_state->setUserInput($user_input);
      }
    }
    else {
      $element['#type'] = 'checkboxes';
      $values = array_values((array) ($user_input[$element_id] ?? []));
      $user_input[$element_id] = array_filter(array_combine($values, $values));
      $element['#default_value'] = $user_input[$element_id];
      $form_state->setUserInput($user_input);
    }
  }

}
