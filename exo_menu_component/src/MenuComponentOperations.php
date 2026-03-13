<?php

namespace Drupal\exo_menu_component;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for reacting to entity events related to Inline Blocks.
 *
 * @internal
 *   This is an internal utility class wrapping hook implementations.
 */
class MenuComponentOperations implements ContainerInjectionInterface {
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The menu component manager.
   *
   * @var \Drupal\exo_menu_component\MenuComponentManagerInterface
   */
  protected $menuComponentManager;

  /**
   * The inner form state key.
   */
  const INNER_FORM_STATE_KEY = 'inner_form_state';

  /**
   * The main submit button.
   */
  const MAIN_SUBMIT_BUTTON = 'submit';

  /**
   * Constructs a new EntityOperations object.
   *
   * @param \Drupal\exo_menu_component\MenuComponentManagerInterface $exo_menu_component_manager
   *   The menu component manager service.
   */
  public function __construct(MenuComponentManagerInterface $exo_menu_component_manager) {
    $this->menuComponentManager = $exo_menu_component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('exo_menu_component.manager')
    );
  }

  /**
   * Replace links that are menu components.
   *
   * @param array $items
   *   An array of items.
   */
  public function replaceComponents(array &$items) {
    $found = [];
    foreach ($items as &$item) {
      $below_found = [];
      if (!empty($item['below'])) {
        $below_found += $this->replaceComponents($item['below']);
      }
      $url = $item['url'];
      if (!$url instanceof Url) {
        return [];
      }
      $menu_attributes = $url->getOption('attributes');
      if (isset($menu_attributes['data-exo-menu-component']) && !empty($menu_attributes['data-exo-menu-component'])) {
        $component = $this->menuComponentManager->getMenuComponent($menu_attributes['data-exo-menu-component']);
        $item['markup'] = $this->menuComponentManager->viewMenuComponent($menu_attributes['data-exo-menu-component'], 'default', $component);
        $item['exo_menu_component'] = TRUE;
        $item['attributes']->addClass('exo-menu-component-wrapper');
        $found[$component->bundle()] = $component->bundle();
      }
      if ($below_found) {
        $item['exo_menu_component_below'] = TRUE;
        $item['attributes']->addClass('exo-menu-has-component');
        foreach ($below_found as $type) {
          $item['attributes']->addClass('exo-menu-has-component--' . Html::getClass($type));
        }
      }
    }
    return $found;
  }

  /**
   * Handle form build.
   */
  public function handleForm(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $menu_link = $form_object->getEntity();
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContentInterface $menu_link */
    $menu_name = $menu_link->getMenuName();
    $menu_link_options = $menu_link->link->first()->options ?: [];

    $allowed_types = $this->menuComponentManager->getMegaMenuTypeWhichTargetMenu($menu_name);
    if (!$allowed_types) {
      return;
    }
    $menu_component_id = $menu_link_options['attributes']['data-exo-menu-component'] ?? NULL;
    if (!$menu_component_id) {
      return;
    }
    if (is_array($menu_component_id)) {
      $menu_component_id = reset($menu_component_id);
      if (!$menu_component_id) {
        return;
      }
    }
    $menu_component = $this->menuComponentManager->getMenuComponent($menu_component_id);
    if (!$menu_component || !isset($allowed_types[$menu_component->bundle()])) {
      return;
    }
    $form['exo_menu_component_id'] = [
      '#type' => 'hidden',
      '#value' => $menu_component_id,
    ];
    $form['exo_menu_component'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Component'),
      '#tree' => TRUE,
      '#weight' => -10,
    ];

    foreach ([
      'title',
      'link',
      'description',
      'expanded',
    ] as $field_name) {
      $form[$field_name]['#access'] = FALSE;
      if (!empty($form[$field_name]['widget'][0]['value'])) {
        $form[$field_name]['widget'][0]['value']['#required'] = FALSE;
      }
    }

    $menu_component_form = $this->menuComponentManager->getFormObject($menu_component);
    $form['exo_menu_component'] = $this->buildComponentForm($form['exo_menu_component'], $form_state, $menu_component_form);
    $form['exo_menu_component']['#process'] = \Drupal::service('element_info')->getInfoProperty('form', '#process', []);
    $form['exo_menu_component']['#process'][] = [
      get_class($this),
      'processComponentForm',
    ];
    array_unshift($form['#validate'], [
      get_class($this),
      'validateComponentForm',
    ]);
    $form['actions']['submit']['#submit'][] = [
      get_class($this),
      'submitComponentForm',
    ];

    if (!empty($form['exo_menu_component']['component']['form']['field_title']['widget'][0]['#required'])) {
      $form['exo_menu_component']['component']['form']['title']['#access'] = FALSE;
      $form_state->set('component_use_title', TRUE);
    }
    if (!empty($form['exo_menu_component']['component']['form']['field_link']['widget'][0]['#required'])) {
      $form['exo_menu_component']['component']['form']['title']['#access'] = FALSE;
      $form_state->set('component_use_link_title', TRUE);
    }
  }

  /**
   * Build inner form.
   *
   * The build form needs to take care of the following:
   *   - Creating a custom form state object for each inner form (and keep it
   *     inside the main form state.
   *   - Generating a render array for each inner form.
   *   - Handle compatibility issues such as #process array and action elements.
   */
  public function buildComponentForm(array $form, FormStateInterface $form_state, FormInterface $menu_component_form) {
    $key = 'component';
    $inner_form_state = static::createInnerFormState($form_state, $menu_component_form, $key);
    $inner_form_state = static::getInnerFormState($form_state, $key);

    // By placing the actual inner form inside a container element (such as
    // details) we gain the freedom to alter the wrapper of the inner form
    // with little damage to the render element attributes of the inner form.
    $inner_form = ['#parents' => ['exo_menu_component', $key]];
    $inner_form = $menu_component_form->buildForm($inner_form, $inner_form_state);
    $form[$key] = [
      '#type' => 'container',
      '#title' => $this->t('Inner form: %key', ['%key' => $key]),
      'form' => $inner_form,
    ];

    $form[$key]['form']['#type'] = 'container';
    $form[$key]['form']['#theme_wrappers'] = \Drupal::service('element_info')->getInfoProperty('container', '#theme_wrappers', []);
    unset($form[$key]['form']['form_token']);

    // The process array is called from the FormBuilder::doBuildForm method
    // with the form_state object assigned to the this (ComboForm) object.
    // This results in a compatibility issues because these methods should
    // be called on the inner forms (with their assigned FormStates).
    // To resolve this we move the process array in the inner_form_state
    // object.
    if (!empty($form[$key]['form']['#process'])) {
      $inner_form_state->set('#process', $form[$key]['form']['#process']);
      unset($form[$key]['form']['#process']);
    }
    else {
      $inner_form_state->set('#process', []);
    }

    // The actions array causes a UX problem because there should only be a
    // single save button and not multiple.
    // The current solution is to move the #submit callbacks of the submit
    // element to the inner form element root.
    if (!empty($form[$key]['form']['actions'])) {
      if (isset($form[$key]['form']['actions'][static::MAIN_SUBMIT_BUTTON])) {
        $form[$key]['form']['#submit'] = $form[$key]['form']['actions'][static::MAIN_SUBMIT_BUTTON]['#submit'];
      }

      unset($form[$key]['form']['actions']);
    }

    return $form;
  }

  /**
   * Process component form.
   */
  public static function processComponentForm(array &$element, FormStateInterface &$form_state, array &$complete_form) {
    $key = 'component';
    $inner_form_state = static::getInnerFormState($form_state, $key);
    foreach ($inner_form_state->get('#process') as $callback) {
      // The callback format was copied from FormBuilder::doBuildForm().
      $element[$key]['form'] = call_user_func_array($inner_form_state->prepareCallback($callback), [
        &$element[$key]['form'],
        &$inner_form_state,
        &$complete_form,
      ]);
    }

    return $element;
  }

  /**
   * Validate component form.
   */
  public static function validateComponentForm(array $form, FormStateInterface $form_state) {
    $key = 'component';
    /** @var \Drupal\Core\Form\FormValidatorInterface $form_validator */
    $form_validator = \Drupal::service('form_validator');
    $inner_form_state = static::getInnerFormState($form_state, $key);
    /** @var \Drupal\Core\Entity\EntityFormInterface $inner_form */
    $inner_form = $inner_form_state->getFormObject();

    // Pass through both the form elements validation and the form object
    // validation.
    $inner_form->validateForm($form['exo_menu_component'][$key]['form'], $inner_form_state);
    $form_validator->validateForm($inner_form->getFormId(), $form['exo_menu_component'][$key]['form'], $inner_form_state);

    foreach ($inner_form_state->getErrors() as $error_element_path => $error) {
      $form_state->setErrorByName($error_element_path, $error);
    }

    $title = NULL;
    if ($form_state->get('component_use_title')) {
      $title = NestedArray::getValue($form_state->getValues(), [
        'exo_menu_component',
        'component',
        'field_title',
        0,
        'value',
      ]);
    }
    if (!$title && $form_state->get('component_use_link_title')) {
      $title = NestedArray::getValue($form_state->getValues(), [
        'exo_menu_component',
        'component',
        'field_link',
        0,
        'title',
      ]);
    }

    if ($title) {
      $form_state->setValue([
        'exo_menu_component',
        'component',
        'title',
      ], $title);
    }

    $form_state->setValue(['title', 0, 'value'], $form_state->getValue([
      'exo_menu_component',
      'component',
      'title',
    ]));
  }

  /**
   * Validate component form.
   */
  public static function submitComponentForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $menu_link = $form_object->getEntity();
    $menu_link->link->first()->uri = 'route:<nolink>';
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContentInterface $menu_link */
    $menu_link_options = $menu_link->link->first()->options ?: [];
    if ($exo_menu_component_id = $form_state->getValue('exo_menu_component_id')) {
      $menu_link->link->first()->options = array_merge_recursive($menu_link_options, [
        'attributes' => [
          'data-exo-menu-component' => $exo_menu_component_id,
        ],
      ]);
    }

    $key = 'component';
    /** @var \Drupal\Core\Form\FormSubmitterInterface $form_submitter */
    $form_submitter = \Drupal::service('form_submitter');
    $inner_form_state = static::getInnerFormState($form_state, $key);

    // The form state needs to be set as submitted before executing the
    // doSubmitForm method.
    $inner_form_state->setSubmitted();
    /** @var \Drupal\Core\Entity\EntityFormInterface $inner_form */
    $inner_form = $inner_form_state->getFormObject();
    $form_submitter->doSubmitForm($form['exo_menu_component'][$key]['form'], $inner_form_state);
    /** @var \Drupal\exo_menu_component\Entity\MenuComponentInterface $menu_component */
    $menu_component = $inner_form->getEntity();

    if ($menu_component->hasField('field_link') && !$menu_component->get('field_link')->isEmpty()) {
      $menu_link->link->first()->uri = $menu_component->get('field_link')->uri;
    }
    $menu_link->save();
    $form_state->setRedirectUrl($menu_link->toUrl('edit-form'));
  }

  /**
   * Get inner form state.
   *
   * Before returning the innerFormState object, we need to set the
   * complete_form, values and user_input properties from the main form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The main form state.
   * @param string $key
   *   The key used to store the inner form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The inner form state.
   */
  protected static function getInnerFormState(FormStateInterface $form_state, $key) {
    /** @var \Drupal\Core\Form\FormStateInterface $inner_form_state */
    $inner_form_state = $form_state->get([static::INNER_FORM_STATE_KEY, $key]);
    if ($complete_form = $form_state->getCompleteForm()) {
      $inner_form_state->setCompleteForm($complete_form);
    }
    $inner_form_state->setValues($form_state->getValues() ? $form_state->getValues() : []);
    $inner_form_state->setUserInput($form_state->getUserInput() ? $form_state->getUserInput() : []);

    $field_storage = $form_state->getStorage()['field_storage']['#parents'] ?? [];
    $inner_field_storage = $inner_form_state->getStorage()['field_storage']['#parents'] ?? [];
    $form_state->set('field_storage', ['#parents' => $field_storage + $inner_field_storage]);
    $inner_form_state->set('field_storage', ['#parents' => $field_storage + $inner_field_storage]);

    return $inner_form_state;
  }

  /**
   * Create inner form state.
   *
   * After the initialization of the inner form state, we need to assign it with
   * the inner form object and set it inside the main form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The main form state.
   * @param \Drupal\Core\Form\FormInterface $form_object
   *   The inner form object.
   * @param string $key
   *   The key used to store the inner form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The inner form state.
   */
  protected static function createInnerFormState(FormStateInterface $form_state, FormInterface $form_object, $key) {
    $inner_form_state = new FormState();
    $inner_form_state->setFormObject($form_object);
    $form_state->set([static::INNER_FORM_STATE_KEY, $key], $inner_form_state);
    return $inner_form_state;
  }

}
