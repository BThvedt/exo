<?php

namespace Drupal\exo_site_settings\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Element;

/**
 * Aggregated settings form.
 */
class SiteSettingsGeneralForm extends FormBase {

  /**
   * Helpers.
   *
   * This constant is used as a key inside the main form state object to gather
   * all the inner form state objects.
   *
   * @const
   * @see getInnerFormState()
   */
  const INNER_FORM_STATE_KEY = 'inner_form_state';

  /**
   * The main submit button.
   */
  const MAIN_SUBMIT_BUTTON = 'submit';

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Inner forms.
   *
   * @var \Drupal\Core\Entity\EntityFormInterface[]
   */
  protected $innerForms = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $exo_site_settings_storage = $this->entityTypeManager->getStorage('exo_site_settings');
    /** @var \Drupal\exo_site_settings\SiteSettingsStorage $exo_site_settings_storage */
    foreach ($this->entityTypeManager->getStorage('exo_site_settings_type')->loadMultiple() as $exo_site_settings_type) {
      /** @var \Drupal\exo_site_settings\Entity\SiteSettingsTypeInterface $exo_site_settings_type */
      if (($exo_site_settings_type->isAggregate() || $exo_site_settings_type->id() == 'general') && $exo_site_settings_type->access('page_update')) {
        $exo_site_settings = $exo_site_settings_storage->loadOrCreateByType($exo_site_settings_type->id());
        if ($exo_site_settings) {
          $this->innerForms[$exo_site_settings_type->id()] = $this->entityTypeManager->getFormObject('exo_site_settings', 'default')->setEntity($exo_site_settings);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_settings_aggregate_form';
  }

  /**
   * {@inheritdoc}
   *
   * The build form needs to take care of the following:
   *   - Creating a custom form state object for each inner form (and keep it
   *     inside the main form state.
   *   - Generating a render array for each inner form.
   *   - Handle compatibility issues such as #process array and action elements.
   *
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#process'] = \Drupal::service('element_info')->getInfoProperty('form', '#process', []);
    $form['#process'][] = '::processForm';

    if (count($this->innerForms) > 1) {
      $form['tabs'] = [
        '#type' => 'vertical_tabs',
      ];
    }

    foreach ($this->innerForms as $key => $inner_form_object) {
      /** @var \Drupal\Core\Entity\EntityFormInterface $inner_form_object */
      $inner_form_state = static::createInnerFormState($form_state, $inner_form_object, $key);
      // By placing the actual inner form inside a container element (such as
      // details) we gain the freedom to alter the wrapper of the inner form
      // with little damage to the render element attributes of the inner form.
      $inner_form = ['#parents' => [$key]];
      $inner_form = $inner_form_object->buildForm($inner_form, $inner_form_state);

      $form[$key] = [
        '#type' => 'container',
        '#title' => $inner_form_object->getEntity()->type->entity->label(),
        '#weight' => $inner_form_object->getEntity()->type->entity->getWeight(),
        'form' => $inner_form,
      ];
      if (count($this->innerForms) > 1) {
        $form[$key] = [
          '#type' => 'details',
          '#group' => 'tabs',
        ] + $form[$key];
      }
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
      if (function_exists('field_group_form_alter')) {
        field_group_form_alter($form[$key]['form'], $inner_form_state);
      }
    }

    // Handle copyright.
    if (!empty($form['general']['form']['field_copyright'])) {
      $form['general']['form']['field_copyright']['widget'][0]['value']['#field_prefix'] = '&copy; ' . date('Y');
    }

    // Default action elements.
    $form['actions'] = [
      '#type' => 'actions',
      static::MAIN_SUBMIT_BUTTON => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
        '#validate' => ['::validateForm'],
        '#submit' => ['::submitForm'],
      ],
    ];

    return $form;
  }

  /**
   * Process form.
   *
   * This method will be called from FormBuilder::doBuildForm during the process
   * stage.
   * In here we call the #process callbacks that were previously removed.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The altered form element.
   *
   * @see \Drupal\Core\Form\FormBuilder::doBuildForm()
   */
  public function processForm(array &$element, FormStateInterface &$form_state, array &$complete_form) {
    foreach ($this->innerForms as $key => $inner_form) {
      $inner_form_state = static::getInnerFormState($form_state, $key);
      ksm($inner_form_state->getValues());
      foreach ($inner_form_state->get('#process') as $callback) {
        // The callback format was copied from FormBuilder::doBuildForm().
        $element[$key]['form'] = call_user_func_array($inner_form_state->prepareCallback($callback), [
          &$element[$key]['form'],
          &$inner_form_state,
          &$complete_form,
        ]);
        $this->recursiveProcessForm($element[$key]['form'], $key);
      }
    }
    return $element;
  }

  /**
   * Nested process form.
   */
  public function nestedProcessForm(array &$element, FormStateInterface &$form_state, array &$complete_form) {
    $this->recursiveProcessForm($element, $element['#inner_form_key']);
    return $element;
  }

  /**
   * Find any nested submit buttons.
   */
  public function recursiveProcessForm(&$element, $inner_form_key) {
    if (is_array($element)) {
      if (isset($element['#ajax']) && !isset($element['#inner_form_key'])) {
        $element['#inner_form_key'] = $inner_form_key;
        $element['#inner_form_submit'] = !empty($element['#submit']) ? $element['#submit'] : [];
        $element['#submit'] = ['::inlineButtonSubmit'];
        if (!empty($element['#validate'])) {
          $element['#inner_form_validate'] = $element['#validate'];
          $element['#validate'][] = '::inlineButtonValidate';
        }
      }
      if (!empty($element['#process'])) {
        $element['#inner_form_key'] = $inner_form_key;
        $element['#process'][] = '::nestedProcessForm';
      }
      foreach (Element::children($element) as $key) {
        $this->recursiveProcessForm($element[$key], $inner_form_key);
      }
    }
  }

  /**
   * Make sure we use the correct form state.
   */
  public static function inlineButtonValidate(array $element, FormStateInterface $form_state) {
    \Drupal::messenger()->addWarning('Site Settings need validation.');
  }

  /**
   * Make sure we use the correct form state.
   */
  public static function inlineButtonSubmit(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#inner_form_key']) && isset($trigger['#inner_form_submit'])) {
      $key = $trigger['#inner_form_key'];
      $inner_form_state = static::getInnerFormState($form_state, $key);
      /** @var \Drupal\Core\Form\FormSubmitterInterface $form_submitter */
      $form_submitter = \Drupal::service('form_submitter');
      $inner_form_state->setSubmitted();
      $inner_form_state->setSubmitHandlers($trigger['#inner_form_submit']);
      $form_submitter->doSubmitForm($form, $inner_form_state);
      $form_state->setRebuild($inner_form_state->isRebuilding());
      $inner_form_state->setSubmitHandlers([]);

      // Merge in user input changes as submit handler may have altered them.
      $user_input = $form_state->getUserInput();
      NestedArray::setValue($user_input, [$key], $inner_form_state->getUserInput()[$key] ?? []);
      $form_state->setUserInput($user_input);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Form\FormValidatorInterface $form_validator */
    $form_validator = \Drupal::service('form_validator');
    foreach ($this->innerForms as $form_key => $inner_form) {
      $inner_form_state = static::getInnerFormState($form_state, $form_key);
      // Pass through both the form elements validation and the form object
      // validation.
      $inner_form->validateForm($form[$form_key]['form'], $inner_form_state);
      $form_validator->validateForm($inner_form->getFormId(), $form[$form_key]['form'], $inner_form_state);
      foreach ($inner_form_state->getErrors() as $error_element_path => $error) {
        $form_state->setErrorByName($form_key . '][' . $error_element_path, $error);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Form\FormSubmitterInterface $form_submitter */
    $form_submitter = \Drupal::service('form_submitter');
    $success = TRUE;
    foreach ($this->innerForms as $key => $inner_form) {
      $inner_form_state = static::getInnerFormState($form_state, $key);
      // The form state needs to be set as submitted before executing the
      // doSubmitForm method.
      $inner_form_state->setSubmitted();
      $form_submitter->doSubmitForm($form[$key]['form'], $inner_form_state);
      /** @var Drupal\Core\Entity\EntityFormInterface $inner_form_object */
      $inner_form_object = $inner_form_state->getFormObject();
      if (!$inner_form_object->getEntity()->exoSiteSettingsStatus) {
        $success = FALSE;
      }
    }
    \Drupal::messenger()->deleteByType('status');
    if ($success) {
      \Drupal::messenger()->addMessage($this->t('Settings have been saved successfully.'));
    }
    else {
      \Drupal::messenger()->addError($this->t('There was an error when trying to save the settings.'));
    }

  }

  /**
   * Get an inner form state.
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

    $inner_form_state->setValues($form_state->getValues() ?? []);
    $inner_form_state->setUserInput($form_state->getUserInput() ?? []);
    $inner_form_state->setRebuild($form_state->isRebuilding());
    $inner_form_state->setRebuildInfo($form_state->getRebuildInfo());
    $inner_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $inner_form_state->setLimitValidationErrors($form_state->getLimitValidationErrors());
    $inner_form_state->set('field_storage', $inner_form_state->get('field_storage') ?? $form_state->get('field_storage'));
    $inner_form_state->set('inner_form_key', $key);

    // Inline entity form support.
    $inner_form_state->set('inline_entity_form', NestedArray::mergeDeep($inner_form_state->get('inline_entity_form') ?? [], $form_state->get('inline_entity_form') ?? []));
    $form_state->set('inline_entity_form', $inner_form_state->get('inline_entity_form'));

    return $inner_form_state;
  }

  /**
   * Create an inner form state.
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
    if (!$form_state->get([static::INNER_FORM_STATE_KEY, $key])) {
      $inner_form_state = new FormState();
      $inner_form_state->setFormObject($form_object);
      $form_state->set([static::INNER_FORM_STATE_KEY, $key], $inner_form_state);
    }
    return static::getInnerFormState($form_state, $key);
  }

}
