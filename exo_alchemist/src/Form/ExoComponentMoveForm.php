<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Form\MoveBlockForm;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form for moving a block.
 *
 * @internal
 *   Form classes are internal.
 */
class ExoComponentMoveForm extends MoveBlockForm {

  /**
   * Builds the move block form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The original delta of the section.
   * @param string $region
   *   The original region of the block.
   * @param string $uuid
   *   The UUID of the block being updated.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $form = parent::buildForm($form, $form_state, $section_storage, $delta, $region, $uuid);
    // We disable the region selection to prevent moving components into locked
    // regions.
    $form['region']['#access'] = FALSE;
    $form['components_wrapper']['components']['#header'][0] = $this->t('Components');
    return $form;
  }

}
