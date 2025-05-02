<?php

namespace Drupal\exo_icon;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Config\Entity\DraggableListBuilder;

/**
 * Provides a listing of eXo Icon Package entities.
 */
class ExoIconPackageListBuilder extends DraggableListBuilder {
  use ExoIconTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_icon_package_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('eXo Icon Package');
    $header['preview'] = $this->t('Preview');
    $header['type'] = $this->t('Type');
    $header['global'] = $this->t('Global');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\exo_icon\Entity\ExoIconPackageInterface $entity */
    $preview = [];
    $definitions = $entity->getDefinitions();
    shuffle($definitions);
    $definitions = array_slice($definitions, 0, 12);
    foreach ($entity->getInstances($definitions) as $icon) {
      $preview[] = $icon->toRenderable();
    }
    $row = [];
    $row['title']['data'] = [
      '#type' => 'link',
      '#title' => $this->t('<strong>@label</strong> <small>(.@machine)</small>', ['@label' => $entity->label(), '@machine' => $entity->getIconId()]),
      '#url' => Url::fromRoute('entity.exo_icon_package.canonical', [
        'exo_icon_package' => $entity->id(),
      ]),
    ];
    $row['preview']['#wrapper_attributes']['style'] = 'white-space:nowrap;text-align:left;';
    $row['preview']['data'] = $preview;
    $row['type']['data']['#markup'] = $this->icon($entity->getType());
    $row['global']['data']['#markup'] = $entity->isGlobal() ? $this->icon('Global')->setIcon('regular-check-circle')->setIconOnly() : $this->icon('Not Global')->setIcon('regular-circle')->setIconOnly();
    $row['status']['data']['#markup'] = $entity->status() ? $this->icon('Published')->setIcon('regular-check-circle')->setIconOnly() : $this->icon('Unpublished')->setIcon('regular-circle')->setIconOnly();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Builds the entity listing as renderable array for table.html.twig.
   *
   * @todo Add a link to add a new item to the #empty text.
   */
  public function render() {
    $build = parent::render();
    $build['#attached']['library'][] = 'exo_icon/admin';
    $build['table']['#attributes']['class'][] = 'exo-icon-table';
    return $build;
  }

}
