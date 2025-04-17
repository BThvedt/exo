<?php

namespace Drupal\exo_modal;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\Component\Utility\Html;
use Drupal\exo\ExoSettingsInstanceInterface;
use Drupal\Core\Render\AttachmentsTrait;
use Drupal\Component\Utility\UrlHelper;

/**
 * Defines an eXo modal.
 */
class ExoModal implements ExoModalInterface {
  use RefinableCacheableDependencyTrait;
  use AttachmentsTrait;
  use ExoIconTranslationTrait;

  /**
   * The eXo Menu options service.
   *
   * @var \Drupal\ux\UxOptionsInterface
   */
  protected $exoSettings;

  /**
   * The unique id of this modal.
   *
   * @var string
   */
  protected $id = '';

  /**
   * The modal settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The modal attribute object.
   *
   * @var \Drupal\Core\Template\Attribute
   */
  protected $modalAttributes;

  /**
   * The trigger attribute object.
   *
   * @var \Drupal\Core\Template\Attribute
   */
  protected $triggerAttributes;

  /**
   * The modal trigger.
   *
   * @var \Drupal\exo_icon\ExoIconTranslatableMarkup
   */
  protected $trigger;

  /**
   * The modal content.
   *
   * @var array
   */
  protected $modal;

  /**
   * The modal section content.
   *
   * @var array
   */
  protected $sections = [];

  /**
   * The modal panel content.
   *
   * @var array
   */
  protected $panels = [];

  /**
   * Boolean indicating if the modal should be cached.
   *
   * @var bool
   */
  protected $cache = TRUE;

  /**
   * Flag for building drupal attachments.
   *
   * @var bool
   */
  protected $buildDrupalAttachments;

  /**
   * Constructs a new ExoModal.
   */
  public function __construct(ExoSettingsInstanceInterface $exo_settings, $id, $modal = NULL) {
    $this->exoSettings = $exo_settings;
    // The $id passed in will be used as is with exception is converting certain
    // characters to underscores. The caller should be responsible for making
    // sure this is a valid HTML id.
    $id = str_replace([' ', '_', '[', ']'], ['-', '-', '-', ''], $id);
    $this->id = $id;
    $this->triggerAttributes = new Attribute();
    $this->modalAttributes = new Attribute();
    $this->setTrigger($this->exoSettings->getSetting(['trigger', 'text']), $this->exoSettings->getSetting(['trigger', 'icon']), $this->exoSettings->getSetting(['trigger', 'icon_only']));
    if ($modal) {
      $this->setContent($modal);
    }
  }

  /**
   * The unique id of this instance.
   *
   * @return string
   *   A unique id.
   */
  public function getId() {
    if (!isset($this->id)) {
      $this->id = Html::getUniqueId('exo-modal-' . time());
    }
    return $this->id;
  }

  /**
   * Set the trigger text.
   *
   * @param string $text
   *   The text of the trigger.
   * @param string $icon_id
   *   The icon of the trigger.
   * @param bool $icon_only
   *   If TRUE will show the icon only.
   *
   * @return $this
   */
  public function setTrigger($text, $icon_id = NULL, $icon_only = NULL) {
    $this->trigger = $this->icon();
    $this->setTriggerText($text);
    $this->setTriggerIcon($icon_id);
    $this->setTriggerIconOnly($icon_only);
    $this->setTriggerAttributes([]);
    return $this;
  }

  /**
   * Set the trigger text.
   *
   * @param string $text
   *   The trigger text.
   *
   * @return $this
   */
  public function setTriggerText($text) {
    $this->getTrigger()->setText($text);
    return $this;
  }

  /**
   * Set the trigger icon.
   *
   * @param string $icon_id
   *   The trigger icon id.
   *
   * @return $this
   */
  public function setTriggerIcon($icon_id) {
    if ($icon_id) {
      $this->getTrigger()->setIcon($icon_id);
    }
    return $this;
  }

  /**
   * Set the trigger icon.
   *
   * @param bool $icon_only
   *   If TRUE will show the icon only.
   *
   * @return $this
   */
  public function setTriggerIconOnly($icon_only = TRUE) {
    $this->getTrigger()->setIconOnly((bool) $icon_only);
    return $this;
  }

  /**
   * Get the trigger.
   *
   * @return \Drupal\exo_icon\ExoIcon
   *   The trigger object.
   */
  public function getTrigger() {
    return $this->trigger;
  }

  /**
   * Get the trigger for rendering.
   *
   * @return array
   *   An associative array suitable for a render array.
   */
  public function getTriggerAsRenderable() {
    return $this->getTrigger()->render();
  }

  /**
   * Set modal.
   *
   * @param mixed $modal
   *   An array suitable for a render array.
   *
   * @return $this
   */
  public function setContent($modal) {
    $this->modal = $modal;
    return $this;
  }

  /**
   * Get the modal.
   *
   * @return mixed
   *   An associative array suitable for a render array.
   */
  public function getContent() {
    return $this->modal;
  }

  /**
   * {@inheritdoc}
   */
  public function addSectionContent($group, $id, $render) {
    $this->sections[$group][$id] = $render;
    return $this;
  }

  /**
   * Clear the modal sections.
   *
   * @return $this
   */
  public function clearSections() {
    $this->sections = [];
    return $this;
  }

  /**
   * Get the modal sections.
   *
   * @param string $group
   *   The section group to add the panel content to.
   *
   * @return mixed
   *   An associative array suitable for a render array.
   */
  public function getSections($group = NULL) {
    if ($group) {
      return !empty($this->sections[$group]) ? $this->sections[$group] : [];
    }
    return $this->sections;
  }

  /**
   * {@inheritdoc}
   */
  public function addPanel($group, $id, $render, array $settings = []) {
    $settings = $settings + $this->getSetting('panel');

    $trigger = [
      '#type' => 'inline_template',
      '#template' => '
        <div class="exo-modal-panel-trigger" data-exo-modal-panel="{{ key }}" data-exo-modal-panel-width="{{ width }}">
          <a href="#"><span class="hide">{{ return }}</span><span class="show">{{ text }}</span></a>
        </div>',
      '#context' => [
        'key' => $id,
        'width' => $settings['width'],
        'text' => $this->icon($settings['text'])->setIcon($settings['icon'])->setIconOnly($settings['icon_only']),
        'return' => $this->icon($settings['return_text'])->setIcon($settings['return_icon'])->setIconOnly($settings['return_icon_only']),
      ],
      '#weight' => $render['#weight'] ?? NULL,
    ];
    $this->addSectionContent($group, $id, $trigger);

    $panel = [
      '#type' => 'inline_template',
      '#template' => '<div class="exo-modal-panel" data-exo-modal-panel="{{ key }}">{{ content }}</div>',
      '#context' => [
        'key' => $id,
        'content' => $render,
      ],
      '#weight' => $render['#weight'] ?? NULL,
    ];
    $this->panels[$id] = $panel;

    return $this;
  }

  /**
   * Get the modal panels.
   *
   * @return mixed
   *   An associative array suitable for a render array.
   */
  public function getPanels() {
    return $this->panels;
  }

  /**
   * Gets the settings.
   *
   * @return array
   *   The array of settings.
   */
  public function getSettings() {
    return $this->exoSettings->getSettings();
  }

  /**
   * Gets data from this settings object.
   *
   * @param string $key
   *   A string that maps to a key within the configuration data.
   *   If no key is specified, then the entire data array is returned.
   *
   * @return mixed
   *   The data that was requested.
   */
  public function getSetting($key = '') {
    return $this->exoSettings->getSetting($key);
  }

  /**
   * Sets a setting.
   *
   * @return $this
   */
  public function setSetting($key, $value) {
    $this->exoSettings->setSetting($key, $value);
    return $this;
  }

  /**
   * Sets a modal settings.
   *
   * @return $this
   */
  public function setModalSetting($key, $value) {
    $this->exoSettings->setSetting(['modal', $key], $value);
    return $this;
  }

  /**
   * Sets the settings.
   *
   * @return $this
   */
  public function setSettings($values) {
    foreach ($values as $key => $value) {
      $this->exoSettings->setSetting($key, $value);
    }
    return $this;
  }

  /**
   * Get the modal for rendering.
   *
   * @return array
   *   An associative array suitable for a render array.
   */
  public function getContentAsRenderable() {
    $render = $this->getContent();
    if (!is_array($render)) {
      $render = ['#markup' => $render];
    }
    return $render;
  }

  /**
   * Set the trigger fallback URL.
   *
   * @param string $url
   *   The valid URL of the trigger. This will be used if javascript is
   *   disabled.
   *
   * @return $this
   */
  public function setTriggerUrl($url) {
    if (UrlHelper::isValid($url)) {
      $this->setTriggerAttribute('href', $url);
    }
    return $this;
  }

  /**
   * Adds classes or merges them on to array of existing CSS classes.
   *
   * @param string|array ...
   *   CSS classes to add to the class attribute array.
   *
   * @return $this
   */
  public function addTriggerClass($classes) {
    $this->triggerAttributes->addClass($classes);
    return $this;
  }

  /**
   * Removes argument values from array of existing CSS classes.
   *
   * @param string|array ...
   *   CSS classes to remove from the class attribute array.
   *
   * @return $this
   */
  public function removeTriggerClass() {
    $args = func_get_args();
    $this->triggerAttributes->removeClass($args);
    return $this;
  }

  /**
   * Sets the values for all attributes.
   *
   * @param array $attributes
   *   An array of attributes, keyed by attribute name.
   */
  public function setTriggerAttributes(array $attributes) {
    $this->triggerAttributes = new Attribute($attributes);
    // Always make sure class is set. Use class instead of ID as multiple
    // triggers can exist for a single modal.
    $this->addTriggerClass($this->getId() . '-trigger');
    return $this;
  }

  /**
   * Sets values for an attribute key.
   *
   * @param string $attribute
   *   Name of the attribute.
   * @param string|array $value
   *   Value(s) to set for the given attribute key.
   *
   * @return $this
   */
  public function setTriggerAttribute($attribute, $value) {
    $this->triggerAttributes->setAttribute($attribute, $value);
    return $this;
  }

  /**
   * Gets the values for all attributes.
   *
   * @return array
   *   An array of set attribute values, keyed by attribute name.
   */
  public function getTriggerAttributes() {
    return $this->triggerAttributes;
  }

  /**
   * Adds classes or merges them on to array of existing CSS classes.
   *
   * @param string|array ...
   *   CSS classes to add to the class attribute array.
   *
   * @return $this
   */
  public function addModalClass($classes) {
    $this->modalAttributes->addClass($classes);
    return $this;
  }

  /**
   * Removes argument values from array of existing CSS classes.
   *
   * @param string|array ...
   *   CSS classes to remove from the class attribute array.
   *
   * @return $this
   */
  public function removeModalClass() {
    $args = func_get_args();
    $this->modalAttributes->removeClass($args);
    return $this;
  }

  /**
   * Sets the values for all attributes.
   *
   * @param array $attributes
   *   An array of attributes, keyed by attribute name.
   */
  public function setModalAttributes(array $attributes) {
    $this->modalAttributes = new Attribute($attributes);
    return $this;
  }

  /**
   * Sets values for an attribute key.
   *
   * @param string $attribute
   *   Name of the attribute.
   * @param string|array $value
   *   Value(s) to set for the given attribute key.
   *
   * @return $this
   */
  public function setModalAttribute($attribute, $value) {
    $this->modalAttributes->setAttribute($attribute, $value);
    return $this;
  }

  /**
   * Gets the values for all attributes.
   *
   * @return array
   *   An array of set attribute values, keyed by attribute name.
   */
  public function getModalAttributes() {
    return $this->modalAttributes;
  }

  /**
   * Get javascript settings.
   *
   * These settings only include those different from the default settings.
   *
   * @return array
   *   An array of settings.
   */
  protected function getSiteModalSettings() {
    $settings = $this->exoSettings->getSiteSettingsDiff();
    return $settings['modal'] ?? NULL;
  }

  /**
   * Get javascript settings.
   *
   * These settings only include those different from the default settings.
   *
   * @return array
   *   An array of settings.
   */
  protected function getLocalModalSettings() {
    $settings = $this->exoSettings->getLocalSettingsDiff();
    $settings = $settings['modal'] ?? [];
    if (isset($settings['icon']) && empty($settings['iconText'])) {
      $icon = $this->icon()->setIcon($settings['icon']);
      // Render as root as this request may be made too late.
      // @see exo_modal_ajax_render_alter().
      $settings['iconText'] = $icon->toMarkup(TRUE);
      // Because the icon contains libraries, and this request can be made via
      // ajax, we need to attach the icon libraries to the renderable so that
      // the ajax response is aware of it and includes these libraries.
      $assets = $icon->getAttachedAssets();
      $attachments = [
        'library' => $assets->getLibraries(),
        'drupalSettings' => $assets->getSettings(),
      ];
      $this->addAttachments($attachments);
    }
    return $settings;
  }

  /**
   * Get javascript settings used in drupalSettings.
   *
   * @return array
   *   An array ready to be used with drupalSettings.
   */
  public function getDrupalSettings() {
    return [
      'exoModal' => [
        'defaults' => $this->getSiteModalSettings(),
        'modals' => [
          $this->getId() => ['id' => $this->getId()] + $this->getLocalModalSettings(),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachments() {
    if (!isset($this->buildDrupalAttachments)) {
      $this->buildDrupalAttachments = TRUE;
      $attachments = [
        'library' => [
          'exo_modal/theme',
        ],
        'drupalSettings' => $this->getDrupalSettings(),
      ];
      if ($this->exoSettings->getSetting(['modal', 'ajax'])) {
        $attachments['library'][] = 'core/drupal.ajax';
      }
      $this->addAttachments($attachments);
    }
    return $this->attachments;
  }

  /**
   * Set to enable or disable modal caching.
   *
   * @param bool $cache
   *   Set to TRUE to cache or FALSE to disable.
   *
   * @return $this
   */
  public function setCache($cache = TRUE) {
    $this->cache = $cache;
    return $this;
  }

  /**
   * Return trigger as render array.
   *
   * @return array
   *   The trigger render array.
   */
  public function toRenderableTrigger() {
    $render = [
      '#theme' => 'exo_modal_trigger',
      '#attributes' => $this->getTriggerAttributes()->toArray(),
      '#content' => $this->getTriggerAsRenderable(),
      '#attached' => $this->getAttachments(),
    ];
    if ($this->cache == TRUE) {
      $render['#cache'] = [
        'keys' => ['exo_modal', $this->getId(), 'trigger'],
        'tags' => $this->getCacheTags(),
        'contexts' => $this->getCacheContexts(),
        'max-age' => $this->getCacheMaxAge(),
      ];
    }
    return $render;
  }

  /**
   * Return modal as render array.
   *
   * @return array
   *   The trigger render array.
   */
  public function toRenderableModal() {
    $this->setModalAttribute('id', $this->getId());
    $this->addModalClass($this->getId());
    if (($theme = $this->exoSettings->getSetting(['theme'])) && $theme != '_custom') {
      $this->addModalClass('exo-modal-theme-' . $theme);
    }
    if (($theme = $this->exoSettings->getSetting(['theme_content'])) && $theme != '_custom') {
      $this->addModalClass('exo-modal-theme-content-' . $theme);
      $this->modalAttributes->offsetSet('data-exo-theme', $theme);
    }
    $render = [
      '#theme' => 'exo_modal',
      '#attributes' => $this->getModalAttributes()->toArray(),
      '#content' => $this->getContentAsRenderable(),
      '#sections' => $this->getSections(),
      '#panels' => $this->getPanels(),
      '#attached' => $this->getAttachments(),
    ];
    if ($this->cache == TRUE) {
      $render['#cache'] = [
        'keys' => ['exo_modal', $this->getId(), 'content'],
        'tags' => $this->getCacheTags(),
        'contexts' => $this->getCacheContexts(),
        'max-age' => $this->getCacheMaxAge(),
      ];
    }
    return $render;
  }

  /**
   * Returns trigger and modal as render array.
   *
   * @return array
   *   The trigger and modal render array.
   */
  public function toRenderable() {
    $build = [
      'trigger' => $this->toRenderableTrigger(),
    ];
    if (
      $modal = $this->getContent() ||
      $this->getSetting(['modal', 'iframe']) ||
      $this->getSetting(['modal', 'contentAjax']) ||
      $this->getSetting(['modal', 'contentSelector']) ||
      $this->getSetting(['modal', 'imageUrl'])
    ) {
      $build['modal'] = $this->toRenderableModal();
    }
    return $build;
  }

  /**
   * Wraps the modal options service.
   *
   * @return \Drupal\exo\ExoSettingsInterface
   *   The modal options service.
   */
  protected static function exoModalSettings() {
    return \Drupal::service('exo_modal.settings');
  }

}
