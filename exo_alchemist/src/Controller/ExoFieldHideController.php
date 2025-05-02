<?php

namespace Drupal\exo_alchemist\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\exo_alchemist\Ajax\ExoComponentFieldBlur;
use Drupal\exo_alchemist\ExoComponentFieldManager;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines a controller to move a field forward in a list.
 *
 * @internal
 *   Controller classes are internal.
 */
class ExoFieldHideController implements ContainerInjectionInterface {

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

    if ($parent_entity = $this->extractBlockEntity($block)) {
      $parents = explode('.', $path);
      $key = $this->getKeyFromParents($parents);
      if (is_numeric($key)) {
        $entity = $this->getTargetEntity($parent_entity, $parents);
        /** @var \Drupal\Core\Field\FieldItemInterface $items */
        $items = $this->getTargetItems($parent_entity, $parents);
        $definition = $this->exoComponentManager->getEntityComponentDefinition($entity);
        $field = $definition->getFieldBySafeId($items->getName());

        if (empty($field)) {
          // There is an unknown issue with sequence fields.
          $entity = $parent_entity;
          $definition = $this->exoComponentManager->getEntityComponentDefinition($entity);
          $field = $definition->getFieldBySafeId($items->getName());
        }

        if (ExoComponentFieldManager::setHiddenFieldName($entity, $field->getName())) {
          $configuration = $block->getConfiguration();
          $configuration['block_serialized'] = serialize($parent_entity);
          $section = $section_storage->getSection($delta);
          $section->getComponent($uuid)->setConfiguration($configuration);
          $this->layoutTempstoreRepository->set($section_storage);
        }
        else {
          \Drupal::messenger('Could not hide element.', 'warning');
        }
      }
    }

    if ($this->isAjax()) {
      return $this->rebuildAndClose($section_storage);
    }
    else {
      $url = $section_storage->getLayoutBuilderUrl();
      return new RedirectResponse($url->setAbsolute()->toString());
    }
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
