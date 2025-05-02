<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_alchemist\Ajax\ExoComponentFieldBlur;
use Drupal\exo_alchemist\Controller\ExoFieldParentsTrait;
use Drupal\layout_builder\Form\LayoutRebuildConfirmFormBase;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to update a block.
 *
 * @internal
 *   Form classes are internal.
 */
class ExoFieldRemoveForm extends LayoutRebuildConfirmFormBase {

  use ExoFieldParentsTrait;

  /**
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * The UUID of the block containiing the field.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The path to the field being removed.
   *
   * @var string
   */
  protected $path;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Remove Element');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will remove this element.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_remove_field';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL, $path = NULL) {
    $this->region = $region;
    $this->uuid = $uuid;
    $this->path = $path;
    return parent::buildForm($form, $form_state, $section_storage, $delta);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleSectionStorage(SectionStorageInterface $section_storage, FormStateInterface $form_state) {
    $component = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid);
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $block */
    $block = $component->getPlugin();
    $entity = $this->extractBlockEntity($block);

    $parents = explode('.', $this->path);
    $items = $this->getTargetItems($entity, $parents);
    if ($items->count() > 1) {
      $items->removeItem($this->getKeyFromParents($parents));
    }

    // Save changes.
    $configuration = $block->getConfiguration();
    $configuration['block_serialized'] = serialize($entity);
    $component->setConfiguration($configuration);
    $this->layoutTempstoreRepository->set($section_storage);
  }

  /**
   * Rebuilds the layout.
   */
  protected function rebuildAndClose(SectionStorageInterface $section_storage) {
    $response = $this->rebuildLayout($section_storage);
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    $response->addCommand(new ExoComponentFieldBlur());
    return $response;
  }

}
