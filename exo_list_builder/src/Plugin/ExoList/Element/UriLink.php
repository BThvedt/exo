<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Element;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\Plugin\ExoListElementContentBase;

/**
 * Defines a eXo list element for rendering a content entity field.
 *
 * @ExoListElement(
 *   id = "uri_link",
 *   label = @Translation("Link"),
 *   description = @Translation("Render the uri as a link."),
 *   weight = 0,
 *   field_type = {
 *     "uri",
 *   },
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = FALSE,
 * )
 */
class UriLink extends ExoListElementContentBase {
  use ExoIconTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'title_type' => 'entity_label',
      'title' => NULL,
      'target' => NULL,
      'icon' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, EntityListInterface $entity_list, array $field) {
    $form = parent::buildConfigurationForm($form, $form_state, $entity_list, $field);
    unset($form['link']);

    $configuration = $this->getConfiguration();
    $form['title_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Title Type'),
      '#id' => $form['#id'] . '--title-type',
      '#options' => [
        'uri' => $this->t('URI'),
        'url' => $this->t('URL'),
        'custom' => $this->t('Custom'),
      ],
      '#default_value' => $configuration['title_type'],
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Title'),
      '#default_value' => $configuration['title'],
      '#states' => [
        'visible' => [
          '#' . $form['#id'] . '--title-type input' => ['value' => 'custom'],
        ],
      ],
    ];

    $form['icon'] = [
      '#type' => 'exo_icon',
      '#title' => $this->t('Icon'),
      '#default_value' => $configuration['icon'],
    ];

    $form['target'] = [
      '#type' => 'checkbox',
      '#title' => t('Open link in new window'),
      '#return_value' => '_blank',
      '#default_value' => $configuration['target'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewItem(EntityInterface $entity, FieldItemInterface $field_item, array $field) {
    $configuration = $this->getConfiguration();
    $uri = $field_item->value;
    if (substr($uri, 0, 9) === 'public://' || substr($uri, 0, 10) === 'private://') {
      $url = \Drupal::service('file_url_generator')->generate($uri);
    }
    else {
      $url = Url::fromUri($uri);
    }
    switch ($configuration['title_type']) {
      case 'url':
        $link_title = $url->toString();
        break;

      case 'custom':
        $link_title = $configuration['title'];
        break;

      default:
        $link_title = $uri;
    }
    $options = [];
    if ($configuration['target']) {
      $options = $url->getOptions();
      $options['attributes']['target'] = '_blank';
      $url->setOptions($options);
    }
    $link = Link::fromTextAndUrl($link_title, $url)->toString();
    if ($configuration['icon']) {
      $link = exo_icon($link_title)->setIcon($configuration['icon'])->setIconOnly() . $link;
    }
    return $link;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewPlainItem(EntityInterface $entity, FieldItemInterface $field_item, array $field) {
    $file = $field_item->entity;
    return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
  }

}
