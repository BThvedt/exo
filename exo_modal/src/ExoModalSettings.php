<?php

namespace Drupal\exo_modal;

use Drupal\exo\ExoSettingsBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;

/**
 * Class UxMenuOptions.
 *
 * @package Drupal\exo_modal
 */
class ExoModalSettings extends ExoSettingsBase {

  /**
   * {@inheritdoc}
   */
  public function getModuleId() {
    return 'exo_modal';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['theme'] = [
      '#type' => 'exo_radios',
      '#title' => $this->t('Theme'),
      '#exo_style' => 'inline',
      '#options' => exo_theme_options(TRUE, TRUE),
      '#empty_option' => $this->t('Custom'),
      '#attributes' => [
        'class' => ['exo-modal-theme'],
      ],
      '#default_value' => $this->getSetting(['theme']),
    ];

    $form['theme_content'] = [
      '#type' => 'exo_radios',
      '#title' => $this->t('Content Theme'),
      '#exo_style' => 'inline',
      '#description' => $this->t('The theme of the modal content wrapper.'),
      '#options' => exo_theme_options(TRUE, TRUE),
      '#empty_option' => $this->t('Custom'),
      '#default_value' => $this->getSetting(['theme_content']),
    ];

    $form['trigger'] = [
      '#type' => 'details',
      '#title' => $this->t('Trigger'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['trigger']['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text'),
      '#description' => $this->t('The text to be placed within the link that will trigger the modal element.'),
      '#default_value' => $this->getSetting(['trigger', 'text']),
      '#required' => TRUE,
    ];
    $form['trigger']['icon'] = [
      '#type' => 'exo_icon',
      '#title' => $this->t('Icon'),
      '#default_value' => $this->getSetting(['trigger', 'icon']),
    ];
    $form['trigger']['icon_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Icon Only'),
      '#default_value' => $this->getSetting(['trigger', 'icon_only']),
    ];

    $form['modal'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Modal'),
      '#tree' => TRUE,
    ];

    $form['modal']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Fixed width of the modal. You can use %, px, em or cm. If not using a measure unity, PX will be assumed as measurement unit.'),
      '#default_value' => $this->getSetting(['modal', 'width']),
      '#required' => TRUE,
    ];

    $form['modal']['inherit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Inherit'),
      '#description' => $this->t("Will use the currently active modal's configuration. Useful when you want to overlay modals and keep their appearance consistant."),
      '#default_value' => $this->getSetting(['modal', 'inherit']),
    ];

    // Header settings.
    $form['modal']['header'] = [
      '#type' => 'details',
      '#title' => $this->t('Header'),
      '#process' => [[static::class, 'processParents']],
    ];

    $form['modal']['header']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t("Title in modal's header."),
      '#default_value' => $this->getSetting(['modal', 'title']),
    ];

    $form['modal']['header']['subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sub-title'),
      '#description' => $this->t("Caption below modal's title."),
      '#default_value' => $this->getSetting(['modal', 'subtitle']),
    ];

    $form['modal']['header']['icon'] = [
      '#type' => 'exo_icon',
      '#title' => $this->t('Icon'),
      '#default_value' => $this->getSetting(['modal', 'icon']),

    ];
    $form['modal']['header']['iconText'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon Text'),
      '#default_value' => $this->getSetting(['modal', 'iconText']),
    ];

    $form['modal']['header']['closeButton'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display close button in the header.'),
      '#default_value' => $this->getSetting(['modal', 'closeButton']),
    ];

    // Footer settings.
    $form['modal']['footer'] = [
      '#type' => 'details',
      '#title' => $this->t('Footer'),
      '#process' => [[static::class, 'processParents']],
    ];

    $form['modal']['footer']['smartActions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use smart actions'),
      '#description' => $this->t('The smart action feature will pull out valid form actions (buttons/links/etc) and place them in the modal footer.'),
      '#default_value' => $this->getSetting(['modal', 'smartActions']),
    ];

    // Position settings.
    $form['modal']['position'] = [
      '#type' => 'details',
      '#title' => $this->t('Position and Fullscreen'),
      '#process' => [[static::class, 'processParents']],
    ];

    foreach (['top', 'bottom', 'left', 'right'] as $position) {

      $form['modal']['position'][$position] = [
        '#type' => 'textfield',
        '#title' => $this->t('%label Position', ['%label' => ucfirst($position)]),
        '#default_value' => $this->getSetting(['modal', $position]),
      ];
    }

    $form['modal']['position']['openTall'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open aside in full-height.'),
      '#default_value' => $this->getSetting(['modal', 'openTall']),
    ];

    $id = Html::getUniqueId('exo-modal-open-fullscreen');
    $form['modal']['position']['openFullscreen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open aside in full-screen.'),
      '#default_value' => $this->getSetting(['modal', 'openFullscreen']),
      '#id' => $id,
    ];

    $form['modal']['position']['fullscreen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a button in aside header to allow full screen expanding.'),
      '#default_value' => $this->getSetting(['modal', 'fullscreen']),
      '#states' => [
        'disabled' => [
          '#' . $id => ['checked' => TRUE],
        ],
      ],
    ];

    $form['modal']['position']['bodyOverflow'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force overflow hidden in the document when opening the aside, closing the aside, overflow will be restored.'),
      '#default_value' => $this->getSetting(['modal', 'bodyOverflow']),
    ];

    // Feature settings.
    $form['modal']['features'] = [
      '#type' => 'details',
      '#title' => $this->t('Features'),
      '#process' => [[static::class, 'processParents']],
    ];

    $form['modal']['features']['focusInput'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Focus input.'),
      '#description' => $this->t('If enabled, when modal is opened, the first visible field is active.'),
      '#default_value' => $this->getSetting(['modal', 'focusInput']),
    ];

    $form['modal']['features']['overlay'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable background overlay.'),
      '#default_value' => $this->getSetting(['modal', 'overlay']),
    ];

    $form['modal']['features']['borderBottom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable border bottom.'),
      '#default_value' => $this->getSetting(['modal', 'borderBottom']),
    ];

    $form['modal']['features']['closeInBody'] = [
      '#type' => 'select',
      '#title' => $this->t('Display close button in the body.'),
      '#options' => [
        0 => $this->t('- No -'),
        'isOuterLeft' => $this->t('Outer Left'),
        'isInnerLeft' => $this->t('Inner Left'),
        'isOuterRight' => $this->t('Outer Right'),
        'isInnerRight' => $this->t('Inner Right'),
      ],
      '#default_value' => $this->getSetting(['modal', 'closeInBody']),
    ];

    $form['modal']['features']['padding'] = [
      '#type' => 'number',
      '#title' => $this->t('Padding'),
      '#field_suffix' => 'px',
      '#description' => $this->t('Aside inner margin.'),
      '#default_value' => $this->getSetting(['modal', 'padding']),
    ];

    $form['modal']['features']['radius'] = [
      '#type' => 'number',
      '#title' => $this->t('Radius'),
      '#field_suffix' => 'px',
      '#description' => $this->t('Border-radius that will be applied in aside.'),
      '#default_value' => $this->getSetting(['modal', 'radius']),
    ];

    $form['modal']['features']['class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Class'),
      '#description' => $this->t('Class(es) that will be added to the modal wrapper.'),
      '#default_value' => $this->getSetting(['modal', 'class']),
    ];

    // Transition settings.
    $form['modal']['transition'] = [
      '#type' => 'details',
      '#title' => $this->t('Transitions'),
      '#process' => [[static::class, 'processParents']],
    ];

    $form['modal']['transition']['transitionIn'] = [
      '#type' => 'select',
      '#title' => $this->t('Transition In'),
      '#description' => $this->t('Modal opening default transition.'),
      '#options' => exo_animate_in_options(),
      '#default_value' => $this->getSetting(['modal', 'transitionIn']),
    ];

    $form['modal']['transition']['transitionOut'] = [
      '#type' => 'select',
      '#title' => $this->t('Transition Out'),
      '#description' => $this->t('Modal opening default transition.'),
      '#options' => exo_animate_out_options(),
      '#default_value' => $this->getSetting(['modal', 'transitionOut']),
    ];

    $form['modal']['transition']['transitionInPanel'] = [
      '#type' => 'select',
      '#title' => $this->t('Panel Transition In'),
      '#description' => $this->t('Panel opening default transition. Only valid when modal has panel(s).'),
      '#options' => exo_animate_in_options(),
      '#default_value' => $this->getSetting(['modal', 'transitionInPanel']),
    ];

    $form['modal']['transition']['transitionOutPanel'] = [
      '#type' => 'select',
      '#title' => $this->t('Panel Transition Out'),
      '#description' => $this->t('Panel opening default transition. Only valid when modal has panel(s).'),
      '#options' => exo_animate_out_options(),
      '#default_value' => $this->getSetting(['modal', 'transitionOutPanel']),
    ];

    // Timeout settings.
    $form['modal']['timeout'] = [
      '#type' => 'details',
      '#title' => $this->t('Auto-open and Timeout'),
      '#process' => [[static::class, 'processParents']],
    ];

    $form['modal']['timeout']['autoOpen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('If true, the modal opens automatically without any user action.'),
      '#default_value' => $this->getSetting(['modal', 'autoOpen']),
    ];

    $form['modal']['timeout']['timeout'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Timeout'),
      '#description' => $this->t('Amount in milliseconds to close the modal or false to disable.'),
      '#default_value' => $this->getSetting(['modal', 'timeout']),
    ];

    $form['modal']['timeout']['timeoutProgressbar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable timeout progress bar.'),
      '#default_value' => $this->getSetting(['modal', 'timeoutProgressbar']),
    ];

    return $form;
  }

  /**
   * Get the panel form.
   *
   * @param array $settings
   *   The current settings of the panel.
   *
   * @return array
   *   The panel form.
   */
  public function buildPanelForm(array $settings = []) {
    $form = [];
    $settings += $this->getSetting(['panel']);
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trigger Open Text'),
      '#default_value' => $settings['text'],
    ];
    $form['icon'] = [
      '#type' => 'exo_icon',
      '#title' => $this->t('Trigger Open Icon'),
      '#default_value' => $settings['icon'],
    ];
    $form['icon_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trigger Open Icon Only'),
      '#default_value' => $settings['icon_only'],
    ];
    $form['return_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trigger Close Text'),
      '#default_value' => $settings['return_text'],
    ];
    $form['return_icon'] = [
      '#type' => 'exo_icon',
      '#title' => $this->t('Trigger Close Icon'),
      '#default_value' => $settings['return_icon'],
    ];
    $form['return_icon_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Trigger Close Icon Only'),
      '#default_value' => $settings['return_icon_only'],
    ];
    $form['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal Width'),
      '#default_value' => $settings['width'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function mergePresets($settings) {
    if (!empty($settings['exo_preset'])) {
      $preset = $this->getPreset($settings['exo_preset']);
      // Modal presets are sent via js so that we are setting fewer
      // drupalSettings.
      if (isset($preset['modal'])) {
        $preset['modal'] = ['preset' => $settings['exo_preset']];
      }
      $settings = NestedArray::mergeDeep($preset, $settings);
      unset($settings['label']);
    }
    return $settings;
  }

}
