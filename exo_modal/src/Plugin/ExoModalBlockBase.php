<?php

namespace Drupal\exo_modal\Plugin;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\exo\ExoSettingsInterface;
use Drupal\exo_modal\ExoModalGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheableResponseTrait;
use Drupal\exo_modal\ExoModalInterface;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\exo_modal\Element\ExoModalUrlTrait;

/**
 * Provides a base for eXo modal blocks.
 */
abstract class ExoModalBlockBase extends BlockBase implements ExoModalBlockPluginInterface, ContainerFactoryPluginInterface {
  use CacheableResponseTrait;
  use ExoIconTranslationTrait;
  use ExoModalUrlTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The eXo Modal options service.
   *
   * @var \Drupal\exo\ExoSettingsPluginInstanceInterface
   */
  protected $exoModalSettings;

  /**
   * The eXo modal generator.
   *
   * @var \Drupal\exo_modal\ExoModalGeneratorInterface
   */
  protected $exoModalGenerator;

  /**
   * The modal.
   *
   * @var \Drupal\exo_modal\ExoModalInterface
   */
  protected $modal;

  /**
   * Creates a LocalActionsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\exo\ExoSettingsInterface $exo_modal_settings
   *   The eXo options service.
   * @param \Drupal\exo_modal\ExoModalGeneratorInterface $exo_modal_generator
   *   The eXo modal generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ExoSettingsInterface $exo_modal_settings, ExoModalGeneratorInterface $exo_modal_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->exoModalSettings = $exo_modal_settings->createInstance($this->configuration['modal']);
    $this->exoModalGenerator = $exo_modal_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('exo_modal.settings'),
      $container->get('exo_modal.generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'ajax' => FALSE,
      'block_id' => '',
      'modal' => [
        'exo_default' => 1,
      ],
      'blocks' => [
        'header' => [],
        'footer' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['ajax'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load in dynamically'),
      '#default_value' => $this->configuration['ajax'],
    ];
    $form['modal'] = [];
    $form['modal'] = $this->exoModalSettings->buildForm($form['modal'], $form_state) + [
      '#type' => 'details',
      '#title' => $this->t('Modal'),
    ];

    $form['blocks'] = [
      '#type' => 'details',
      '#title' => $this->t('Blocks (Header/Footer)'),
    ];
    $form['blocks']['header'] = [
      '#type' => 'details',
      '#title' => $this->t('Header'),
    ];
    $form['blocks']['header']['blocks'] = $this->blocksForm('header', $form_state, $this->configuration['blocks']['header']);
    $form['blocks']['footer'] = [
      '#type' => 'details',
      '#title' => $this->t('Footer'),
    ];
    $form['blocks']['footer']['blocks'] = $this->blocksForm('footer', $form_state, $this->configuration['blocks']['footer']);

    return $form;
  }

  /**
   * Build block selection form.
   */
  protected function blocksForm($group, FormStateInterface $form_state, $settings = [], $support_panel = TRUE) {
    $form = [
      '#type' => 'table',
      '#header' => [
        $this->t('Block'),
        $this->t('Settings'),
        $this->t('Weight'),
      ],
      '#element_validate' => [[get_class($this), 'validateBlocks']],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'menu-weight',
        ],
      ],
    ];
    if (!$support_panel) {
      unset($form['#header'][1]);
      $form['#header'] = array_values($form['#header']);
    }

    $theme = $form_state->get('block_theme') ?: \Drupal::routeMatch()->getParameter('theme');
    if (!$theme) {
      // Drupal no longer sets the block_theme in the form state.
      if ($block = \Drupal::routeMatch()->getParameter('block')) {
        $theme = $block->getTheme();
      }
    }
    $count = 0;
    foreach ($this->getBlockOptions($theme) as $id => $label) {
      $status = isset($settings[$id]);
      $panel_settings = $settings[$id]['panel'] ?? [];
      $weight = $status ? array_search($id, array_keys($settings)) : $count + 100;
      $html_id = 'exo-modal-block-blocks-' . $id . '-' . $group;
      $form[$id]['#attributes']['class'][] = 'draggable';
      $form[$id]['#weight'] = $weight;
      $form[$id]['status'] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => $status,
      ];
      $states = [
        'visible' => [
          ':input#' . $html_id => ['checked' => TRUE],
        ],
      ];
      if ($support_panel) {
        $form[$id]['panel']['status'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable'),
          '#default_value' => !empty($panel_settings),
          '#attributes' => ['id' => [$html_id]],
        ];
        $form[$id]['panel']['settings'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Settings'),
          '#states' => $states,
        ];
        $form[$id]['panel']['settings'] += $this->exoModalSettings->getExoSettings()->buildPanelForm($panel_settings);
      }

      $form[$id]['weight'] = [
        '#type' => 'number',
        '#title' => t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => 0,
        '#attributes' => ['class' => ['menu-weight']],
      ];
      $count++;
    }
    uasort($form, ['Drupal\Component\Utility\SortArray', 'sortByWeightProperty']);
    return $form;
  }

  /**
   * Given the menu form values, clean them into a simple array.
   */
  public static function validateBlocks($element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    $values = array_filter($values, function ($value) {
      return $value['status'] == 1;
    });
    array_walk($values, function (&$value) {
      unset($value['status'], $value['weight']);
      if (isset($value['panel'])) {
        if ($value['panel']['status']) {
          $value['panel'] = array_filter($value['panel']['settings']);
        }
        else {
          unset($value['panel']);
        }
      }
      $value = array_filter($value);
    });
    $form_state->setValueForElement($element, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['modal'], $form, $form_state);
    $this->exoModalSettings->validateForm($form['modal'], $subform_state);
    parent::blockValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Form\SubformStateInterface $form_state */
    // There is a bug in BlockForm that passes the whole form vs just the
    // settings subform like it does in validate.
    $subform_state = SubformState::createForSubform($form['settings']['modal'], $form['settings'], $form_state);
    $this->exoModalSettings->submitForm($form['settings']['modal'], $subform_state);

    $this->configuration['block_id'] = $form_state->getCompleteFormState()->getValue('id');
    $this->configuration['modal'] = $form_state->getValue('modal');
    $this->configuration['ajax'] = $form_state->getValue('ajax');
    $this->configuration['blocks'] = [];
    foreach ($form_state->getValue('blocks') as $id => $data) {
      if (!empty($data['blocks'])) {
        $this->configuration['blocks'][$id] = $data['blocks'];
      }
    }
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $use_ajax = $this->configuration['ajax'];
    $build = [];
    if ($use_ajax) {
      $modal = $this->generateModal(FALSE);
      $url = static::toModalUrl(Url::fromRoute('exo_modal.api.block.view', [
        'block' => $this->configuration['block_id'],
      ], [
        'query' => \Drupal::destination()->getAsArray(),
      ]));
      $modal->setSetting(['modal', 'ajax'], $url->getInternalPath());
      $build['modal'] = $modal->toRenderableTrigger();
    }
    else {
      $modal = $this->generateModal();
      $build['modal'] = $modal->toRenderable();
    }
    return $build;
  }

  /**
   * Generate the modal.
   *
   * @return \Drupal\exo_modal\ExoModalInterface
   *   The modal.
   */
  protected function generateModal($with_content = TRUE) {
    $this->modal = $this->exoModalGenerator->generate(
      'exo_modal_block_' . $this->configuration['block_id'],
      $this->configuration['modal']
    );
    if ($with_content) {
      $this->buildModalBlockContent('header', $this->modal);
      $this->buildModalBlockContent('footer', $this->modal);
      $this->modal->addCacheableDependency($this->getCacheableMetadata());
      $this->modal->setContent([$this->buildModalContent()]);
    }
    return $this->modal;
  }

  /**
   * {@inheritdoc}
   *
   * Creates the modal content. Individual modal block plugins can add elements
   * to this form by overriding ExoModalBlockBase::buildModalContent(). Most
   * block plugins should not override this method unless they need to alter
   * the generic modal properties.
   *
   * @see \Drupal\exo_modal\Plugin\ExoModalBlockBase::buildModalContent()
   */
  public function buildModal() {
    $modal = $this->generateModal();
    $modal->setSetting(['modal', 'autoOpen'], TRUE);
    return $modal->toRenderableModal();
  }

  /**
   * Builds and returns the renderable array for display within the modal.
   *
   * @return array
   *   A renderable array representing the content of the modal.
   */
  protected function buildModalContent() {
    return ['#markup' => 'eXo Modal Default'];
  }

  /**
   * Build modal block content.
   *
   * @param string $group
   *   Either header of footer.
   * @param Drupal\exo_modal\ExoModalInterface $modal
   *   The exo modal.
   */
  protected function buildModalBlockContent($group, ExoModalInterface $modal) {
    if (!empty($this->configuration['blocks'][$group])) {
      $count = 0;
      foreach ($this->configuration['blocks'][$group] as $block_id => $settings) {
        if ($block_render = $this->buildBlock($block_id)) {
          $block_render['#weight'] = $count;
          if (!empty($settings['panel'])) {
            $modal->addPanel($group, $block_id, $block_render, $settings['panel']);
          }
          else {
            $modal->addSectionContent($group, $block_id, $block_render);
          }
          $count++;
        }
      }
    }
  }

  /**
   * Build block content.
   *
   * @param string $block_id
   *   The block id.
   *
   * @return mixed
   *   A render array.
   */
  protected function buildBlock($block_id) {
    $build = [];
    $block = $this->entityTypeManager->getStorage('block')->load($block_id);
    if ($block && $block->access('view')) {
      $this->addCacheableDependency($block);
      $build = $this->entityTypeManager->getViewBuilder('block')->view($block);
      // Vary the embedded block's render cache by the outer modal so the same
      // block embedded in multiple modals on one page doesn't reuse cached
      // output. Without this, render-cache hits replay the first embed's
      // markup (and any unique IDs in it) for every subsequent embed.
      if (!empty($build['#cache']['keys']) && !empty($this->configuration['block_id'])) {
        $build['#cache']['keys'][] = 'exo_modal:' . $this->configuration['block_id'];
      }
    }
    return $build;
  }

  /**
   * Get available blocks as options.
   */
  protected function getBlockOptions($theme) {
    $theme_blocks = $this->entityTypeManager->getStorage('block')->loadByProperties(['theme' => $theme]);
    $options = [];
    if (!empty($theme_blocks)) {
      foreach ($theme_blocks as $block) {
        $options[$block->id()] = $block->label();
      }
    }
    unset($options[$this->configuration['block_id']]);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->getCacheableMetadata()->addCacheTags(parent::getCacheTags())->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->getCacheableMetadata()->addCacheContexts(parent::getCacheContexts())->getCacheContexts();
  }

}
