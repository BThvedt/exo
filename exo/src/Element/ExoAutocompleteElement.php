<?php

namespace Drupal\exo\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\CompositeFormElementTrait;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides an Autocomplete Form API element.
 *
 * @FormElement("exo_autocomplete")
 */
class ExoAutocompleteElement extends FormElement {

  use CompositeFormElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    // Apply default form element properties.
    $info['#target_type'] = NULL;
    $info['#selection_handler'] = 'default';
    $info['#selection_settings'] = [];
    $info['#tags'] = TRUE;
    $info['#autocreate'] = NULL;
    // This should only be set to FALSE if proper validation by the selection
    // handler is performed at another level on the extracted form values.
    $info['#validate_reference'] = TRUE;
    // IMPORTANT! This should only be set to FALSE if the #default_value
    // property is processed at another level (e.g. by a Field API widget) and
    // its value is properly checked for access.
    $info['#process_default_value'] = TRUE;

    $info['#element_validate'] = [
      [
        '\Drupal\exo\Element\ExoAutocompleteElement',
        'validateEntityAutocomplete',
      ],
    ];
    $info['#process'][] = [$class, 'processElement'];

    return $info;
  }

  /**
   * Autocomplete Deluxe element process callback.
   */
  public static function processElement($element) {
    $element['#attached']['library'][] = 'exo/autocomplete';

    $html_id = Html::getUniqueId('exo-autocomplete-input');

    $element['#after_build'][] = [get_called_class(), 'afterBuild'];

    // Set default options for multiple values.
    $element['#multiple'] = $element['#multiple'] ?? FALSE;

    // Add label_display and label variables to template.
    $element['label'] = ['#theme' => 'form_element_label'];
    $element['label'] += array_intersect_key(
      $element,
      array_flip(
        [
          '#id',
          '#required',
          '#title',
          '#title_display',
        ]
      )
    );

    $element['textfield'] = [
      '#type' => 'textfield',
      '#size' => $element['#size'] ?? '',
      '#attributes' => [
        'class' => ['exo-autocomplete-form'],
        'id' => $html_id,
      ],
      '#default_value' => '',
      '#prefix' => '<div class="exo-autocomplete-container">',
      '#suffix' => '</div>',
      '#description' => $element['#description'] ?? '',
    ];
    $js_settings[$html_id] = [
      'input_id' => $html_id,
      'multiple' => $element['#multiple'],
      'required' => $element['#required'],
      'limit' => $element['#limit'] ?? 10,
      'min_length' => $element['#min_length'] ?? 0,
      'use_synonyms' => $element['#use_synonyms'] ?? 0,
      'delimiter' => $element['#delimiter'] ?? '',
      'not_found_message_allow' => $element['#not_found_message_allow'] ?? FALSE,
      'not_found_message' => $element['#not_found_message'] ?? "The term '@term' will be added.",
      'new_terms' => $element['#new_terms'] ?? FALSE,
      'no_empty_message' => $element['#no_empty_message'] ?? 'No terms could be found. Please type in order to add a new term.',
    ];

    if (isset($element['#exo_autocomplete_path'])) {
      if (isset($element['#default_value'])) {
        // Split on the comma only if that comma has zero, or an even number of
        // quotes in ahead of it.
        // http://stackoverflow.com/questions/1757065/java-splitting-a-comma-separated-string-but-ignoring-commas-in-quotes
        $default_value = preg_replace('/,(?=([^\"]*\"[^\"]*\")*[^\"]*$)/i', '"" ""', $element['#default_value']);
        $default_value = '""' . $default_value . '""';
      }
      else {
        $default_value = '';
      }

      if ($element['#multiple']) {
        $element['value_field'] = [
          '#type' => 'textfield',
          '#attributes' => [
            'class' => ['exo-autocomplete-value-field'],
          ],
          '#maxlength' => NULL,
          '#default_value' => $default_value,
          '#prefix' => '<div class="exo-autocomplete-value-container">',
          '#suffix' => '</div>',
        ];
        $element['textfield']['#attributes']['style'] = ['display: none'];
      }
      else {
        $element['textfield']['#default_value'] = $element['#default_value'] ?? '';
      }

      $js_settings[$html_id] += [
        'type' => 'ajax',
        'uri' => $element['#exo_autocomplete_path'],
      ];
    }
    else {
      // If there is no source (path or data), we don't want to add the js
      // settings and so the functions will be aborted.
      return $element;
    }

    $element['#attached']['drupalSettings']['exoAutocomplete'] = $js_settings;
    $element['#tree'] = TRUE;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return [];
  }

  /**
   * Form element validation handler for entity_autocomplete elements.
   */
  public static function validateEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = NULL;

    if (!empty($element['#value'])) {
      $options = $element['#selection_settings'] + [
        'target_type' => $element['#target_type'],
        'handler' => $element['#selection_handler'],
      ];
      /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
      $autocreate = (bool) $element['#autocreate'] && $handler instanceof SelectionWithAutocreateInterface;

      // GET forms might pass the validated data around on the next request, in
      // which case it will already be in the expected format.
      if (is_array($element['#value'])) {
        $value = $element['#value'];
      }
      else {
        $input_values = $element['#tags'] ? Tags::explode($element['#value']) : [$element['#value']];

        foreach ($input_values as $input) {
          $match = static::extractEntityIdFromAutocompleteInput($input);
          // Handling the case when the entity label contains parentheses.
          if (!empty($match)) {
            $match = static::matchEntityByTitle($handler, $input, $element, $form_state, FALSE) ?? $match;
          }
          if ($match === NULL) {
            // Try to get a match from the input string when the user didn't use
            // the autocomplete but filled in a value manually.
            $match = static::matchEntityByTitle($handler, $input, $element, $form_state, !$autocreate);
          }

          if ($match !== NULL) {
            $value[] = [
              'target_id' => $match,
            ];
          }
          elseif ($autocreate) {
            /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface $handler */
            // Auto-create item. See an example of how this is handled in
            // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::presave().
            $value[] = [
              'entity' => $handler->createNewEntity($element['#target_type'], $element['#autocreate']['bundle'], $input, $element['#autocreate']['uid']),
            ];
          }
        }
      }

      // Check that the referenced entities are valid, if needed.
      if ($element['#validate_reference'] && !empty($value)) {
        // Validate existing entities.
        $ids = array_reduce($value, function ($return, $item) {
          if (isset($item['target_id'])) {
            $return[] = $item['target_id'];
          }
          return $return;
        });

        if ($ids) {
          $valid_ids = $handler->validateReferenceableEntities($ids);
          if ($invalid_ids = array_diff($ids, $valid_ids)) {
            foreach ($invalid_ids as $invalid_id) {
              $form_state->setError($element, t('The referenced entity (%type: %id) does not exist.', [
                '%type' => $element['#target_type'],
                '%id' => $invalid_id,
              ]));
            }
          }
        }

        // Validate newly created entities.
        $new_entities = array_reduce($value, function ($return, $item) {
          if (isset($item['entity'])) {
            $return[] = $item['entity'];
          }
          return $return;
        });

        if ($new_entities) {
          if ($autocreate) {
            $valid_new_entities = $handler->validateReferenceableNewEntities($new_entities);
            $invalid_new_entities = array_diff_key($new_entities, $valid_new_entities);
          }
          else {
            // If the selection handler does not support referencing newly
            // created entities, all of them should be invalidated.
            $invalid_new_entities = $new_entities;
          }

          foreach ($invalid_new_entities as $entity) {
            /** @var \Drupal\Core\Entity\EntityInterface $entity */
            $form_state->setError($element, t('This entity (%type: %label) cannot be referenced.', [
              '%type' => $element['#target_type'],
              '%label' => $entity->label(),
            ]));
          }
        }
      }

      // Use only the last value if the form element does not support multiple
      // matches (tags).
      if (!$element['#tags'] && !empty($value)) {
        $last_value = $value[count($value) - 1];
        $value = $last_value['target_id'] ?? $last_value;
      }
    }

    $form_state->setValueForElement($element, $value);
  }

  /**
   * Finds an entity from an autocomplete input without an explicit ID.
   *
   * The method will return an entity ID if one single entity unambiguously
   * matches the incoming input, and assign form errors otherwise.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler
   *   Entity reference selection plugin.
   * @param string $input
   *   Single string from autocomplete element.
   * @param array $element
   *   The form element to set a form error.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param bool $strict
   *   Whether to trigger a form error if an element from $input (eg. an entity)
   *   is not found.
   *
   * @return int|null
   *   Value of a matching entity ID, or NULL if none.
   */
  protected static function matchEntityByTitle(SelectionInterface $handler, $input, array &$element, FormStateInterface $form_state, $strict) {
    $entities_by_bundle = $handler->getReferenceableEntities(trim($input), '=', 6);
    $entities = array_reduce($entities_by_bundle, function ($flattened, $bundle_entities) {
      return $flattened + $bundle_entities;
    }, []);
    $params = [
      '%value' => $input,
      '@value' => $input,
      '@entity_type_plural' => \Drupal::entityTypeManager()->getDefinition($element['#target_type'])->getPluralLabel(),
    ];
    if (empty($entities)) {
      if ($strict) {
        // Error if there are no entities available for a required field.
        $form_state->setError($element, t('There are no @entity_type_plural matching "%value".', $params));
      }
    }
    elseif (count($entities) > 5) {
      $params['@id'] = key($entities);
      // Error if there are more than 5 matching entities.
      $form_state->setError($element, t('Many @entity_type_plural are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
    }
    elseif (count($entities) > 1) {
      // More helpful error if there are only a few matching entities.
      $multiples = [];
      foreach ($entities as $id => $name) {
        $multiples[] = $name . ' (' . $id . ')';
      }
      $params['@id'] = $id;
      $form_state->setError($element, t('Multiple @entity_type_plural match this reference; "%multiple". Specify the one you want by appending the id in parentheses, like "@value (@id)".', ['%multiple' => strip_tags(implode('", "', $multiples))] + $params));
    }
    else {
      // Take the one and only matching entity.
      return key($entities);
    }
  }

  /**
   * Extracts the entity ID from the autocompletion result.
   *
   * @param string $input
   *   The input coming from the autocompletion result.
   *
   * @return mixed|null
   *   An entity ID or NULL if the input does not contain one.
   */
  public static function extractEntityIdFromAutocompleteInput($input) {
    $match = NULL;

    // Take "label (entity id)', match the ID from inside the parentheses.
    // @todo Add support for entities containing parentheses in their ID.
    // @see https://www.drupal.org/node/2520416
    if (preg_match("/.+\s\(([^\)]+)\)/", $input, $matches)) {
      $match = $matches[1];
    }

    return $match;
  }

  /**
   * Form API after build callback for the duration parameter type form.
   *
   * Fixes up the form value by applying the multiplier.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state) {
    // By default Drupal sets the maxlength to 128 if the property isn't
    // specified, but since the limit isn't useful in some cases,
    // we unset the property.
    unset($element['textfield']['#maxlength']);

    // Set the elements value from either the value field or text field input.
    $element['#value'] = isset($element['value_field']) ? $element['value_field']['#value'] : $element['textfield']['#value'];

    if (isset($element['value_field'])) {
      $element['#value'] = trim($element['#value']);
      // Replace all cases of double double quotes and one or more spaces with a
      // comma. This will allow us to keep entries in double quotes.
      $element['#value'] = preg_replace('/"" +""/', ',', $element['#value']);
      // Remove the double quotes at the beginning and the end from the first
      // and the last term.
      $element['#value'] = substr($element['#value'], 2, strlen($element['#value']) - 4);

      unset($element['value_field']['#maxlength']);
    }

    $form_state->setValueForElement($element, $element['#value']);

    return $element;
  }

}
