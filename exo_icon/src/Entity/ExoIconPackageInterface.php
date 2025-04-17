<?php

namespace Drupal\exo_icon\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining eXo Icon Package entities.
 */
interface ExoIconPackageInterface extends ConfigEntityInterface {

  /**
   * Get the icon id.
   *
   * The icon id differes from the entity id as the machine name of the entity
   * may be too generic for use as a selector and may cause CSS conflicts.
   *
   * @return string
   *   The icon id.
   */
  public function getIconId();

  /**
   * Get path to the icon package.
   *
   * @return string
   *   The path to the icon package.
   */
  public function getPath();

  /**
   * Get the icon package type.
   *
   * @return string
   *   The icon type. Either 'image' or 'icon'.
   */
  public function getType();

  /**
   * Should this icon package be included globally.
   *
   * @return bool
   *   If TRUE, the icon package will be included on every page.
   */
  public function isGlobal();

  /**
   * Check if this is an SVG icon set.
   *
   * @return bool
   *   Returns TRUE if this is an SVG icon set.
   */
  public function isSvg();

  /**
   * Check if this is a font icon set.
   *
   * @return bool
   *   Returns TRUE if this is a font icon set.
   */
  public function isFont();

  /**
   * Return the stylesheet of the icon package if it exists.
   *
   * @return string
   *   The path to the IcoMoon style.css file.
   */
  public function getStylesheet();

  /**
   * Returns the weight of the icon package.
   *
   * @return int
   *   The icon package weight.
   */
  public function getWeight();

  /**
   * Sets the weight of the icon package.
   *
   * @param int $weight
   *   The weight to set.
   */
  public function setWeight($weight);

  /**
   * Get the icon definitions.
   *
   * @return array
   *   An array of icon definitions.
   */
  public function getDefinitions();

  /**
   * Get icon instances.
   *
   * @var array $definitions
   *   An array of icon definitions.
   *
   * @return \Drupal\exo_icon\ExoIconInterface[]
   *   An array of icon instances.
   */
  public function getInstances(?array $definitions = NULL);

  /**
   * Get icon instance.
   *
   * @var array $definition
   *   An icon definition.
   *
   * @return \Drupal\exo_icon\ExoIconInterface
   *   An icon instance.
   */
  public function getInstance(array $definition);

  /**
   * Get icon package information.
   *
   * @return array
   *   The information for the IcoMoon package.
   */
  public function getInfo();

  /**
   * Get unique IcoMoon package name.
   */
  public function getInfoName();

  /**
   * Get unique IcoMoon package prefix.
   */
  public function getInfoPrefix();

}
