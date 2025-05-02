<?php

namespace Drupal\exo_alchemist\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\exo_alchemist\ExoComponentManager;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldDisplayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines dynamic local tasks.
 */
class ExoAlchemistLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The eXo component manager.
   *
   * @var \Drupal\exo_alchemist\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * Creates a DynamicLocalTasks object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The layout manager.
   */
  public function __construct(TranslationInterface $string_translation, EntityTypeManagerInterface $entity_type_manager, ExoComponentManager $exo_component_manager) {
    $this->stringTranslation = $string_translation;
    $this->entityTypeManager = $entity_type_manager;
    $this->exoComponentManager = $exo_component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('string_translation'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.exo_component')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->exoComponentManager->getInstalledDefinitions() as $definition) {
      foreach ($definition->getFields() as $field) {
        /** @var \Drupal\exo_alchemist\Plugin\ExoComponentField\EntityDisplay $component_field */
        $component_field = $this->exoComponentManager->getExoComponentFieldManager()->createFieldInstance($field);
        if (!$component_field instanceof ExoComponentFieldDisplayInterface) {
          continue;
        }
        if (!$component_field->useDisplay()) {
          continue;
        }
        $view_mode = $field->safeId();
        $this->derivatives[$field->safeId()] = [
          'route_name' => "exo_alchemist.component.display.{$view_mode}",
          'title' => $this->t('Manage (@label)', ['@label' => $field->getLabel()]),
          'options' => [
            'attributes' => [
              'data-icon' => 'regular-shield-check',
            ],
          ],
          'base_route' => "exo_alchemist.component.preview",
          'weight' => 10,
          'cache_tags' => $this->entityTypeManager->getDefinition('entity_view_display')->getListCacheTags(),
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
