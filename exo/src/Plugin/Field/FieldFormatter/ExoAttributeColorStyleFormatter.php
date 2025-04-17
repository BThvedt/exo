<?php

namespace Drupal\exo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\color_field\ColorHex;
use Drupal\Core\Field\FieldItemInterface;

/**
 * Plugin implementation of the color_field text formatter.
 *
 * @FieldFormatter(
 *   id = "exo_attribute_color_style",
 *   module = "color_field",
 *   label = @Translation("eXo Attribute Style"),
 *   field_types = {
 *     "color_field_type"
 *   }
 * )
 */
class ExoAttributeColorStyleFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'attribute' => 'background-color',
      'format' => 'hex',
      'opacity' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $opacity = $this->getFieldSetting('opacity');

    $elements = [];

    $elements['attribute'] = [
      '#type' => 'select',
      '#title' => $this->t('Attribute'),
      '#options' => $this->getColorAttribute(),
      '#default_value' => $this->getSetting('attribute'),
    ];

    $elements['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => $this->getColorFormat(),
      '#default_value' => $this->getSetting('format'),
    ];

    if ($opacity) {
      $elements['opacity'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display opacity'),
        '#default_value' => $this->getSetting('opacity'),
      ];
    }

    return $elements;
  }

  /**
   * This function is used to get the color attributes.
   *
   * @param string $attribute
   *   Attribute is of string type.
   *
   * @return array|string
   *   Returns array or string.
   */
  protected function getColorAttribute($attribute = NULL) {
    $attributes = [];
    $attributes['background-color'] = $this->t('Background Color');
    $attributes['color'] = $this->t('Color');

    if ($attribute) {
      return $attributes[$attribute];
    }
    return $attributes;
  }

  /**
   * This function is used to get the color format.
   *
   * @param string $format
   *   Format is of string type.
   *
   * @return array|string
   *   Returns array or string.
   */
  protected function getColorFormat($format = NULL) {
    $formats = [];
    $formats['hex'] = $this->t('Hex triplet');
    $formats['rgb'] = $this->t('RGB Decimal');

    if ($format) {
      return $formats[$format];
    }
    return $formats;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $opacity = $this->getFieldSetting('opacity');
    $settings = $this->getSettings();

    $summary = [];

    $summary[] = $this->t('Attribute: %attribute', [
      '%attribute' => $this->getColorAttribute($settings['attribute']),
    ]);

    $summary[] = $this->t('Format: %format', [
      '%format' => $this->getColorFormat($settings['format']),
    ]);

    if ($opacity && $settings['opacity']) {
      $summary[] = $this->t('Display with opacity.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // The magic happens in exo_entity_view_alter().
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function entityViewAlter(array &$build, FieldItemInterface $item, $settings) {
    $opacity = $item->getFieldDefinition()->getSetting('opacity');
    $color_hex = new ColorHex($item->color, $item->opacity);
    switch ($settings['format']) {
      case 'hex':
        if ($opacity && $settings['opacity']) {
          $output = $color_hex->toString(TRUE);
        }
        else {
          $output = $color_hex->toString(FALSE);
        }
        break;

      case 'rgb':
        if ($opacity && $settings['opacity']) {
          $output = $color_hex->toRgb()->toString(TRUE);
        }
        else {
          $output = $color_hex->toRgb()->toString(FALSE);
        }
        break;
    }
    $output = $settings['attribute'] . ':' . $output . ';';
    if (empty($build['#attributes']['style'])) {
      $build['#attributes']['style'] = '';
    }
    $build['#attributes']['style'] .= $output;

  }

  // /**
  //  * {@inheritdoc}
  //  */
  // protected function viewValue(ColorFieldType $item) {
  //   $opacity = $this->getFieldSetting('opacity');
  //   $settings = $this->getSettings();
  // $color_hex = new ColorHex($item->color, $item->opacity);
  // switch ($settings['format']) {
  //     case 'hex':
  //       if ($opacity && $settings['opacity']) {
  //         $output = $color_hex->toString(TRUE);
  //       }
  //       else {
  //         $output = $color_hex->toString(FALSE);
  //       }
  //       break;
  // case 'rgb':
  //       if ($opacity && $settings['opacity']) {
  //         $output = $color_hex->toRgb()->toString(TRUE);
  //       }
  //       else {
  //         $output = $color_hex->toRgb()->toString(FALSE);
  //       }
  //       break;
  //   }
  // return $output;
  // }.
}
