<?php

namespace Drupal\exo_link\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\Component\Utility\Html;
use Drupal\exo_link\ExoLinkLinkitHelper;

/**
 * Plugin implementation of the 'link' widget.
 *
 * @FieldWidget(
 *   id = "exo_link",
 *   label = @Translation("eXo Link"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class ExoLinkWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'placeholder_url' => '',
      'placeholder_title' => '',
      'icon' => TRUE,
      'packages' => [],
      'target' => FALSE,
      'class' => FALSE,
      'class_list' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow icon selection'),
      '#description' => $this->t('If selected, icon selection will be enabled.'),
      '#default_value' => $this->getSetting('icon'),
    ];

    $element['packages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Icon Packages'),
      '#default_value' => $this->getSetting('packages'),
      '#description' => $this->t('The icon packages that should be made available in this field. If no packages are selected, all will be made available.'),
      '#options' => $this->getPackageOptions(),
      '#element_validate' => [
        [get_class($this), 'validatePackages'],
      ],
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][icon]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['target'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow target selection'),
      '#description' => $this->t('If selected, an "open in new window" checkbox will be made available.'),
      '#default_value' => $this->getSetting('target'),
    ];

    $element['class'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow adding custom CSS classes'),
      '#description' => $this->t('If selected, a textfield will be provided that will allow adding in custom CSS classes.'),
      '#default_value' => $this->getSetting('class'),
    ];

    return $element;
  }

  /**
   * Recursively clean up options array if no data-icon is set.
   */
  public static function validatePackages($element, FormStateInterface $form_state, $form) {
    $values = $form_state->getValue($element['#parents']);
    $values = array_filter($values);
    $form_state->setValueForElement($element, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#element_validate'][] = [get_called_class(), 'validateElement'];
    $element['title']['#weight'] = -1;

    $item = $items[$delta];
    $options = $item->get('options')->getValue();
    $attributes = $options['attributes'] ?? [];

    if (!empty($element['title'])) {
      $element = [
        'title' => $element['title'],
      ] + $element;
    }

    if ($this->getSetting('icon')) {
      $class_name = Html::getUniqueId('exo-link-widget-' . $this->fieldDefinition->getName() . '-' . $delta);
      $element['options']['attributes']['data-icon'] = [
        '#type' => 'exo_icon',
        '#title' => $this->t('Icon'),
        '#default_value' => $attributes['data-icon'] ?? NULL,
        '#packages' => $this->getPackages(),
        '#attributes' => [
          'class' => [$class_name],
        ],
      ];

      $element['options']['attributes']['data-icon-position'] = [
        '#type' => 'select',
        '#title' => $this->t('Icon position'),
        '#options' => [
          'before' => $this->t('Before'),
          'after' => $this->t('After'),
        ],
        '#default_value' => $attributes['data-icon-position'] ?? 'before',
        '#required' => TRUE,
        '#states' => [
          'visible' => [
            '.' . $class_name => ['filled' => TRUE],
          ],
        ],
      ];
    }

    if ($this->getSetting('class')) {
      $element['options']['attributes']['class'] = [
        '#type' => 'textfield',
        '#title' => $this->t('CSS classes'),
        '#description' => $this->t('Enter space-separated CSS class names that will be added to the link.'),
        '#default_value' => !empty($attributes['class']) ? implode(' ', $attributes['class']) : NULL,
      ];
      if (!empty($this->getSetting('class_list'))) {
        $element['options']['attributes']['class']['#type'] = 'select';
        $element['options']['attributes']['class']['#description'] = $this->t('A style may apply special styling the the link and/or its children.');
        $element['options']['attributes']['class']['#title'] = $this->t('Style');
        $element['options']['attributes']['class']['#options'] = ['' => $this->t('- Select -')] + $this->getSetting('class_list');
      }
    }

    if ($this->getSetting('target')) {
      $element['options']['attributes']['target'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Open link in new window'),
        '#description' => $this->t('See WCAG guidance on <a href="https://www.w3.org/WAI/WCAG21/Techniques/general/G200" target="_blank">opening links in new windows/tabs</a>.'),
        '#default_value' => !empty($attributes['target']),
      ];
    }

    if (!empty($element['options'])) {
      $element['options'] += [
        '#type' => 'fieldset',
        '#title' => $this->t('Options'),
        '#weight' => 100,
      ];
    }

    // If cardinality is 1, ensure a proper label is output for the field.
    if (!empty($element['options']) && $this->fieldDefinition->getFieldStorageDefinition()->getCardinality() == 1) {
      $element += [
        '#type' => 'fieldset',
      ];
      $element['uri']['#title'] = $this->t('URL');
    }

    return $element;
  }

  /**
   * Get packages available to this field.
   */
  protected function getPackages() {
    return $this->getSetting('packages');
  }

  /**
   * Get packages as options.
   *
   * @return array
   *   An array of id => label options.
   */
  protected function getPackageOptions() {
    return \Drupal::service('exo_icon.repository')->getPackagesAsLabels();
  }

  /**
   * Recursively clean up options array if no data-icon is set.
   */
  public static function validateElement($element, FormStateInterface $form_state, $form) {
    $values = $form_state->getValue($element['#parents']);
    $values['packages'] = array_filter($values['packages'] ?? []);
    if (!empty($values['options']['attributes']['target'])) {
      $values['options']['attributes']['target'] = '_blank';
    }
    if (empty($values['options']['attributes']['data-icon'])) {
      $values['options']['attributes']['data-icon-position'] = '';
    }
    if (!empty($values)) {
      foreach ($values['options']['attributes'] as $attribute => $value) {
        if (!empty($value)) {
          if ($attribute == 'class') {
            $value = explode(' ', $value);
          }
          $values['options']['attributes'][$attribute] = $value;
          $values['attributes'][$attribute] = $value;
        }
        else {
          unset($values['options']['attributes'][$attribute]);
          unset($values['attributes'][$attribute]);
        }
      }
    }
    $form_state->setValueForElement($element, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->supportsInternalLinks() && $this->getSetting('linkit')) {
      $summary[] = $this->t('Use Linkit: %profile', ['%profile' => $this->getSetting('linkit_profile')]);
    }
    if ($this->getSetting('icon')) {
      $summary[] = $this->t('Allow icon selection');
      $enabled_packages = array_filter($this->getSetting('packages'));
      if ($enabled_packages) {
        $enabled_packages = array_intersect_key($this->getPackageOptions(), $enabled_packages);
        $summary[] = $this->t('With icon packages: %packages', [
          '%packages' => implode(', ', $enabled_packages),
        ]);
      }
      else {
        $summary[] = $this->t('With icon packages: %packages', ['%packages' => 'All']);
      }
    }
    if ($this->getSetting('target')) {
      $summary[] = $this->t('Allow target selection');
    }
    if ($this->getSetting('class')) {
      $summary[] = $this->t('Allow custom CSS classes');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getLinkitUriAsDisplayableString($uri) {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity' && $entity = ExoLinkLinkitHelper::getEntityFromUri($uri)) {
      // If there is no fragment on the original URI, show the entity label.
      $fragment = parse_url($uri, PHP_URL_FRAGMENT);
      if (empty($fragment)) {
        $displayable_string = $entity->label();
      }
    }
    elseif ($scheme === 'mailto') {
      $email = explode(':', $uri)[1];
      $displayable_string = $email;
    }

    return $displayable_string;
  }

}
