<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_alchemist\Controller\ExoFieldParentsTrait;
use Drupal\layout_builder\Form\LayoutRebuildConfirmFormBase;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to update a block.
 *
 * @internal
 *   Form classes are internal.
 */
class ExoComponentRestoreForm extends LayoutRebuildConfirmFormBase {

  use ExoFieldParentsTrait;

  /**
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * The UUID of the block being removed.
   *
   * @var string
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $label = $this->sectionStorage
      ->getSection($this->delta)
      ->getComponent($this->uuid)
      ->getPlugin()
      ->label();

    return $this->t('Restore Component', ['%label' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $component = $this->sectionStorage->getSection($this->delta)->getComponent($this->uuid);
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $block */
    $block = $component->getPlugin();
    $entity = $this->extractBlockEntity($block);

    $exo_component_manager = \Drupal::service('plugin.manager.exo_component');
    $plugin_id = $exo_component_manager->getPluginIdFromSafeId($entity->bundle());
    $definition = $exo_component_manager->getInstalledDefinition($plugin_id);
    if ($definition && $definition->isGlobal()) {
      return $this->t('This will restore this component to its original state.');
    }
    return $this->t('This will restore all elements that have been removed. It will not impact any existing elements.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Restore');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_remove_block';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $this->region = $region;
    $this->uuid = $uuid;
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

    $exo_component_manager = \Drupal::service('plugin.manager.exo_component');
    $plugin_id = $exo_component_manager->getPluginIdFromSafeId($entity->bundle());
    if ($definition = $exo_component_manager->getInstalledDefinition($plugin_id)) {
      $exo_component_manager->restoreEntity($definition, $entity);
    }

    // Save changes.
    $configuration = $block->getConfiguration();
    // Allow component to act on update.
    $definition = $exo_component_manager->getEntityComponentDefinition($entity);
    $exo_component_manager->onDraftUpdateLayoutBuilderEntity($definition, $entity);
    $configuration['block_serialized'] = serialize($entity);
    $component->setConfiguration($configuration);
    $this->layoutTempstoreRepository->set($section_storage);
  }

}
