<?php

namespace Drupal\exo_alchemist\Plugin\ExoComponentField;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Url;
use Drupal\exo_alchemist\ExoComponentValue;
use Drupal\exo_alchemist\Plugin\ExoComponentFieldFieldableBase;
use Drupal\file\FileInterface;
use Drupal\link\LinkItemInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * A 'link' adapter for exo components.
 *
 * @ExoComponentField(
 *   id = "link",
 *   label = @Translation("Link"),
 * )
 */
class Link extends ExoComponentFieldFieldableBase {

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $properties = [
      'url' => $this->t('The absolute url of the link.'),
      'title' => $this->t('The title of the link.'),
    ];
    if (!empty($this->getFieldDefinition()->getAdditionalValue('link_icon'))) {
      $properties['icon'] = $this->t('The icon of the link. Should be passed into {{ icon() }}');
      $properties['title_icon'] = $this->t('The title with the icon.');
    }
    if (in_array($this->getWidgetConfig()['type'], ['exo_linkit', 'exo_link'])) {
      $properties['target'] = $this->t('The link target.');
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function validateValue(ExoComponentValue $value) {
    parent::validateValue($value);
    $field = $this->getFieldDefinition();
    if ($field->getAdditionalValue('title_type') === 'disabled') {
      if ($value->has('value')) {
        $value->set('uri', $value->get('value'));
        $value->unset('value');
      }
    }
    if (!$value->has('uri')) {
      throw new PluginException(sprintf('eXo Component Field plugin (%s) requires [default.uri] be set.', $value->getDefinition()->getType()));
    }
    if (!in_array($field->getAdditionalValue('title_type'), [
      'disabled',
      'optional',
    ])) {
      if (!$value->has('title')) {
        throw new PluginException(sprintf('eXo Component Field plugin (%s) requires [default.title] be set.', $value->getDefinition()->getType()));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageConfig() {
    $field = $this->getFieldDefinition();
    $title_type = DRUPAL_REQUIRED;
    if ($type = $field->getAdditionalValue('title_type')) {
      switch ($type) {
        case 'optional':
          $title_type = DRUPAL_OPTIONAL;
          break;

        case 'disabled':
          $title_type = DRUPAL_DISABLED;
          break;
      }
    }
    $link_type = LinkItemInterface::LINK_GENERIC;
    if ($type = $field->getAdditionalValue('link_type')) {
      switch ($type) {
        case 'internal':
          $link_type = LinkItemInterface::LINK_INTERNAL;
          break;

        case 'external':
          $link_type = LinkItemInterface::LINK_EXTERNAL;
          break;
      }
    }
    return [
      'type' => 'link',
      'settings' => [
        'link_type' => $link_type,
        'title' => $title_type,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetConfig() {
    $widget = [
      'type' => 'link_default',
      'settings' => [
        'icon' => !empty($this->getFieldDefinition()->getAdditionalValue('link_icon')),
        'target' => TRUE,
      ],
    ];
    $class_list = $this->getFieldDefinition()->getAdditionalValue('link_class_list');
    if (!empty($class_list) && is_array($class_list)) {
      $widget['settings']['class'] = TRUE;
      $widget['settings']['class_list'] = $class_list;
    }
    if (\Drupal::moduleHandler()->moduleExists('exo_linkit')) {
      $widget['type'] = 'exo_linkit';
      $widget['settings']['linkit_profile'] = 'exo';
    }
    elseif (\Drupal::moduleHandler()->moduleExists('exo_link')) {
      $widget['type'] = 'exo_link';
    }
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition() {
    parent::processDefinition();
    $field = $this->getFieldDefinition();
    foreach ($field->getDefaults() as $default) {
      if ($icon = $default->getValue('icon')) {
        $default->setValue(['options', 'attributes', 'data-icon'], $icon);
        $default->unsetValue('icon');
      }
      if ($class = $default->getValue('class')) {
        $default->setValue(['options', 'attributes', 'class'], $class);
        $default->unsetValue('class');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue($delta = 0) {
    return [
      'title' => $this->t('Placeholder for @label title', [
        '@label' => strtolower($this->getFieldDefinition()->getLabel()),
      ]),
      'uri' => 'internal:/',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewValue(FieldItemInterface $item, $delta, array $contexts) {
    /** @var \Drupal\link\LinkItemInterface $item */
    $value = $item->getValue();
    $url = $item->getUrl()->setAbsolute()->toString();
    // Point media to the actual file.
    if (substr($value['uri'], 0, 13) === 'entity:media/') {
      $media_id = substr($value['uri'], 13);
      $media = \Drupal::entityTypeManager()->getStorage('media')->load($media_id);
      if ($media instanceof MediaInterface) {
        if (\Drupal::service('module_handler')->moduleExists('media_entity_download')) {
          $url = Url::fromRoute('media_entity_download.download', ['media' => $media->id()], [
            'query' => [ResponseHeaderBag::DISPOSITION_INLINE => NULL],
          ])->toString();
        }
        else {
          $source_field = $media->getSource()->getSourceFieldDefinition($media->get('bundle')->entity);
          if ($source_field && $media->hasField($source_field->getName()) && $media->get($source_field->getName())->entity instanceof FileInterface) {
            /** @var \Drupal\file\FileInterface $file */
            $file = $media->get($source_field->getName())->entity;
            $url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }
      }
    }
    $value['url'] = $url;
    $value['title_icon'] = $value['title'];
    if (isset($value['attributes']['data-icon'])) {
      $value['icon'] = $value['attributes']['data-icon'];
    }
    elseif (isset($value['options']['attributes']['data-icon'])) {
      $value['icon'] = $value['options']['attributes']['data-icon'];
    }
    if (!empty($value['icon'])) {
      $value['title_icon'] = exo_icon($value['title'])->setIcon($value['options']['attributes']['data-icon']);
      if (isset($value['options']['attributes']['data-icon-position'])) {
        $value['title_icon']->setIconPosition($value['options']['attributes']['data-icon-position']);
      }
    }
    $value['target'] = $value['options']['attributes']['target'] ?? '_self';
    if (!empty($value['options']['attributes'])) {
      $value['#field_attributes'] = $value['options']['attributes'];
    }
    return $value;
  }

}
