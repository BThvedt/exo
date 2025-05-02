<?php

namespace Drupal\exo_icon\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_icon\Entity\ExoIconPackage;

/**
 * Plugin implementation of the 'icon' widget.
 *
 * @FieldWidget(
 *   id = "icon",
 *   label = @Translation("Icon"),
 *   field_types = {
 *     "icon"
 *   }
 * )
 */
class IconWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'packages' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['packages'] = [
      '#type' => 'checkboxes',
      '#title' => t('Icon Packages'),
      '#default_value' => $this->getSetting('packages'),
      '#description' => t('The icon packages that should be made available in this field. If no packages are selected, all will be made available.'),
      '#options' => ExoIconPackage::getLabels(),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['value'] = $element + [
      '#type' => 'exo_icon',
      '#default_value' => $items[$delta]->value ?? NULL,
      '#packages' => $this->getSetting('packages'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $enabled_packages = array_filter($this->getSetting('packages'));
    if ($enabled_packages) {
      $enabled_packages = array_intersect_key(ExoIconPackage::getLabels(), $enabled_packages);
      $summary[] = $this->t('With icon packages: @packages', ['@packages' => implode(', ', $enabled_packages)]);
    }
    else {
      $summary[] = $this->t('With icon packages: @packages', ['@packages' => 'All']);
    }
    return $summary;
  }

}
