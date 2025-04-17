<?php

namespace Drupal\exo_alchemist\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_alchemist\Ajax\ExoComponentFocus;
use Drupal\exo_alchemist\Controller\ExoFieldParentsTrait;
use Drupal\layout_builder\Form\LayoutRebuildConfirmFormBase;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to update a block.
 *
 * @internal
 *   Form classes are internal.
 */
class ExoComponentCloneForm extends LayoutRebuildConfirmFormBase {

  use ExoFieldParentsTrait;

  /**
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * The UUID of the block being cloned.
   *
   * @var string
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Clone Component %label');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will clone this component and add it below the existing component.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Clone');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_clone_block';
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
    $section = $this->sectionStorage->getSection($this->delta);
    $component = $section->getComponent($this->uuid);
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $block */
    $block = $component->getPlugin();
    $entity = $this->extractBlockEntity($block);

    $exo_component_manager = $this->exoComponentManager();
    $plugin_id = $exo_component_manager->getPluginIdFromSafeId($entity->bundle());
    $definition = $exo_component_manager->getInstalledDefinition($plugin_id);
    $newEntity = $exo_component_manager->cloneEntity($definition, $entity);

    $newUuid = \Drupal::service('uuid')->generate();

    $newComponent = new SectionComponent($newUuid, $component->getRegion(), [
      'id' => $component->getPluginId(),
      'label_display' => FALSE,
      'block_serialized' => serialize($newEntity),
    ]);

    $section->insertAfterComponent($this->uuid, $newComponent);
    $this->layoutTempstoreRepository->set($this->sectionStorage);
    $this->uuid = $newUuid;
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = $this->rebuildAndClose($this->sectionStorage);
    $response->addCommand(new ExoComponentFocus($this->uuid));
    return $response;
  }

}
