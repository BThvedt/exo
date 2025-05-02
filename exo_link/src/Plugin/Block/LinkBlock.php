<?php

namespace Drupal\exo_link\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\LinkItemInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Url;
use Drupal\exo_icon\ExoIconTranslationTrait;

/**
 * Provides a block to display a single link.
 *
 * @Block(
 *   id = "link",
 *   admin_label = @Translation("Link"),
 * )
 */
class LinkBlock extends BlockBase {
  use ExoIconTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'uri' => '',
      'title' => '',
      'icon' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->configuration['title'],
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    $form['uri'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      // The current field value could have been entered by a different user.
      // However, if it is inaccessible to the current user, do not display it
      // to them.
      '#default_value' => $this->configuration['uri'] ? static::getUriAsDisplayableString($this->configuration['uri']) : NULL,
      '#element_validate' => [[get_called_class(), 'validateUriElement']],
      '#maxlength' => 2048,
      '#required' => TRUE,
      '#link_type' => LinkItemInterface::LINK_GENERIC,
    ];

    // If the field is configured to support internal links, it cannot use the
    // 'url' form element and we have to do the validation ourselves.
    if ($this->supportsInternalLinks()) {
      $form['uri']['#type'] = 'entity_autocomplete';
      $form['uri']['#target_type'] = 'node';
      $form['uri']['#attributes']['data-autocomplete-first-character-blacklist'] = '/#?';
      $form['uri']['#process_default_value'] = FALSE;
    }

    // If the field is configured to allow only internal links, add a useful
    // element prefix and description.
    if (!$this->supportsExternalLinks()) {
      $form['uri']['#field_prefix'] = rtrim(Url::fromRoute('<front>', [], ['absolute' => TRUE]), '/');
      $form['uri']['#description'] = $this->t('This must be an internal path such as %add-node. You can also start typing the title of a piece of content to select it. Enter %front to link to the front page.', ['%add-node' => '/node/add', '%front' => '<front>']);
    }
    // If the field is configured to allow both internal and external links,
    // show a useful description.
    elseif ($this->supportsExternalLinks() && $this->supportsInternalLinks()) {
      $form['uri']['#description'] = $this->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as %add-node or an external URL such as %url. Enter %front to link to the front page.', [
        '%front' => '<front>',
        '%add-node' => '/node/add',
        '%url' => 'http://example.com',
      ]);
    }
    // If the field is configured to allow only external links, show a useful
    // description.
    elseif ($this->supportsExternalLinks() && !$this->supportsInternalLinks()) {
      $form['uri']['#description'] = $this->t('This must be an external URL such as %url.', ['%url' => 'http://example.com']);
    }

    $form['icon'] = [
      '#type' => 'exo_icon',
      '#title' => $this->t('Icon'),
      '#default_value' => $this->configuration['icon'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['uri'] = $form_state->getValue('uri');
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['icon'] = $form_state->getValue('icon');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $title = $this->configuration['title'];
    if ($icon = $this->configuration['icon']) {
      $title = $this->icon($title)->setIcon($icon);
    }
    $build['link'] = [
      '#title' => $title,
      '#type' => 'link',
      '#url' => Url::fromUri($this->configuration['uri']),
    ];
    return $build;
  }

  /**
   * Indicates enabled support for link to routes.
   *
   * @return bool
   *   Returns TRUE if the block is configured to support links to
   *   routes, otherwise FALSE.
   */
  protected function supportsInternalLinks() {
    return TRUE;
  }

  /**
   * Indicates enabled support for link to external URLs.
   *
   * @return bool
   *   Returns TRUE if the block is configured to support links to
   *   external URLs, otherwise FALSE.
   */
  protected function supportsExternalLinks() {
    return TRUE;
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
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }

    return $displayable_string;
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
    $uri = $string;

    // Detect entity autocomplete string, map to 'entity:' URI.
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($string);
    if ($entity_id !== NULL) {
      $uri = 'entity:node/' . $entity_id;
    }
    // Detect a schemeless string, map to 'internal:' URI.
    elseif (!empty($string) && parse_url($string, PHP_URL_SCHEME) === NULL) {
      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      // - '<front>' -> '/'
      // - '<front>#foo' -> '/#foo'
      if (strpos($string, '<front>') === 0) {
        $string = '/' . substr($string, strlen('<front>'));
      }
      $uri = 'internal:' . $string;
    }

    return $uri;
  }

}
