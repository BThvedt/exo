<?php

namespace Drupal\exo_icon;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;

/**
 * Provides translatable icon markup class.
 *
 * This object, when cast to a string, will return the formatted, translated
 * string. Avoid casting it to a string yourself, because it is preferable to
 * let the rendering system do the cast as late as possible in the rendering
 * process, so that this object itself can be put, untranslated, into render
 * caches and thus the cache can be shared between different language contexts.
 *
 * @see \Drupal\Component\Render\FormattableMarkup
 * @see \Drupal\Core\StringTranslation\TranslationManager::translateString()
 * @see \Drupal\Core\Annotation\Translation
 */
class ExoIconTranslatableMarkup extends TranslatableMarkup {

  /**
   * The ExoIcon object.
   *
   * @var \Drupal\exo_icon\ExoIconInterface
   */
  protected $icon;

  /**
   * If true will show as icon-only.
   *
   * @var bool
   */
  protected $iconOnly = FALSE;

  /**
   * Set icon position as it related to the string.
   *
   * @var bool
   */
  protected $iconPosition = 'before';

  /**
   * The eXo icon repository service.
   *
   * @var \Drupal\exo_icon\ExoIconRepositoryInterface
   */
  protected $exoIconRepository;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The attached assets for this Ajax command.
   *
   * @var \Drupal\Core\Asset\AttachedAssets
   */
  protected $attachedAssets;

  /**
   * The Attribute object.
   *
   * @var \Drupal\Core\Template\Attribute
   */
  protected $attributes;

  /**
   * The Attribute object.
   *
   * @var \Drupal\Core\Template\Attribute
   */
  protected $iconAttributes;

  /**
   * The exo icon plugin manager.
   *
   * @var \Drupal\exo_icon\ExoIconManagerInterface
   */
  protected static $exoIconManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($string = '', array $arguments = [], array $options = [], ?TranslationInterface $string_translation = NULL) {
    if (is_a($string, '\Drupal\Core\StringTranslation\TranslatableMarkup')) {
      $arguments = $string->getArguments();
      $options = $string->getOptions();
      $string_translation = $string->getStringTranslation();
      $string = $string->getUntranslatedString();
    }
    if (is_a($string, '\Drupal\Component\Render\FormattableMarkup')) {
      $string = (string) $string;
    }
    if (is_a($string, '\Drupal\Core\Render\Markup')) {
      $string = (string) $string;
    }
    $this->attributes = new Attribute();
    $this->iconAttributes = new Attribute();
    parent::__construct($string, $arguments, $options, $string_translation);
  }

  /**
   * Create from string.
   *
   * @param string $string
   *   A string containing the English text to translate.
   * @param array $arguments
   *   (optional) An associative array of replacements to make after
   *   translation. Based on the first character of the key, the value is
   *   escaped and/or themed. See
   *   \Drupal\Component\Render\FormattableMarkup::placeholderFormat() for
   *   details.
   * @param array $options
   *   (optional) An associative array of additional options, with the following
   *   elements:
   *   - 'langcode' (defaults to the current language): A language code, to
   *     translate to a language other than what is used to display the page.
   *   - 'context' (defaults to the empty context): The context the source
   *     string belongs to.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   (optional) The string translation service.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown when $string is not a string.
   *
   * @return static
   */
  public static function fromString($string = '', array $arguments = [], array $options = [], ?TranslationInterface $string_translation = NULL) {
    return new static($string, $arguments, $options, $string_translation);
  }

  /**
   * Retrieves the exo icon manager.
   *
   * @return \Drupal\exo_icon\ExoIconManagerInterface
   *   The exo icon manager.
   */
  protected static function exoIconManager() {
    if (!isset(static::$exoIconManager)) {
      static::$exoIconManager = \Drupal::service('plugin.manager.exo_icon');
    }
    return static::$exoIconManager;
  }

  /**
   * Perform match using exo icon manager.
   *
   * @param array|string $prefix
   *   An optional prefix to filter the icon definitions by.
   * @param string $string
   *   An option string to use for icon matching.
   *
   * @return $this
   */
  public function match($prefix = [], $string = NULL) {
    $icon = static::exoIconManager()->getDefinitionMatch($string ?: $this->getUntranslatedString(), $prefix);
    if ($icon) {
      $this->setIcon($icon);
    }
    return $this;
  }

  /**
   * Set the icon text.
   *
   * @param string $text
   *   Set the untranslated text.
   *
   * @return $this
   */
  public function setText($text) {
    $this->string = $text;
    return $this;
  }

  /**
   * Render the object as the title only.
   *
   * @return string
   *   The translated string.
   */
  public function getText() {
    return parent::render();
  }

  /**
   * Given an icon id, check to see if we have an eXo icon match.
   *
   * @param string $icon_id
   *   The ID if the icon that should be used. This ID is defined in the
   *   eXo icon package.
   *
   * @return $this
   */
  public function setIcon($icon_id) {
    $this->icon = $this->getExoIconRepository()->getInstanceById($icon_id);
    return $this;
  }

  /**
   * Returns the icon.
   *
   * @return \Drupal\exo_icon\ExoIconInterface
   *   The eXo icon.
   */
  public function getIcon() {
    return $this->icon;
  }

  /**
   * Returns TRUE if we have an icon.
   *
   * @return bool
   *   Returns TRUE if we have an icon.
   */
  public function hasIcon() {
    return !empty($this->icon);
  }

  /**
   * Only show the icon.
   *
   * @param bool $icon_only
   *   (optional) Whether to hide the string and only show the icon.
   *
   * @return $this
   */
  public function setIconOnly($icon_only = TRUE) {
    $this->iconOnly = $icon_only;
    return $this;
  }

  /**
   * Check if is icon only.
   *
   * @return bool
   *   Returns TRUE if icon only.
   */
  public function isIconOnly() {
    return !empty($this->iconOnly);
  }

  /**
   * Set the icon position. Either 'before' or 'after'.
   *
   * @return $this
   */
  public function setIconPosition($position) {
    $this->iconPosition = $position == 'before' ? 'before' : 'after';
    return $this;
  }

  /**
   * Show the icon before the title.
   *
   * @return $this
   */
  public function setIconBefore() {
    $this->setIconPosition('before');
    return $this;
  }

  /**
   * Show the icon before the title.
   *
   * @return $this
   */
  public function setIconAfter() {
    $this->setIconPosition('after');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addClass($classes) {
    $this->attributes->addClass($classes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeClass($classes) {
    $this->attributes->removeClass($classes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addIconClass($classes) {
    $this->iconAttributes->addClass($classes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeIconClass($classes) {
    $this->iconAttributes->removeClass($classes);
    return $this;
  }

  /**
   * Renders the object as a string.
   *
   * @return string
   *   The translated string.
   */
  public function render() {
    return $this->toMarkup();
  }

  /**
   * Returns a fully rendered Markup representation of the object.
   *
   * @param bool $is_root_call
   *   (Internal use only.) Whether this is a recursive call or not. See
   *   ::renderRoot().
   *
   * @return \Drupal\Core\Render\Markup
   *   A Markup object.
   */
  public function toMarkup($is_root_call = FALSE) {
    $is_root_call = PHP_SAPI == 'cli' ? TRUE : $is_root_call;
    $renderer = \Drupal::service('renderer');
    $elements = $this->toRenderable();
    $output = $is_root_call ? $renderer->renderPlain($elements) : $renderer->render($elements);
    $this->attachedAssets = AttachedAssets::createFromRenderArray($elements);
    return $output;
  }

  /**
   * Returns a HTML string.
   *
   * @return string
   *   The HTML of the icon.
   */
  public function toString() {
    return preg_replace('/([\s])\1+/', ' ', preg_replace('/\r|\n/', '', $this->toMarkup()));
  }

  /**
   * Returns a render array representation of the object.
   *
   * @return mixed[]
   *   A render array.
   */
  public function toRenderable() {
    $output = [];
    $string = $this->getText();
    if ($icon = $this->getIcon()) {
      if (is_null($string)) {
        $output = $icon->toRenderable();
      }
      else {
        $output = [
          '#theme' => 'exo_icon_element',
          '#icon' => $icon,
          '#attributes' => $this->attributes->merge($icon->getAttributes())->toArray(),
          '#icon_attributes' => $this->iconAttributes->merge($icon->getIconAttributes())->toArray(),
          '#title' => Markup::create($string),
          '#icon_only' => $this->iconOnly,
          '#position' => $this->iconPosition,
        ];
      }
    }
    else {
      if (is_array($string)) {
        // Allow render arrays to be used.
        $output = $string;
      }
      else {
        $output = [
          '#markup' => $string,
        ];
      }
    }
    return $output;
  }

  /**
   * Gets the attached assets.
   *
   * @return \Drupal\Core\Asset\AttachedAssets|null
   *   The attached assets for this command.
   */
  public function getAttachedAssets() {
    return $this->attachedAssets;
  }

  /**
   * Gets the renderer service.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The renderer service.
   */
  protected function getRenderer() {
    if (!$this->renderer) {
      $this->renderer = \Drupal::service('renderer');
    }
    return $this->renderer;
  }

  /**
   * Gets the eXo icon repository service.
   *
   * @return \Drupal\exo_icon\ExoIconRepositoryInterface
   *   The eXo icon repository service.
   */
  protected function getExoIconRepository() {
    if (!$this->exoIconRepository) {
      $this->exoIconRepository = \Drupal::service('exo_icon.repository');
    }
    return $this->exoIconRepository;
  }

}
