<?php

namespace Drupal\exo_alchemist\Plugin;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\exo_alchemist\Command\ExoComponentCommand;
use Drupal\exo_alchemist\ExoComponentContextTrait;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for Component Field plugins.
 */
abstract class ExoComponentFieldBase extends PluginBase implements ExoComponentFieldInterface, ContextAwarePluginInterface {

  use ExoIconTranslationTrait;
  use ContextAwarePluginTrait;
  use ExoComponentContextTrait;

  /**
   * Editable flag.
   *
   * @var bool
   */
  protected $editable;

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    return $this->configuration['fieldDefinition'];
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentFieldDefinition($name) {
    return $this->getFieldDefinition()->getComponent()->getField($name);
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldChanges(array &$changes, ExoComponentFieldInterface $from_field, ?FieldStorageConfigInterface $from_storage = NULL, ?FieldConfigInterface $from_config = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function onInstall(ConfigEntityInterface $entity) {
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(ConfigEntityInterface $entity) {
  }

  /**
   * {@inheritdoc}
   */
  public function onUninstall(ConfigEntityInterface $entity) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldInstall() {}

  /**
   * {@inheritdoc}
   */
  public function onFieldUpdate() {}

  /**
   * {@inheritdoc}
   */
  public function onFieldUninstall() {}

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    return $this->pluginDefinition['properties'];
  }

  /**
   * {@inheritdoc}
   */
  public function getParents() {
    if ($this->isComputed()) {
      // If this is a computed field, direct to parent.
      return array_merge($this->getFieldDefinition()->getComponent()->getParents(), [
        $this->getFieldDefinition()->getName(),
      ]);
    }
    return array_merge($this->getFieldDefinition()->getComponent()->getParents(), [
      $this->getFieldDefinition()->safeId(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentsAsPath() {
    return implode('.', $this->getParents());
  }

  /**
   * {@inheritdoc}
   */
  public function getItemParents($delta) {
    return array_merge($this->getParents(), [
      $delta,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getItemParentsAsPath($delta) {
    return implode('.', $this->getItemParents($delta));
  }

  /**
   * {@inheritdoc}
   */
  public static function buildCommand(ExoComponentCommand $command, array &$data) {
  }

  /**
   * {@inheritdoc}
   */
  public function alterContexts(ContentEntityInterface $entity, array &$contexts) {}

  /**
   * {@inheritdoc}
   */
  public function isEditable(array $contexts) {
    if (isset($this->editable)) {
      return $this->editable === TRUE;
    }
    // Never allow when we are locked and not on the default storage.
    if ($this->isLocked($contexts) && !$this->isDefaultStorage($contexts)) {
      return FALSE;
    }
    if (!$this instanceof ExoComponentFieldFormInterface) {
      return FALSE;
    }
    return $this->getFieldDefinition()->isEditable();
  }

  /**
   * {@inheritdoc}
   */
  public function setEditable($editable = TRUE) {
    $this->editable = $editable === TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isRemoveable(array $contexts) {
    // Never allow when we are locked and not on the default storage.
    if ($this->isLocked($contexts) && !$this->isDefaultStorage($contexts)) {
      return FALSE;
    }
    return $this->getFieldDefinition()->supportsUnlimited();
  }

  /**
   * {@inheritdoc}
   */
  public function isHideable(array $contexts) {
    // Never allow when we are locked and not on the default storage.
    if ($this->isLocked($contexts) && !$this->isDefaultStorage($contexts)) {
      return FALSE;
    }
    // If it's required, you can't hide it.
    if ($this->isRequired()) {
      return FALSE;
    }

    // Be careful here. We used to have logic to check if the field is computed
    // and if it was, we would not allow it to be hidden. This was causing
    // issues with sequence fields that have subfields that are optional.
    if ($this->getFieldDefinition()->getComponent()->isComputed() || $this->isComputed()) {
      return FALSE;
    }

    return $this->getFieldDefinition()->isHideable();
  }

  /**
   * {@inheritdoc}
   */
  public function isComputed() {
    return $this->getFieldDefinition()->isComputed();
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired() {
    return $this->getFieldDefinition()->isRequired();
  }

  /**
   * {@inheritdoc}
   */
  public function isInvisible() {
    return $this->getFieldDefinition()->isInvisible();
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultStorageLocked() {
    return $this instanceof ExoComponentFieldDefaultStorageLockInterface;
  }

  /**
   * Return markup that can be used for a placeholder.
   *
   * @param string $title
   *   The placeholder text.
   * @param string $description
   *   The placeholder description.
   *
   * @return array
   *   A render array.
   */
  protected function componentPlaceholder($title, $description = NULL) {
    $context = [
      'title' => $title,
    ];
    if ($description) {
      $context['description'] = $this->icon($description)->setIcon('regular-question-circle');
    }
    return [
      '#type' => 'inline_template',
      '#template' => '<div class="exo-alchemist-component-placeholder exo-font"><span class="exo-alchemist-component-title">{{ title }}</span>{% if description %} <span class="exo-alchemist-component-description">{{ description }}</span>{% endif %}</div>',
      '#context' => $context,
    ];
  }

  /**
   * Return markup that can be used for a placeholder with default description.
   *
   * @param string $title
   *   The placeholder text.
   * @param string $description
   *   The placeholder description.
   *
   * @return array
   *   A render array.
   */
  protected function componentPlaceholderDefault($title, $description = 'This box will be replaced with the actual content when in full view.') {
    return $this->componentPlaceholder($title, $description);
  }

  /**
   * Gets the entity that has the component.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity.
   */
  protected function getParentEntity() {
    return $this->getContextValue('entity');
  }

  /**
   * Gets the entity view mode that has the component.
   *
   * @return string
   *   The entity view mode.
   */
  protected function getParentViewMode() {
    return $this->getContextValue('view_mode');
  }

}
