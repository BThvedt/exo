<?php

namespace Drupal\exo_toolbar\Plugin;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\exo_toolbar\ExoToolbarElement;
use Drupal\Core\Access\AccessResult;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\exo_toolbar\Entity\ExoToolbarItemInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for eXo Toolbar Item plugins.
 */
abstract class ExoToolbarItemBase extends PluginBase implements ExoToolbarItemPluginInterface, PluginWithFormsInterface, RefinableCacheableDependencyInterface, ContainerFactoryPluginInterface, ContextAwarePluginInterface {

  use RefinableCacheableDependencyTrait;
  use ContextAwarePluginAssignmentTrait;
  use PluginWithFormsTrait;
  use PluginDependencyTrait;
  use ExoIconTranslationTrait;
  use ContextAwarePluginTrait;

  /**
   * The eXo toolbar badge type manager.
   *
   * @var \Drupal\exo_toolbar\Plugin\ExoToolbarBadgeTypeManagerInterface
   */
  protected $badgeTypeManager;

  /**
   * The plugin collection that holds the item plugin for this entity.
   *
   * @var \Drupal\exo_toolbar\Plugin\ExoToolbarBadgeTypeCollection
   */
  protected $badgeTypeCollection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * The eXo toolbar item this plugin instance belongs to.
   *
   * @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface
   */
  protected $exoToolbarItem;

  /**
   * Creates a ExoToolbarItemBase instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\exo_toolbar\Plugin\ExoToolbarBadgeTypeManagerInterface $exo_toolbar_badge_type_manager
   *   The eXo toolbar badge type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExoToolbarBadgeTypeManagerInterface $exo_toolbar_badge_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->badgeTypeManager = $exo_toolbar_badge_type_manager;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.exo_toolbar_badge_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    if (!empty($this->configuration['title'])) {
      return $this->configuration['title'];
    }

    $definition = $this->getPluginDefinition();
    // Cast the admin label to a string since it is an object.
    // @see \Drupal\Core\StringTranslation\TranslatableMarkup
    return (string) $definition['admin_label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return $this->configuration['icon'];
  }

  /**
   * {@inheritdoc}
   */
  public function allowSort() {
    $definition = $this->getPluginDefinition();
    return !isset($definition['no_sort']) || empty($definition['no_sort']);
  }

  /**
   * {@inheritdoc}
   */
  public function allowAdmin() {
    $definition = $this->getPluginDefinition();
    return !isset($definition['no_admin']) || empty($definition['no_admin']);
  }

  /**
   * {@inheritdoc}
   */
  public function isDependent() {
    $definition = $this->getPluginDefinition();
    return !empty($definition['is_dependent']);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->baseConfigurationDefaults(),
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * Returns generic default configuration for item plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function baseConfigurationDefaults() {
    return [
      'id' => $this->getPluginId(),
      'title' => '',
      'provider' => $this->pluginDefinition['provider'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'mark_only_horizontal' => FALSE,
      'icon' => '',
      'badge_type' => '',
      'badge_settings' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurationValue($key, $value) {
    $this->configuration[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    if ($this->configuration['badge_type']) {
      $this->calculatePluginDependencies($this->getBadgeType());
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $access = $this->itemAccess($account);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function setItem(ExoToolbarItemInterface $item) {
    $this->exoToolbarItem = $item;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItem() {
    return $this->exoToolbarItem;
  }

  /**
   * Indicates whether the item should be shown.
   *
   * Blocks with specific access checking should override this method rather
   * than access(), in order to avoid repeating the handling of the
   * $return_as_object argument.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   *
   * @see self::access()
   */
  protected function itemAccess(AccountInterface $account) {
    // By default, the item is visible.
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   *
   * Creates a generic configuration form for all item types. Individual
   * item plugins can add elements to this form by overriding
   * ExoToolbarItemBase::itemForm(). Most item plugins should not override this
   * method unless they need to alter the generic form elements.
   *
   * @see \Drupal\Core\Block\ExoToolbarItemBase::itemForm()
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $definition = $this->getPluginDefinition();
    $form['provider'] = [
      '#type' => 'value',
      '#value' => $definition['provider'],
    ];

    $form['admin_label'] = [
      '#type' => 'item',
      '#title' => $this->t('Item description'),
      '#plain_text' => $definition['admin_label'],
      '#weight' => -10,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#default_value' => $this->label(),
      '#required' => TRUE,
      '#weight' => -5,
    ];

    // Add context mapping UI form elements.
    $contexts = $form_state->getTemporaryValue('gathered_contexts') ?: [];
    $form['context_mapping'] = $this->addContextAssignmentElement($this, $contexts);
    // Add plugin-specific settings for this item type.
    $form += $this->itemForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function itemForm(array $form, FormStateInterface $form_state) {
    $form['mark_only_horizontal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mark only'),
      '#description' => $this->t('Hide item titles and only show the item icon/image.'),
      '#default_value' => $this->configuration['mark_only_horizontal'],
    ];
    $form['icon'] = [
      '#type' => 'exo_icon',
      '#title' => $this->t('Icon'),
      '#maxlength' => 255,
      '#default_value' => $this->getIcon(),
    ];

    $element_id = 'exo-toolbar-item-badge-settings';
    $badge_type_instance = $this->getBadgeTypeInstance($form, $form_state);

    $form['badge_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Badge Type'),
      '#options' => $this->badgeTypeManager->getBadgeTypeLabels(),
      '#default_value' => $this->configuration['badge_type'],
      '#limit_validation_errors' => [['settings', 'badge_type']],
      '#empty_option' => $this->t('- None -'),
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxBadgeType'],
        'event' => 'change',
        'wrapper' => $element_id,
        'progress' => [
          'type' => 'throbber',
          'message' => t('Getting badge settings'),
        ],
      ],
    ];

    $form['badge_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Badge settings'),
      '#id' => $element_id,
    ];
    if ($badge_type_instance) {
      $subform_state = SubformState::createForSubform($form['badge_settings'], $form, $form_state);
      $form['badge_settings'] += $badge_type_instance->buildConfigurationForm($form['badge_settings'], $subform_state);
    }
    if (empty(Element::getVisibleChildren($form['badge_settings']))) {
      $form['badge_settings']['#attributes']['style'] = 'display:none';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Most item plugins should not override this method. To add validation
   * for a specific item type, override ExoToolbarItemBase::itemValidate().
   *
   * @see \Drupal\exo_toolbar\Plugin\ExoToolbarItemBase::itemValidate()
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Remove the admin_label form item element value so it will not persist.
    $form_state->unsetValue('admin_label');
    $this->itemValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function itemValidate(array $form, FormStateInterface $form_state) {}

  /**
   * AJAX function to get display IDs for a particular View.
   */
  public static function ajaxBadgeType(array &$form, FormStateInterface $form_state) {
    return $form['settings']['badge_settings'];
  }

  /**
   * {@inheritdoc}
   *
   * Most item plugins should not override this method. To add submission
   * handling for a specific item type, override
   * ExoToolbarItemBase::itemSubmit().
   *
   * @see \Drupal\Core\Block\ExoToolbarItemBase::itemSubmit()
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Process the item's submission handling if no errors occurred only.
    if (!$form_state->getErrors()) {
      $this->configuration['provider'] = $form_state->getValue('provider');
      $this->configuration['title'] = $form_state->getValue('title');
      $this->itemSubmit($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function itemSubmit(array $form, FormStateInterface $form_state) {
    $this->configuration['mark_only_horizontal'] = $form_state->getValue('mark_only_horizontal');
    $this->configuration['icon'] = $form_state->getValue('icon');
    $this->configuration['badge_type'] = $form_state->getValue('badge_type');
    $this->configuration['badge_settings'] = !empty($form_state->getValue('badge_settings')) ? $form_state->getValue('badge_settings') : [];
  }

  /**
   * {@inheritdoc}
   */
  public function build($preview = FALSE) {
    $build = [
      '#exoToolbarItemJsSettings' => [],
      '#attributes' => [],
    ];
    if ($preview) {
      $elements = $this->elementPreviewBuild();
      if (!is_array($elements)) {
        $elements = [$elements];
      }
    }
    else {
      $elements = $this->elementBuildMultiple();
    }
    foreach ($elements as $delta => $element) {
      if ($element) {
        /** @var \Drupal\exo_toolbar\ExoToolbarElementInterface $element */
        if ($this->isDependent()) {
          $element->addClass('exo-toolbar-element-dependent');
        }
        if ($this->configuration['badge_type']) {
          $this->getBadgeType()->elementPrepare($element, $delta, $this);
        }
        if ($element->shouldUseAsideLabel()) {
          $build['aside']['labels'][$delta] = $element->getAsideLabel();
        }
        $build[$delta] = $element->toRenderable();
        $build['#exoToolbarItemJsSettings'] = NestedArray::mergeDeep($build['#exoToolbarItemJsSettings'], $element->getJsSettings());
        $build['#attributes'] = NestedArray::mergeDeep($build['#attributes'], $element->getItemAttributes()->toArray());
      }
    }
    $build['#cache'] = [
      'tags' => $this->getCacheTags(),
      'contexts' => $this->getCacheContexts(),
      'max-age' => $this->getCacheMaxAge(),
    ];
    return $build;
  }

  /**
   * Create an item with multiple elements.
   *
   * By default it will retriev a single item from elementBuild().
   *
   * @return \Drupal\exo_toolbar\ExoToolbarElementInterface[]
   *   An array of eXo element objects.
   */
  protected function elementBuildMultiple() {
    return [
      $this->elementBuild(),
    ];
  }

  /**
   * Create a single element of an item.
   *
   * @return \Drupal\exo_toolbar\ExoToolbarElementInterface
   *   An eXo element object.
   */
  protected function elementBuild() {
    return ExoToolbarElement::create([
      'title' => $this->configuration['title'],
      'icon' => $this->configuration['icon'],
    ])
      ->setHorizontalMarkOnly((bool) $this->configuration['mark_only_horizontal']);
  }

  /**
   * Create a single element of an item for previewing.
   *
   * @return \Drupal\exo_toolbar\ExoToolbarElementInterface
   *   An eXo element object.
   */
  protected function elementPreviewBuild() {
    return $this->elementBuildMultiple();
  }

  /**
   * Get the badge type plugin object.
   *
   * @return \Drupal\exo_toolbar\plugin\ExoToolbarBadgeTypePluginInterface
   *   The badge type plugin.
   */
  protected function getBadgeType() {
    return $this->getBadgeTypeCollection()->get($this->configuration['badge_type']);
  }

  /**
   * Encapsulates the creation of the item's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The item's plugin collection.
   */
  protected function getBadgeTypeCollection() {
    if (!$this->badgeTypeCollection) {
      $this->badgeTypeCollection = new ExoToolbarBadgeTypeCollection($this->badgeTypeManager, $this->configuration['badge_type'], $this->configuration['badge_settings'], $this->getPluginId());
    }
    return $this->badgeTypeCollection;
  }

  /**
   * Get badge type instance.
   */
  protected function getBadgeTypeInstance(array $form, SubformStateInterface $form_state) {
    $badge_type = $form_state->getCompleteFormState()->getValue(['settings', 'badge_type'], $this->configuration['badge_type']);
    $badge_settings = $form_state->getCompleteFormState()->getValue(['settings', 'badge_settings'], $this->configuration['badge_settings']);
    return $badge_type ? $this->badgeTypeManager->createInstance($badge_type, $badge_settings) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(ExoToolbarItemInterface $item) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineNameSuggestion() {
    $definition = $this->getPluginDefinition();
    $admin_label = $definition['admin_label'];

    // @todo This is basically the same as what is done in
    //   \Drupal\system\MachineNameController::transliterate(), so it might make
    //   sense to provide a common service for the two.
    $transliterated = $this->transliteration()->transliterate($admin_label, LanguageInterface::LANGCODE_DEFAULT, '_');
    $transliterated = mb_strtolower($transliterated);

    $transliterated = preg_replace('@[^a-z0-9_.]+@', '', $transliterated);

    return $transliterated;
  }

  /**
   * Wraps the transliteration service.
   *
   * @return \Drupal\Component\Transliteration\TransliterationInterface
   *   The transliteration service.
   */
  protected function transliteration() {
    if (!$this->transliteration) {
      $this->transliteration = \Drupal::transliteration();
    }
    return $this->transliteration;
  }

  /**
   * {@inheritdoc}
   */
  public function setTransliteration(TransliterationInterface $transliteration) {
    $this->transliteration = $transliteration;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRegionJsSettings(array &$element, ExoToolbarRegionPluginInterface $region) {}

  /**
   * {@inheritdoc}
   */
  public function alterRegionElement(array &$element, ExoToolbarRegionPluginInterface $region) {}

  /**
   * {@inheritdoc}
   */
  public function alterSectionElement(array &$element, array $context) {}

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = $this->cacheTags;
    if ($this->configuration['badge_type']) {
      $tags = Cache::mergeTags($tags, $this->getBadgeType()->getCacheTags());
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $cache_contexts = $this->cacheContexts;
    if ($this->configuration['badge_type']) {
      $cache_contexts = Cache::mergeContexts($cache_contexts, $this->getBadgeType()->getCacheContexts());
    }
    return $cache_contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $max_age = $this->cacheMaxAge;
    if ($this->configuration['badge_type']) {
      $max_age = Cache::mergeMaxAges($max_age, $this->getBadgeType()->getCacheMaxAge());
    }
    return $max_age;
  }

}
