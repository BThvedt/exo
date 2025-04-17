<?php

namespace Drupal\exo_alchemist\Plugin;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;

/**
 * A 'form' adapter for exo components.
 */
trait ExoComponentFieldBuildFormTrait {

  use ExoComponentFieldDisplayFormTrait;

  /**
   * The form.
   *
   * @var [type]
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function propertyInfoBuildForm() {
    $properties = [
      'render' => $this->t('The form renderable.'),
      'form.tag' => $this->t('Will be "form" when in full view and "div" in preview.'),
      'form.builder' => $this->t('Contains the elements required for proper form submission.'),
      'form.action' => $this->t('The form action url.'),
    ];
    foreach ($this->formProperties() as $key => $label) {
      $properties['form.' . $key] = $label;
    }
    $form = $this->viewValueGetForm();
    if ($form) {
      $builder_children = $this->formBuilderChildren();
      foreach (Element::children($form) as $key) {
        if (!isset($builder_children[$key])) {
          $properties['form.field.' . $key] = $this->t('Form field with key of %key', [
            '%key' => $key,
          ]);
        }
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewValueBuildForm(EntityInterface $entity, array $contexts) {
    $value = [];
    $form = $this->viewValueGetForm($entity, $contexts);
    if (!empty($form)) {
      $is_layout_builder = $this->isLayoutBuilder($contexts);
      if ($is_layout_builder) {
        $render = $this->getFormAsPlaceholder((array) $form);
      }
      else {
        $render = $form;
      }
      // Rendered form.
      $value['render'] = $render;

      // Individual form options.
      $builder_children = $this->formBuilderChildren();
      $value['form'] = [];
      $value['form']['tag'] = $is_layout_builder ? 'div' : 'form';
      foreach ($this->formProperties() as $key => $label) {
        if (!isset($builder_children[$key])) {
          $value['form'][$key] = $form['#' . $key];
        }
      }
      $value['form']['field'] = [];
      foreach (Element::children($form) as $key) {
        $value['form']['field'][$key] = $form[$key];
      }

      $value['form']['builder'] = [
        '#attached' => $form['#attached'],
      ];
      foreach ($builder_children as $key => $label) {
        if (isset($form[$key])) {
          $value['form']['builder'][$key] = $form[$key];
        }
      }

      if (isset($form['#action'])) {
        $value['form']['attributes']['action'] = UrlHelper::stripDangerousProtocols($form['#action']);
      }
      $value['form']['attributes']['method'] = $form['#method'];
      $value['form']['attributes']['accept-charset'] = 'UTF-8';
      $value['form']['attributes'] = new Attribute($value['form']['attributes']);
      $this->addCacheableDependency($contexts, $form);
    }
    return $value;
  }

  /**
   * The form properties to extract.
   */
  protected function formProperties() {
    return [
      'method' => $this->t('The form method.'),
      'attributes' => $this->t('The form attributes.'),
    ];
  }

  /**
   * The form children that will make of the builder.
   */
  protected function formBuilderChildren() {
    return [
      'form_build_id' => $this->t('The form build id.'),
      'form_token' => $this->t('The form token.'),
      'form_id' => $this->t('The form id.'),
    ];
  }

  /**
   * Get a form class.
   */
  protected function viewValueGetForm(?EntityInterface $entity = NULL, ?array $contexts = NULL) {
    if (!isset($this->form)) {
      $this->form = $this->viewValueForm($entity, $contexts);
      foreach (Element::children($this->form) as $key) {
        unset($this->form[$key]['#attributes']['autofocus']);
      }
    }
    return $this->form;
  }

  /**
   * Build a form class.
   */
  protected function viewValueForm(?EntityInterface $entity = NULL, ?array $contexts = NULL) {
    return [];
  }

}
