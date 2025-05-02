<?php

namespace Drupal\exo_alchemist\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\exo_alchemist\Ajax\ExoComponentFieldFocus;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines a controller to clone a field.
 *
 * @internal
 *   Controller classes are internal.
 */
class ExoFieldCloneController implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use LayoutRebuildTrait;
  use ExoFieldParentsTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The eXo component manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * The parent component entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $component;

  /**
   * Constructs a new block form.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The layout manager.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ExoComponentManager $exo_component_manager) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->exoComponentManager = $exo_component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.exo_component')
    );
  }

  /**
   * Clone an eXo field.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region of the block.
   * @param string $uuid
   *   The UUID of the block being updated.
   * @param string $path
   *   The path to the field requested for updating.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The controller response.
   */
  public function build(?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL, $path = NULL) {

    $component = $section_storage->getSection($delta)->getComponent($uuid);
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $block */
    $block = $component->getPlugin();
    $updated_path = $path;
    $this->component = $this->extractBlockEntity($block);
    $uuid = $this->component->uuid();
    $definition = $this->exoComponentManager->getEntityComponentDefinition($this->component);

    if ($this->component) {
      $parents = explode('.', $path);
      // We need to use the parent entity in the updated path. A parent only
      // exists if this component is nested more than once.
      if ($target = $this->getTargetParent($this->component, $parents)) {
        $uuid = $target['entity']->uuid();
      }
      $field_delta = (int) end($parents);
      if (is_numeric($field_delta)) {
        $items = $this->getTargetItems($this->component, $parents);
        $cardinality = $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
        if ($items->count() === $cardinality) {
          \Drupal::messenger()->addError(t('No more items can be added as the maximum has already been reached.'));
        }
        else {
          $clone_items = clone $items;
          $items->setValue(NULL);
          foreach ($clone_items as $item_delta => $item) {
            $items->appendItem($item->getValue());
            if ($field_delta === $item_delta) {
              // Set new path.
              $updated_path = explode('.', $updated_path);
              array_pop($updated_path);
              array_push($updated_path, $item_delta + 1);
              $updated_path = implode('.', $updated_path);
              $value = $item->getValue();
              $entity = !empty($value['entity']) ? $value['entity'] : $item->entity;
              if ($entity && $entity->getEntityTypeId() == ExoComponentManager::ENTITY_TYPE) {
                $item_definition = $this->exoComponentManager->getEntityBundleComponentDefinition($entity->type->entity);
                $value['target_id'] = NULL;
                $value['entity'] = $this->exoComponentManager->cloneEntity($item_definition, $entity);
              }
              $items->appendItem($value);
            }
          }

          $configuration = $block->getConfiguration();
          // Allow component to act on update.
          $this->exoComponentManager->onDraftUpdateLayoutBuilderEntity($definition, $this->component);
          $configuration['block_serialized'] = serialize($this->component);
          $component->setConfiguration($configuration);
          $this->layoutTempstoreRepository->set($section_storage);
        }
      }
      if ($this->isAjax()) {
        return $this->rebuildAndClose($section_storage, $updated_path . '.' . $uuid);
      }
    }

    $url = $section_storage->getLayoutBuilderUrl();
    return new RedirectResponse($url->setAbsolute()->toString());
  }

  /**
   * Rebuilds the layout.
   */
  protected function rebuildAndClose(SectionStorageInterface $section_storage, $path) {
    $response = $this->rebuildLayout($section_storage);
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    $response->addCommand(new ExoComponentFieldFocus($path));
    return $response;
  }

}
