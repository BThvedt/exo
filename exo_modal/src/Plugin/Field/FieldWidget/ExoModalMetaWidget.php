<?php

namespace Drupal\exo_modal\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'exo_modal_meta' widget.
 *
 * @FieldWidget(
 *   id = "exo_modal_meta",
 *   label = @Translation("Snippets default"),
 *   field_types = {
 *     "exo_modal_meta"
 *   }
 * )
 */
class ExoModalMetaWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['trigger_text'] = [
      '#title' => $this->t('Link Title'),
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->trigger_text ?? NULL,
    ];
    $element['trigger_icon'] = [
      '#title' => $this->t('Link Icon'),
      '#type' => 'exo_icon',
      '#default_value' => $items[$delta]->trigger_icon ?? NULL,
    ];
    $element['modal_title'] = [
      '#title' => $this->t('Modal Title'),
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->modal_title ?? NULL,
    ];
    $element['modal_subtitle'] = [
      '#title' => $this->t('Modal Subtitle'),
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->modal_subtitle ?? NULL,
    ];
    $element['modal_icon'] = [
      '#title' => $this->t('Modal Icon'),
      '#type' => 'exo_icon',
      '#default_value' => $items[$delta]->modal_icon ?? NULL,
    ];

    return $element;
  }

}
