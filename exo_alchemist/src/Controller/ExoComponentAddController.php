<?php

namespace Drupal\exo_alchemist\Controller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_alchemist\Ajax\ExoComponentFocus;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\Plugin\SectionStorage\ExoComponentSectionStorage;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines a controller to add a new section.
 *
 * @internal
 *   Controller classes are internal.
 */
class ExoComponentAddController implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use LayoutRebuildTrait;

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
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The UUID of the component.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * ExoComponentAddController constructor.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The layout manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid generator.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ExoComponentManager $exo_component_manager, BlockManagerInterface $block_manager, EntityTypeManagerInterface $entity_type_manager, UuidInterface $uuid, AccountInterface $current_user) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->exoComponentManager = $exo_component_manager;
    $this->blockManager = $block_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->uuidGenerator = $uuid;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.exo_component'),
      $container->get('plugin.manager.block'),
      $container->get('entity_type.manager'),
      $container->get('uuid'),
      $container->get('current_user')
    );
  }

  /**
   * Adds the new section.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $region
   *   The region of the block.
   * @param string $plugin_id
   *   The plugin ID of the layout to add.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The controller response.
   */
  public function build(SectionStorageInterface $section_storage, $delta, $region, $plugin_id) {
    $default_layout_plugin_id = 'layout_onecol';
    $definition = $this->exoComponentManager->getInstalledDefinition($plugin_id);

    if ($definition->isGlobal()) {
      $block_plugin_id = 'global_block:' . $definition->safeId();
    }
    else {
      $block_plugin_id = 'inline_block:' . $definition->safeId();
    }
    $data = [];
    if ($this->blockManager->hasDefinition($block_plugin_id)) {
      if ($definition->isGlobal()) {
        $entity = $this->exoComponentManager->loadGlobalEntity($definition);
      }
      else {
        $entity = $this->exoComponentManager->cloneEntity($definition);
      }
      if ($entity) {
        if (empty($section_storage->getSections())) {
          if ($section_storage instanceof ExoComponentSectionStorage && !$section_storage->isOverridden()) {
            // This storage wraps a component (e.g. a two-column section)
            // that has its own default layout, set up via
            // ExoComponentField::onFieldInstall() when the field was
            // installed. That layout (e.g. layout_twocol_section, with
            // "first"/"second" regions) must be used here instead of a
            // generic single-column fallback. Falling back to
            // $default_layout_plugin_id would create a section whose only
            // region is "content", so a component added into e.g. the
            // "first" region would be silently dropped on render.
            foreach ($section_storage->getDefaultSectionStorage()->getSections() as $default_section) {
              $section_storage->appendSection($default_section);
            }
          }
          if (empty($section_storage->getSections())) {
            $section_storage->insertSection($delta, new Section($default_layout_plugin_id));
          }
          $section = $section_storage->getSection($delta);
        }
        else {
          $section = $section_storage->getSection($delta);
        }

        $this->uuid = $this->uuidGenerator->generate();
        $component = new SectionComponent($this->uuid, $region, [
          'id' => $block_plugin_id,
          'label_display' => FALSE,
          'block_serialized' => serialize($entity),
        ]);
        $section->appendComponent($component);
      }
    }

    $this->layoutTempstoreRepository->set($section_storage);

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
    $response->addCommand(new ExoComponentFocus($this->uuid));
    return $response;
  }

}
