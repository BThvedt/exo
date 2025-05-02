<?php

namespace Drupal\exo_toolbar;

use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Component\Utility\SortArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\exo_icon\ExoIconTranslationTrait;

/**
 * Provides a toolbar element for use within toolbar items.
 */
class ExoToolbarElement implements ExoToolbarElementInterface {
  use ExoToolbarJsSettingsTrait;
  use ExoIconTranslationTrait;

  /**
   * The element id.
   *
   * @var string
   */
  protected $id;

  /**
   * The element tag.
   *
   * @var string
   */
  protected $tag = 'div';

  /**
   * The element title.
   *
   * @var string
   */
  protected $title;

  /**
   * The element url.
   *
   * @var \Drupal\Core\Url
   */
  protected $url;

  /**
   * The eXo icon id.
   *
   * @var string
   */
  protected $icon = '';

  /**
   * The icon position.
   *
   * @var string
   */
  protected $iconPosition = 'before';

  /**
   * The icon size.
   *
   * @var string
   */
  protected $iconSize = 'standard';

  /**
   * The image uri.
   *
   * @var string
   */
  protected $image;

  /**
   * The image style.
   *
   * @var string
   */
  protected $imageStyle = 'exo_toolbar_regular';

  /**
   * The image position.
   *
   * @var string
   */
  protected $imagePosition = 'before';

  /**
   * Flag set to true if image is external.
   *
   * @var bool
   */
  protected $imageIsExternal = FALSE;

  /**
   * Flag to indicate badge usage.
   *
   * @var bool
   */
  protected $useBadge = FALSE;

  /**
   * The badge value.
   *
   * @var string
   */
  protected $badgeValue = NULL;

  /**
   * The element weight.
   *
   * @var string
   */
  protected $weight = 0;

  /**
   * Flag that determines if aside label is used.
   *
   * @var bool
   */
  protected $useAsideLabel = FALSE;

  /**
   * The element access property.
   *
   * @var string
   */
  protected $access = TRUE;

  /**
   * The element attributes.
   *
   * @var \Drupal\Core\Template\Attribute
   */
  protected $attributes;

  /**
   * The wrapping item attributes.
   *
   * @var \Drupal\Core\Template\Attribute
   */
  protected $itemAttributes;

  /**
   * The element libraries.
   *
   * @var array
   */
  protected $libraries = [];

  /**
   * The element cachable metadata.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected $cacheableMetadata;

  /**
   * A subset of elements.
   *
   * @var \Drupal\exo_toolbar\ExoToolbarElement[]
   */
  protected $subElements;

  /**
   * The #theme to use when rendering the element subset.
   *
   * @var string
   */
  protected $subElementTheme = 'exo_toolbar_list';

  /**
   * The element tooltip.
   *
   * @var array
   */
  protected $tip;

  /**
   * Constructs a new ExoToolbarElement object.
   *
   * @param array $values
   *   Optional properties of the element.
   */
  public function __construct(array $values = []) {
    $attributes = $values['attributes'] ?? [];
    $this->id = Html::getUniqueId('exo-toolbar-element');
    $attributes['id'] = $this->id;
    if (isset($attributes['class']) && !is_array($attributes['class'])) {
      $attributes['class'] = [$attributes['class']];
    }
    $attributes['class'][] = 'exo-toolbar-element';
    $this->attributes = new Attribute($attributes);
    $this->itemAttributes = new Attribute();
    $this->cacheableMetadata = new CacheableMetadata();
    if (isset($values['tag'])) {
      $this->setTag($values['tag']);
    }
    if (isset($values['title'])) {
      $this->setTitle($values['title']);
    }
    if (isset($values['url'])) {
      $this->setUrl($values['url']);
    }
    if (isset($values['weight'])) {
      $this->setWeight($values['weight']);
    }
    if (isset($values['access'])) {
      $this->setAccess($values['access']);
    }
    if (isset($values['icon'])) {
      $this->setIcon($values['icon']);
    }
    if (isset($values['image'])) {
      $this->setImage($values['image']);
    }
    if (isset($values['badge'])) {
      $this->setBadge($values['badge']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $values = []) {
    return new static($values);
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function setTag($tag) {
    $this->tag = $tag;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTag() {
    return $this->tag ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function addLibrary($library) {
    if (!in_array($library, $this->libraries)) {
      $this->libraries[] = $library;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries() {
    return $this->libraries;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight = 0) {
    $this->weight = $weight ?: 0;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccess($access) {
    $this->access = $access;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccess() {
    return $this->access;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl($url) {
    if ($url instanceof Url) {
      $url = $url;
    }
    else {
      $url = Url::fromUri($url);
    }
    $this->url = $url;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->url ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setAsLink($url = NULL) {
    $this->setTag('a');
    $this->addClass('as-link');
    if ($url) {
      $this->setUrl($url);
    }
    if ($url = $this->getUrl()) {
      // External URLs can not have cacheable metadata.
      if ($url->isExternal()) {
        $href = $url->toString(FALSE);
      }
      elseif ($url->isRouted() && $url->getRouteName() === '<nolink>') {
        $href = '';
      }
      else {
        $generated_url = $url->toString(TRUE);
        // The result of the URL generator is a plain-text URL to use as the
        // href attribute, and it is escaped by \Drupal\Core\Template\Attribute.
        $href = $generated_url->getGeneratedUrl();

        if ($url->isRouted()) {
          // Set data element for active link setting.
          // @todo Drupal's active-link.js seems to not work for this. Why?
          $system_path = $url->getInternalPath();
          // Special case for the front page.
          $path = $system_path == '' ? '<front>' : $system_path;
          $this->setAttribute('data-drupal-link-system-path', $path);
        }
      }
      $this->setAttribute('href', $href);
    }
    else {
      $this->setAttribute('href', '#');
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return $this->icon;
  }

  /**
   * {@inheritdoc}
   */
  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setIconPosition($position) {
    $this->iconPosition = $position == 'after' ? 'after' : 'before';
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIconPosition() {
    return $this->iconPosition;
  }

  /**
   * {@inheritdoc}
   */
  public function setIconSize($size = 'standard') {
    $this->iconSize = in_array($size, ['small', 'standard', 'large']) ? $size : 'standard';
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIconSize() {
    return $this->iconSize;
  }

  /**
   * {@inheritdoc}
   */
  public function setImage($image_uri) {
    if (UrlHelper::isExternal($image_uri)) {
      $this->imageIsExternal = TRUE;
      $this->image = $image_uri;
    }
    else {
      $image = \Drupal::service('image.factory')->get($image_uri);
      if ($image->isValid()) {
        $this->image = $image;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getImage() {
    return $this->image;
  }

  /**
   * {@inheritdoc}
   */
  public function imageIsExternal() {
    return $this->getImage() && (bool) $this->imageIsExternal;
  }

  /**
   * {@inheritdoc}
   */
  public function setImageStyle($style) {
    $this->imageStyle = $style;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageStyle() {
    return $this->imageStyle;
  }

  /**
   * {@inheritdoc}
   */
  public function setImagePosition($position) {
    $this->imagePosition = $position == 'after' ? 'after' : 'before';
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getImagePosition() {
    return $this->imagePosition;
  }

  /**
   * {@inheritdoc}
   */
  public function useBadge($use = TRUE) {
    $this->useBadge = $use;
    return $this->useBadge;
  }

  /**
   * {@inheritdoc}
   */
  public function setBadge($value) {
    $this->useBadge();
    $this->badgeValue = (string) $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBadge() {
    return $this->badgeValue;
  }

  /**
   * {@inheritdoc}
   */
  public function useTip($use = TRUE) {
    if ($use == TRUE) {
      $this->tip = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->getTitle(),
        '#attributes' => [
          'role' => 'tooltip',
          'id' => $this->id() . '-label',
          'class' => ['exo-toolbar-item-aside-label'],
        ],
        '#weight' => 1000,
      ];
    }
    else {
      $this->tip = NULL;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function useAsideLabel($use = TRUE) {
    $this->useAsideLabel = $use;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldUseAsideLabel() {
    return $this->useAsideLabel === TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAsideLabel() {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->getTitle(),
      '#attributes' => [
        'role' => 'tooltip',
        'id' => $this->id() . '-label',
        'class' => ['exo-toolbar-item-aside-label'],
      ],
      '#weight' => 1000,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setMarkOnly($set = TRUE) {
    $this->setVerticalMarkOnly($set);
    $this->setHorizontalMarkOnly($set);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setVerticalMarkOnly($set = TRUE) {
    if ($set) {
      $this->useAsideLabel();
    }
    $class_name = 'mark-only-vertical';
    $set ? $this->itemAttributes->addClass($class_name) : $this->itemAttributes->removeClass($class_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHorizontalMarkOnly($set = TRUE) {
    if ($set) {
      $this->useAsideLabel();
    }
    $class_name = 'mark-only-horizontal';
    $set ? $this->itemAttributes->addClass($class_name) : $this->itemAttributes->removeClass($class_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addSubElement(array $values = []) {
    $subelement = self::create($values);
    $this->subElements[] = $subelement;
    return $subelement;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubElements(array $values = []) {
    return $this->subElements;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubElementTheme() {
    return $this->subElementTheme;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubElementTheme($theme) {
    $this->subElementTheme = $theme;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes() {
    return $this->attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function addClass() {
    $this->attributes->addClass(func_get_args());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeClass() {
    $this->attributes->removeClass(func_get_args());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setAttribute($attribute, $value) {
    $this->attributes->offsetSet($attribute, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAttribute() {
    $this->attributes->removeAttribute(func_get_args());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemAttributes() {
    return $this->itemAttributes;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return $this->cacheableMetadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getIconRenderable() {
    $build = [];
    if ($icon = $this->getIcon()) {
      $this->itemAttributes->addClass('has-mark');
      $this->addClass('has-mark');
      $size = $this->getIconSize();
      $build = $this->icon()->setIcon($this->getIcon())->toRenderable();
      if ($size !== 'standard') {
        $build['#attributes']['class'][] = 'exo-icon-size-' . $size;
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getImageRenderable() {
    $build = [];
    if ($image = $this->getImage()) {
      $this->itemAttributes->addClass('has-mark');
      $this->addClass('has-mark');
      if ($this->imageIsExternal()) {
        $build = [
          '#theme' => 'image',
          '#uri' => $image,
        ];
      }
      else {
        $image_style = $this->getImageStyle();

        if (!empty($image_style)) {
          $image_style_entity = \Drupal::service('entity_type.manager')->getStorage('image_style')->load($image_style);
          $cache_tags = $image_style_entity->getCacheTags();
        }
        $build = [
          '#theme' => 'image_style',
          '#style_name' => $image_style,
          '#width' => $image->getWidth(),
          '#height' => $image->getHeight(),
          '#uri' => $image->getSource(),
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable() {
    $build = [
      '#weight' => $this->getWeight(),
    ];
    $build['element'] = [
      '#theme' => 'exo_toolbar_element',
      '#tag' => $this->getTag(),
      '#title' => $this->getTitle(),
      '#icon' => $this->getIconRenderable(),
      '#icon_position' => $this->getIconPosition(),
      '#image' => $this->getImageRenderable(),
      '#image_position' => $this->getImagePosition(),
      '#badge' => $this->useBadge() ? $this->getBadge() : NULL,
      '#attributes' => $this->getAttributes(),
      '#access' => $this->getAccess(),
      '#attached' => [
        'library' => $this->getLibraries(),
      ],
    ];
    $subelements = $this->getSubElements();
    if ($subelements) {
      foreach ($subelements as $element) {
        /** @var \Drupal\exo_toolbar\ExoToolbarElement $element */
        $build['subelements'][] = $element->toRenderable();
      }
      // Sort items at this stage so that theme preprocessing can break them
      // up as needed.
      uasort($build['subelements'], [SortArray::class, 'sortByWeightProperty']);
      $build['subelements'] = array_values((array) $build['subelements']);
      $build['subelements'] += [
        '#theme' => $this->getSubElementTheme(),
      ];
    }
    return $build;
  }

}
