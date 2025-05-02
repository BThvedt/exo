<?php

declare(strict_types=1);

namespace Drupal\exo\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_recur\Plugin\Field\FieldFormatter\DateRecurBasicFormatter;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;

/**
 * Basic recurring date formatter.
 *
 * @FieldFormatter(
 *   id = "exo_date_recur",
 *   label = @Translation("eXo Date Recur"),
 *   field_types = {
 *     "date_recur"
 *   },
 *   provider = "date_recur"
 * )
 */
class ExoDateRecurFormatter extends DateRecurBasicFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'same_merge' => FALSE,
      'use_date_query' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['same_merge'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Merge start and end date if they are the same'),
      '#default_value' => $this->getSetting('same_merge'),
    ];

    $form['use_date_query'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use "date" GET query parameter to determine start date'),
      '#default_value' => $this->getSetting('use_date_query'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary['same_merge'] = [
      '#type' => 'inline_template',
      '#template' => '{{ label }}: {{ sample }}',
      '#context' => [
        'label' => $this->t('Merge same dates'),
        'sample' => $this->getSetting('same_merge') ? 'Yes' : 'No',
      ],
    ];
    $summary['use_date_query'] = [
      '#type' => 'inline_template',
      '#template' => '{{ label }}: {{ sample }}',
      '#context' => [
        'label' => $this->t('Use "date" query parameter'),
        'sample' => $this->getSetting('use_date_query') ? 'Yes' : 'No',
      ],
    ];
    return $summary;
  }

  /**
   * Get the occurrences for a field item.
   *
   * Occurrences are abstracted out to make it easier for extending formatters
   * to change.
   *
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   A field item.
   * @param int $maxOccurrences
   *   Maximum number of occurrences to render.
   *
   * @return \Drupal\date_recur\DateRange[]
   *   A render array.
   */
  protected function getOccurrences(DateRecurItem $item, $maxOccurrences): array {
    $start = 'now';
    if ($this->getSetting('use_date_query')) {
      $start = \Drupal::request()->query->get('date') ?: $start;
    }
    $start = new \DateTime($start);
    return $item->getHelper()
      ->getOccurrences($start, NULL, $maxOccurrences);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDateRangeValue(DrupalDateTime $startDate, DrupalDateTime $endDate, $isOccurrence): array {
    $range = parent::buildDateRangeValue($startDate, $endDate, $isOccurrence);
    if ($this->getSetting('same_merge')) {
      if (isset($range['start_date']['#text']) && isset($range['end_date']['#text'])) {
        if ($range['start_date']['#text'] == $range['end_date']['#text']) {
          return $range['start_date'];
        }
      }
    }
    return $range;
  }

}
