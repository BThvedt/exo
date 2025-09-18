<?php

namespace Drupal\exo\Plugin\Field\FieldFormatter;

use Drupal\options\Plugin\Field\FieldFormatter\OptionsDefaultFormatter;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'string' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_list_formatter",
 *   label = @Translation("eXo List"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string",
 *   }
 * )
 */
class ExoListFormatter extends OptionsDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'delimiter' => '',
      'prefix' => '',
      'suffix' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter.'),
      '#description' => $this->t('A character which should be used to separate the items.'),
      '#default_value' => $this->getSetting('delimiter'),
      '#size' => 1,
    ];
    $element['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => $this->getSetting('prefix'),
    ];
    $element['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix'),
      '#default_value' => $this->getSetting('suffix'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($this->getSetting('delimiter')) {
      $summary[] = $this->t('Delimiter: @delimiter', ['@delimiter' => $this->getSetting('delimiter')]);
    }
    if ($this->getSetting('prefix')) {
      $summary[] = $this->t('Prefix: @prefix', ['@prefix' => $this->getSetting('prefix')]);
    }
    if ($this->getSetting('suffix')) {
      $summary[] = $this->t('Suffix: @suffix', ['@suffix' => $this->getSetting('suffix')]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // Only collect allowed options if there are actually items to display.
    if ($items->count()) {
      $provider = $items->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getOptionsProvider('value', $items->getEntity());
      // Flatten the possible options, to support opt groups.
      $options = OptGroup::flattenOptions($provider->getPossibleOptions());

      foreach ($items as $delta => $item) {
        $value = $item->value;
        // If the stored value is in the current set of allowed values, display
        // the associated label, otherwise just display the raw value.
        $output = $options[$value] ?? $value;
        $elements[$delta] = [
          '#markup' => $output,
          '#allowed_tags' => FieldFilteredMarkup::allowedTags(),
        ];

        $elements[$delta]['#prefix'] = '<span class="field-item">' . $this->getSetting('prefix');
        $elements[$delta]['#suffix'] = '</span>';

        if (!empty($this->getSetting('delimiter')) && $items->count() > 1 && count($elements) < 2) {
          $elements[$delta]['#suffix'] = $this->getSetting('delimiter') . $elements[$delta]['#suffix'];
        }
        $elements[$delta]['#suffix'] = $this->getSetting('suffix') . $elements[$delta]['#suffix'];
      }
    }

    return $elements;
  }

}
