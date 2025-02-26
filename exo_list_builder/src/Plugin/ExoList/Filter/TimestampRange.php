<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Filter;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListFilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a eXo list element for rendering a content entity field.
 *
 * @ExoListFilter(
 *   id = "timestamp_range",
 *   label = @Translation("Range"),
 *   description = @Translation("Filter timestamp between two dates."),
 *   weight = 0,
 *   field_type = {
 *     "created",
 *     "changed",
 *     "timestamp",
 *   },
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = FALSE,
 * )
 */
class TimestampRange extends ExoListFilterBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Datetime\DateFormatterInterface definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * LogGeneratorBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValue() {
    return [
      's' => '',
      'e' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $value, EntityListInterface $entity_list, array $field) {
    $form = parent::buildForm($form, $form_state, $value, $entity_list, $field);
    $form['date'] = [
      '#type' => 'fieldset',
      '#title' => $field['display_label'],
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['date']['s'] = [
      '#type' => 'date',
      '#title' => $this->t('Start'),
      '#default_value' => !empty($value['s']) ? $this->dateFormatter->format($value['s'], 'custom', 'Y-m-d') : NULL,
    ];
    $form['date']['e'] = [
      '#type' => 'date',
      '#title' => $this->t('End'),
      '#default_value' => !empty($value['e']) ? $this->dateFormatter->format($value['e'], 'custom', 'Y-m-d') : NULL,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrlQuery(array $raw_value, EntityListInterface $entity_list, array $field) {
    $query = [];
    if (!empty($raw_value['date']['s'])) {
      $date = new \DateTime($raw_value['date']['s']);
      $date->setTime(0, 0);
      $query['s'] = $date->getTimestamp();
    }
    if (!empty($raw_value['date']['e'])) {
      $date = new \DateTime($raw_value['date']['e']);
      $date->setTime(23, 59);
      $query['e'] = $date->getTimestamp();
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty($raw_value) {
    return $this->checkEmpty($raw_value['date']['s']) && $this->checkEmpty($raw_value['date']['e']);
  }

  /**
   * {@inheritdoc}
   */
  public function toPreview($value, EntityListInterface $entity_list, array $field) {
    $output = [];
    if (!empty($value['s'])) {
      $output[] = $this->dateFormatter->format($value['s'], 'custom', 'n/j/y');
    }
    if (!empty($value['e'])) {
      $output[] = $this->dateFormatter->format($value['e'], 'custom', 'n/j/y');
    }
    return implode(' — ', $output);
  }

  /**
   * {@inheritdoc}
   */
  public function queryAlter($query, $value, EntityListInterface $entity_list, array $field) {
    if (!empty($value['s'])) {
      $query->condition($this->getQueryFieldName($field), $value['s'], '>=');
    }
    if (!empty($value['e'])) {
      $query->condition($this->getQueryFieldName($field), $value['e'], '<=');
    }
  }

}
