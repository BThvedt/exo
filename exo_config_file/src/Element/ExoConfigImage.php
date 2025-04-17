<?php

namespace Drupal\exo_config_file\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element for uploading a file.
 *
 * If you add this element to a form the enctype="multipart/form-data" attribute
 * will automatically be added to the form element.
 *
 * Properties:
 * - #multiple: A Boolean indicating whether multiple files may be uploaded.
 * - #size: The size of the file input element in characters.
 *
 * @FormElement("exo_config_image")
 */
class ExoConfigImage extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#multiple' => FALSE,
      '#process' => [
        [$class, 'processFile'],
      ],
      '#element_validate' => [
        [$class, 'validateElement'],
      ],
      '#size' => 60,
      '#theme' => 'file_exo_config_file',
      '#theme_wrappers' => ['form_element'],
      '#file_destination' => 'public://[exo_config_file:type]',
      '#file_name' => '[exo_config_file:id]-[exo_config_file:field_name]',
      '#extensions' => ['jpg', 'jpeg', 'png', 'gif'],
      '#upload_validators' => [],
    ];
  }

  /**
   * Processes a file upload element, make use of #multiple if present.
   */
  public static function processFile(&$element, FormStateInterface $form_state, &$complete_form) {

    $element['#tree'] = TRUE;

    $element['upload'] = [
      '#type' => 'exo_config_file',
      '#default_value' => $element['#default_value'] ?? NULL,
      '#required' => $element['#required'],
      '#extensions' => $element['#extensions'],
      '#final_parents' => $element['#parents'],
      '#upload_validators' => $element['#upload_validators'],
    ];

    if (!empty($element['#default_value'])) {
      $element['current'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'exo-form-inline',
            'exo-form-inline-top',
            'exo-form-inline-compact',
            'exo-form-inline-all',
          ],
        ],
      ];
      if (!empty($element['#default_value']) && is_string($element['#default_value']) && file_exists($element['#default_value'])) {
        $element['current']['thumbnail'] = [
          '#theme' => 'image_style',
          '#style_name' => 'thumbnail',
          '#uri' => $element['#default_value'],
          '#prefix' => '<div>',
          '#suffix' => '</div>',
        ];
      }
      $element['current']['remove'] = [
        '#type' => 'checkbox',
        '#title' => t('Remove'),
        '#parents' => array_merge($element['#parents'], ['remove']),
      ];
    }

    return $element;
  }

  /**
   * Render API callback: Validates the managed_file element.
   */
  public static function validateElement(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = $form_state->getValue($element['#parents']);
    if (!empty($value['remove'])) {
      // $complete_form = $form_state->getFormObject();
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $form_state->getFormObject()->getEntity();
      $field_name = $element['upload']['#original_name'];
      $exo_config_file_id = ExoConfigFile::getConfigFileEntityId($entity, $field_name);
      $storage = \Drupal::entityTypeManager()->getStorage('exo_config_file');
      /** @var \Drupal\exo_file_config\Entity\ExoConfigFileInterface $exo_config_file */
      $exo_config_file = $storage->load($exo_config_file_id);
      if ($exo_config_file) {
        $exo_config_file->delete();
      }
      $form_state->setValue($element['#parents'], NULL);
    }
    else {
      $form_state->setValue($element['#parents'], $value['upload']);
    }
  }

}
