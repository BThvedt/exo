<?php

namespace Drupal\exo_imagine\Plugin\ExoList\Element;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\exo\ExoSettingsInterface;
use Drupal\exo_imagine\ExoImagineManager;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListElementContentBase;

/**
 * Defines a eXo list element for rendering a content entity field.
 *
 * @ExoListElement(
 *   id = "imagine",
 *   label = @Translation("Imagine"),
 *   description = @Translation("Render the image."),
 *   weight = 0,
 *   field_type = {
 *     "image",
 *   },
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = FALSE,
 * )
 */
class ExoImagine extends ExoListElementContentBase implements ContainerFactoryPluginInterface {

  /**
   * The exo imagine manager.
   *
   * @var \Drupal\exo_imagine\ExoImagineManager
   */
  protected $exoImagineManager;

  /**
   * The exo imagine settings.
   *
   * @var \Drupal\exo\ExoSettingsInstanceInterface
   */
  protected $exoImagineSettings;

  /**
   * Constructs a new FieldBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\exo\ExoSettingsInterface $exo_imagine_settings
   *   The exo image settings.
   * @param \Drupal\exo_imagine\ExoImagineManager $exo_imagine_manager
   *   The exo image stype manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExoSettingsInterface $exo_imagine_settings, ExoImagineManager $exo_imagine_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $configuration = $this->getConfiguration();
    $this->exoImagineSettings = $exo_imagine_settings->createInstance($configuration['display']);
    $this->exoImagineManager = $exo_imagine_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('exo_imagine.settings'),
      $container->get('exo_imagine.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'breakpoints' => '[]',
      'display' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, EntityListInterface $entity_list, array $field) {
    $form = parent::buildConfigurationForm($form, $form_state, $entity_list, $field);
    $configuration = $this->getConfiguration();

    $style_options = ['' => $this->t('- New -')];
    foreach ($this->getSelectableImagineStyles() as $imagine_style) {
      $style_options[$imagine_style->id()] = str_replace(['eXo (', ')'], '', $imagine_style->label());
    }

    $form['breakpoints']['#element_validate'][] = [
      get_class($this),
      'validateBreakpoints',
    ];

    $unique_id = Html::getUniqueId($field['id'] . '_imagine');

    foreach ($this->getBreakpointSettings() as $key => $data) {
      $id = $unique_id . '-' . $key;
      $imagine_style = $this->exoImagineManager->getImagineStyle($data['width'], $data['height'], $data['unique'], NULL, FALSE);
      $states = [
        'visible' => [
          '#' . $id . '-style' => ['value' => ''],
        ],
      ];
      $form['breakpoints'][$key] = [
        '#type' => 'details',
        '#title' => $data['label'],
        '#open' => !empty($data['width']) || !empty($data['height']),
      ];
      $form['breakpoints'][$key]['style'] = [
        '#type' => 'select',
        '#title' => $this->t('Style'),
        '#options' => $style_options,
        '#default_value' => $imagine_style ? $imagine_style->id() : '',
        '#id' => $id . '-style',
      ];
      $form['breakpoints'][$key]['width'] = [
        '#type' => 'number',
        '#title' => $this->t('Width'),
        '#field_suffix' => ' ' . t('pixels'),
        '#default_value' => $data['width'],
        '#min' => 1,
        '#step' => 1,
        '#states' => $states,
      ];
      $form['breakpoints'][$key]['height'] = [
        '#type' => 'number',
        '#title' => $this->t('Height'),
        '#field_suffix' => ' ' . t('pixels'),
        '#default_value' => $data['height'],
        '#min' => 1,
        '#step' => 1,
        '#states' => $states,
      ];
    }

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display Overrides'),
    ];
    $form['display'] = $this->exoImagineSettings->buildForm([], $form_state);
    $form['display']['#element_validate'][] = [
      get_class($this),
      'validateElementDisplay',
    ];

    // $image_styles = image_style_options(FALSE);
    // $form['image_style'] = [
    //   '#title' => $this->t('Image style'),
    //   '#type' => 'select',
    //   '#default_value' => $configuration['image_style'],
    //   '#empty_option' => $this->t('None (original image)'),
    //   '#options' => $image_styles,
    // ];
    return $form;
  }

  /**
   * Validate breakpoint settings.
   */
  public static function validateBreakpoints(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    foreach ($values as &$data) {
      if (!empty($data['style'])) {
        $exo_imagine_style = \Drupal::service('exo_imagine.manager')->getImagineStyleByStyleId($data['style']);
        if ($exo_imagine_style) {
          $data['width'] = $exo_imagine_style->getWidth();
          $data['height'] = $exo_imagine_style->getHeight();
          $data['unique'] = $exo_imagine_style->getUnique();
          $data['height'] = $exo_imagine_style->getHeight();
        }
      }
    }
    $form_state->setValue($element['#parents'], $values);
  }

  /**
   * Validate element display settings.
   */
  public static function validateElementDisplay(array $element, FormStateInterface $form_state) {
    $exo_imagine_settings = \Drupal::service('exo_imagine.settings');
    $values = $form_state->getValue($element['#parents']);
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    $instance = $exo_imagine_settings->createInstance($values);
    $instance->validateForm($element, $subform_state);
    $instance->submitForm($element, $subform_state);
  }

  /**
   * Get breakpoint settings.
   */
  public function getBreakpointSettings() {
    $configuration = $this->getConfiguration();
    $settings = [];
    $breakpoints = $this->exoImagineManager->getBreakpoints();
    $formatter_settings = $configuration['breakpoints'];
    foreach ($breakpoints as $key => $breakpoint) {
      $settings[$key] = [
        'label' => $breakpoint->getLabel(),
        'media' => $breakpoint->getMediaQuery(),
        'width' => NULL,
        'height' => NULL,
        'quality' => NULL,
        'unique' => '',
      ];
      if (isset($formatter_settings[$key])) {
        $breakpoint_settings = $formatter_settings[$key];
        $settings[$key]['width'] = $breakpoint_settings['width'] ?? NULL;
        $settings[$key]['height'] = $breakpoint_settings['height'] ?? NULL;
      }
    }
    return $settings;
  }

  /**
   * Get selectable styles.
   *
   * @return \Drupal\exo_imagine\Entity\ExoImagineStyleInterface[]
   *   Selectable styles.
   */
  protected function getSelectableImagineStyles() {
    return array_filter(\Drupal::service('exo_imagine.manager')->getImagineStyles(), function ($imagine_style) {
      /** @var \Drupal\exo_imagine\Entity\ExoImagineStyleInterface $imagine_style */
      return strpos($imagine_style->id(), ExoImagineManager::PREVIEW_BLUR_QUALITY . 'q') === FALSE;
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function view(EntityInterface $entity, array $field) {
    $configuration = $this->getConfiguration();
    $field_items = $this->getItems($entity, $field);
    if (!$field_items || $field_items->isEmpty()) {
      return $configuration['empty'];
    }
    $field_items = $this->prepareItems($field_items);
    return $field_items->view([
      'type' => 'exo_imagine',
      'label' => 'hidden',
      'settings' => [
        'breakpoints' => $this->getBreakpointSettings(),
        'display' => $this->getConfiguration()['display'],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function viewPlainItem(EntityInterface $entity, FieldItemInterface $field_item, array $field) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $field_item->entity;
    return $file->getFileUri();
  }

}
