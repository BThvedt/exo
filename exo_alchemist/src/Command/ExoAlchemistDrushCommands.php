<?php

namespace Drupal\exo_alchemist\Command;

use Drupal\exo_alchemist\ExoComponentManager;
use Drush\Commands\DrushCommands;

/**
 * Defines Drush commands for the Search API.
 */
class ExoAlchemistDrushCommands extends DrushCommands {

  /**
   * The eXo theme manager.
   *
   * @var \Drupal\exo\ExoComponentManager
   */
  protected $exoComponentManager;

  /**
   * Constructs a ExoAlchemistDrushCommands object.
   *
   * @param \Drupal\exo_alchemist\ExoComponentManager $exo_component_manager
   *   The eXo commponent manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" or "search_api_server" entity types'
   *   storage handlers couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the "search_api_index" or "search_api_server" entity types are
   *   unknown.
   */
  public function __construct(ExoComponentManager $exo_component_manager) {
    $this->exoComponentManager = $exo_component_manager;
  }

  // /**
  //  * Generate global exo-theme.scss.
  //  *
  //  * @command exo:exo-scss
  //  *
  //  * @usage drush exo:scss
  //  *   Run the exo theme generator.
  //  *
  //  * @aliases exo-scss
  //  *
  //  * @throws \Exception
  //  *   If no index or no server were passed or passed values are invalid.
  //  */
  // public function exoScss() {
  //   $dirname = 'public://exo';
  //   $this->fileSystem->prepareDirectory($dirname, FILE_CREATE_DIRECTORY);
  // // Generate exo-common.scss.
  //   $destination = $dirname . '/exo-common.scss';
  //   $exo_path = DRUPAL_ROOT . '/' . \Drupal::service('extension.list.module')->getPath('exo');
  //   $data = file_get_contents($exo_path . '/src/scss/_common.scss');
  //   $data = str_replace("@import '", "@import '" . $exo_path . '/src/scss/', $data);
  //   file_save_data($data, $destination, FILE_EXISTS_REPLACE);
  // // Generate exo-theme.scss.
  //   $destination = $dirname . '/exo-theme.scss';
  //   $theme = \Drupal::service('plugin.manager.exo_theme')->getCurrentTheme();
  //   $exo_path = DRUPAL_ROOT . '/' . $theme->getScssPath() . '/exo-theme';
  //   $data = "@import '$exo_path';";
  //   file_save_data($data, $destination, FILE_EXISTS_REPLACE);
  // $this->logger()->success(dt('eXo utilities generated at @destination', ['@destination' => $dirname]));
  // }

  /**
   * Install a specific alchemist component.
   *
   * @param string $component_id
   *   The component id.
   *
   * @command exo:alchemist:install
   *
   * @usage drush exo:alchemist:install
   *   Install a specific alchemist component.
   *
   * @aliases eai
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   */
  public function exoComponentInstall($component_id) {
    // Make sure we have all current definitions.
    $this->exoComponentManager->clearCachedDefinitions();

    if (!$definition = $this->exoComponentManager->getDefinition($component_id, FALSE)) {
      $this->logger->error(dt('Component id (@id) is not valid. Available component ids are @list.', [
        '@id' => $component_id,
        '@list' => implode(', ', array_keys($this->exoComponentManager->getDefinitions())),
      ]));
      return;
    }

    if ($this->exoComponentManager->getInstalledDefinition($component_id, FALSE)) {
      $this->logger->error(dt('Component id (@id) is already installed.', [
        '@id' => $component_id,
      ]));
      return;
    }

    if ($this->exoComponentManager->installEntityType($definition)) {
      $this->logger->info(dt('Component installed successfully. (@label: @id)', [
        '@id' => $component_id,
        '@label' => $definition->getLabel(),
      ]));
      return TRUE;
    }
    else {
      $this->logger->error(dt('There was an error installing the component. (@label: @id)', [
        '@id' => $component_id,
        '@label' => $definition->getLabel(),
      ]));
    }
    return FALSE;
  }

  /**
   * Uninstall a specific alchemist component.
   *
   * @param string $component_id
   *   The component id.
   *
   * @command exo:alchemist:uninstall
   *
   * @usage drush exo:alchemist:uninstall
   *   Uninstall a specific alchemist component.
   *
   * @aliases earem
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   */
  public function exoComponentUninstall($component_id) {
    // Make sure we have all current definitions.
    $this->exoComponentManager->clearCachedDefinitions();

    if (!$definition = $this->exoComponentManager->getInstalledDefinition($component_id, FALSE)) {
      $this->logger->error(dt('Component id (@id) is not installed. Available component ids are @list.', [
        '@id' => $component_id,
        '@list' => implode(', ', array_keys($this->exoComponentManager->getInstalledDefinitions())),
      ]));
      return;
    }

    if ($this->exoComponentManager->uninstallEntityType($definition)) {
      $this->logger->info(dt('Component uninstalled successfully. (@label: @id)', [
        '@id' => $component_id,
        '@label' => $definition->getLabel(),
      ]));
      return TRUE;
    }
    else {
      $this->logger->error(dt('There was an error uninstalling the component. (@label: @id)', [
        '@id' => $component_id,
        '@label' => $definition->getLabel(),
      ]));
    }
    return FALSE;
  }

  /**
   * Update a specific alchemist component.
   *
   * @param string $component_id
   *   The component id.
   * @param array $options
   *   (optional) An array of options.
   *
   * @command exo:alchemist:update
   *
   * @option force
   *   If TRUE, will force an update even if there are no pending changes.
   *
   * @usage drush exo:alchemist:update
   *   Update a specific alchemist component.
   *
   * @aliases eau
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   */
  public function exoComponentUpdate($component_id, array $options = ['force' => FALSE]) {
    $force = !empty($options['force']);
    // Make sure we have all current definitions.
    $this->exoComponentManager->clearCachedDefinitions();

    // Only allow installed and non-computed components.
    $installed_definitions = array_filter($this->exoComponentManager->getInstalledDefinitions(), function ($definition) {
      /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition */
      return !$definition->isComputed();
    });

    if (!isset($installed_definitions[$component_id])) {
      $this->logger->error(dt('Component id (@id) is not valid. Available component ids are @list.', [
        '@id' => $component_id,
        '@list' => implode(', ', array_keys($installed_definitions)),
      ]));
      return;
    }

    /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition */
    $definition = $installed_definitions[$component_id];
    if (!$this->exoComponentManager->installedDefinitionHasChanges($definition) && !$force) {
      $this->logger->error(dt('Component does not have any pending updates or you do not have access to update it. (@label: @id)', [
        '@id' => $component_id,
        '@label' => $definition->getLabel(),
      ]));
      return;
    }

    if ($this->exoComponentManager->updateInstalledDefinition($definition)) {
      $this->logger->info(dt('Component updated successfully. (@label: @id)', [
        '@id' => $component_id,
        '@label' => $definition->getLabel(),
      ]));
    }
    else {
      $this->logger->error(dt('There was an error updating the component. (@label: @id)', [
        '@id' => $component_id,
        '@label' => $definition->getLabel(),
      ]));
    }
  }

  /**
   * Update all pending alchemist components.
   *
   * @command exo:alchemist:update:all
   *
   * @option force
   *   If TRUE, will force an update even if there are no pending changes.
   *
   * @usage drush exo:alchemist:update:all
   *   Update all pending alchemist components.
   *
   * @aliases eaua
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   */
  public function exoComponentUpdateAll(array $options = ['force' => FALSE]) {
    $force = !empty($options['force']);
    // Make sure we have all current definitions.
    $this->exoComponentManager->clearCachedDefinitions();

    // Only allow installed and visible components.
    $updated = [];
    foreach ($this->exoComponentManager->getInstalledDefinitions() as $definition) {
      /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition */
      if ($definition->isComputed()) {
        continue;
      }
      if ($force || $this->exoComponentManager->installedDefinitionHasChanges($definition)) {
        if ($this->exoComponentManager->updateInstalledDefinition($definition)) {
          $updated[] = $definition->getLabel() . ': ' . $definition->id();
        }
      }
    }

    if (!empty($updated)) {
      $this->logger->info(dt('Components updated successfully. (@components)', [
        '@components' => implode(', ', $updated),
      ]));
    }
    else {
      $this->logger->error(dt('No components were updated as all components are current.'));
    }
  }

  /**
   * Reinstall all pending alchemist components.
   *
   * @param string $component_id
   *   The component id.
   *
   * @command exo:alchemist:reinstall
   *
   * @usage drush exo:alchemist:reinstall
   *   Reinstall a specific alchemist component.
   *
   * @aliases ear
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   */
  public function exoComponentReinstall($component_id) {
    if ($this->exoComponentUninstall($component_id)) {
      $this->exoComponentInstall($component_id);
    }
  }

  /**
   * Fix config import issues for alchemist components.
   *
   * @command exo:alchemist:fix
   *
   * @usage drush exo:alchemist:reinstall
   *   Reinstall a specific alchemist component.
   *
   * @aliases ear
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   */
  public function exoComponentFix() {
    foreach ($this->exoComponentManager->getInstalledDefinitions() as $definition) {
      /** @var \Drupal\exo_alchemist\Definition\ExoComponentDefinition $definition */
      // Fix all missing components.
      if ($definition->isMissing() && !$definition->isComputed()) {
        $this->exoComponentUninstall($definition->id());
      }

    }
  }

}
