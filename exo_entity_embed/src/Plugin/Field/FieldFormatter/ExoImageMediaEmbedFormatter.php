<?php

namespace Drupal\exo_entity_embed\Plugin\Field\FieldFormatter;

use Drupal\exo_image\Plugin\Field\FieldFormatter\ExoImageMediaFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\exo\Plugin\Field\FieldWidget\ExoSizeWidget;
use Drupal\exo\Plugin\Field\FieldWidget\ExoAlignmentHorizontalWidget;
use Drupal\link\LinkItemInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'exo image' formatter.
 *
 * @FieldFormatter(
 *   id = "exo_image_media_embed_deprecated",
 *   label = @Translation("eXo Image Embed (deprecated)"),
 *   provider = "exo_image",
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ExoImageMediaEmbedFormatter extends ExoImageMediaFormatter {
  use ExoIconTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'alignment' => 'center',
      'size' => 'medium',
      'uri' => NULL,
      'new_window' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];

    $element['alignment'] = [
      '#type' => 'exo_radios',
      '#exo_style' => 'inline',
      '#title' => $this->t('Alignment'),
      '#default_value' => $this->getSetting('alignment'),
      '#options' => ExoAlignmentHorizontalWidget::defaultOptions(),
    ];

    $element['size'] = [
      '#type' => 'exo_radios',
      '#exo_style' => 'inline',
      '#title' => $this->t('Size'),
      '#default_value' => $this->getSetting('size'),
      '#options' => ExoSizeWidget::defaultOptions(),
    ];

    $element['uri'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Link'),
      '#description' => $this->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as %add-node or an external URL such as %url. Enter %front to link to the front page.', [
        '%front' => '<front>',
        '%add-node' => '/node/add',
        '%url' => 'http://example.com',
      ]),
      '#default_value' => $this->getSetting('uri') ? static::getUriAsDisplayableString($this->getSetting('uri')) : NULL,
      '#element_validate' => [[get_called_class(), 'validateUriElement']],
      '#maxlength' => 2048,
      '#target_type' => 'node',
      '#link_type' => LinkItemInterface::LINK_GENERIC,
      '#process_default_value' => FALSE,
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
    ];

    $element['new_window'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open link in new window'),
      '#description' => $this->t('If selected, the menu link will open in a new window/tab when clicked.'),
      '#default_value' => $this->getSetting('new_window'),
      '#states' => [
        'visible' => [
          ':input[name="attributes[data-entity-embed-display-settings][uri]"]' => ['filled' => TRUE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as $key => $element) {
      foreach ($this->getBreakpointSettings() as $breakpoint => $data) {
        if (isset($element[$breakpoint])) {
          $item = &$elements[$key][$breakpoint];
          $item['#attributes']['class'][] = 'exo-embed-alignment-' . $this->getSetting('alignment');
          $item['#attributes']['class'][] = 'exo-embed-size-' . $this->getSetting('size');
          if ($uri = $this->getSetting('uri')) {
            $url = Url::fromUri($uri);
            $item['#tag'] = 'a';
            $item['#attributes']['href'] = $url->toString();
            if ($this->getSetting('new_window')) {
              $item['#attributes']['target'] = '_blank';
            }
          }
        }
      }
    }

    return $elements;
  }

  /**
   * Form element validation handler for the 'uri' element.
   *
   * Disallows saving inaccessible or untrusted URLs.
   */
  public static function validateUriElement($element, FormStateInterface $form_state, $form) {
    $uri = static::getUserEnteredStringAsUri($element['#value']);
    $form_state->setValueForElement($element, $uri);

    // If getUserEnteredStringAsUri() mapped the entered value to a 'internal:'
    // URI , ensure the raw value begins with '/', '?' or '#'.
    // @todo '<front>' is valid input for BC reasons, may be removed by
    //   https://www.drupal.org/node/2421941
    if (parse_url($uri, PHP_URL_SCHEME) === 'internal' && !in_array($element['#value'][0], [
      '/',
      '?',
      '#',
    ], TRUE) && substr($element['#value'], 0, 7) !== '<front>') {
      $form_state->setError($element, t('Manually entered paths should start with /, ? or #.'));
      return;
    }
  }

  /**
   * Gets the user-entered string as a URI.
   *
   * The following two forms of input are mapped to URIs:
   * - entity autocomplete ("label (entity id)") strings: to 'entity:' URIs;
   * - strings without a detectable scheme: to 'internal:' URIs.
   *
   * This method is the inverse of ::getUriAsDisplayableString().
   *
   * @param string $string
   *   The user-entered string.
   *
   * @return string
   *   The URI, if a non-empty $uri was passed.
   *
   * @see static::getUriAsDisplayableString()
   */
  protected static function getUserEnteredStringAsUri($string) {
    // By default, assume the entered string is an URI.
    $uri = trim($string);

    // Detect entity autocomplete string, map to 'entity:' URI.
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($string);
    if ($entity_id !== NULL) {
      $uri = 'entity:node/' . $entity_id;
    }
    // Detect a schemeless string, map to 'internal:' URI.
    elseif (!empty($string) && parse_url($string, PHP_URL_SCHEME) === NULL) {
      if (strpos($string, '<front>') === 0) {
        $string = '/' . substr($string, strlen('<front>'));
      }
      $uri = 'internal:' . $string;
    }

    return $uri;
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of ::getUserEnteredStringAsUri().
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The displayable string.
   *
   * @see static::getUserEnteredStringAsUri()
   */
  protected static function getUriAsDisplayableString($uri) {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      // https://www.drupal.org/node/2423093.
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }

    return $displayable_string;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if (substr($route_name, 0, 27) == 'entity.entity_view_display.') {
      return FALSE;
    }
    return parent::isApplicable($field_definition);
  }

}
