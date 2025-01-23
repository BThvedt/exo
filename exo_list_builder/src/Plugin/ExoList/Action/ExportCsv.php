<?php

namespace Drupal\exo_list_builder\Plugin\ExoList\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\Url;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\exo_list_builder\ExoListManagerInterface;
use Drupal\exo_list_builder\Plugin\ExoListActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a eXo list action for batch operations.
 *
 * @ExoListAction(
 *   id = "export_csv",
 *   label = @Translation("Export to CSV"),
 *   description = @Translation("Export to CSV file."),
 *   weight = 0,
 *   entity_type = {},
 *   bundle = {},
 *   queue = true,
 * )
 */
class ExportCsv extends ExoListActionBase {

  /**
   * The private export directory.
   *
   * @var string
   */
  const CSV_DIRECTORY = 'private://exo-entity-list/export';

  /**
   * Store CSV permanently.
   *
   * @var bool
   */
  const CSV_PRESERVE = FALSE;

  /**
   * Store CSV as a managed file.
   *
   * @var bool
   */
  const CSV_MANAGED = TRUE;

  /**
   * The delimiter.
   *
   * @var string
   */
  const DELIMITER = ',';

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The list element manager service.
   *
   * @var \Drupal\exo_list_builder\ExoListManagerInterface
   */
  protected $elementManager;

  /**
   * The temp storage.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $temp;

  /**
   * LogGeneratorBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\exo_list_builder\ExoListManagerInterface $element_manager
   *   The element manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, ExoListManagerInterface $element_manager, SharedTempStoreFactory $tempstore) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->renderer = $renderer;
    $this->elementManager = $element_manager;
    $this->temp = $tempstore->get('exo_list_builder');
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
      $container->get('renderer'),
      $container->get('plugin.manager.exo_list_element'),
      $container->get('tempstore.shared')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeStart(EntityListInterface $entity_list, array &$context) {
    parent::executeStart($entity_list, $context);

    $this->prepareCsvFile($entity_list, $context);
    // Add BOM to fix UTF-8 in Excel.
    $headers = $this->getCsvHeader($entity_list, $context);
    if ($headers) {
      $data = [$headers];
      $this->temp->set($context['results']['csv_file_uri'], $data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity_id, EntityListInterface $entity_list, $selected, array &$context) {
    $data = $this->temp->get($context['results']['csv_file_uri']) ?? [];
    if ($value = $this->getCsvRow($entity_id, $entity_list, $selected, $context)) {
      $data[] = $value;
      $this->temp->set($context['results']['csv_file_uri'], $data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeFinish(EntityListInterface $entity_list, array &$results) {
    parent::executeFinish($entity_list, $results);

    // Store data as file.
    $data = $this->temp->get($results['csv_file_uri']) ?? [];
    $handle = fopen($results['csv_file_path'], 'a');
    fwrite($handle, (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    // Write headers now.
    foreach ($data as $row) {
      fputcsv($handle, $row, static::DELIMITER);
    }
    fclose($handle);

    // Hide default message.
    $results['entity_list_hide_message'] = TRUE;
    if (PHP_SAPI !== 'cli' && isset($results['csv_file_uri']) && file_exists($results['csv_file_uri'])) {
      $file_uri = $results['csv_file_uri'];
      /** @var \Drupal\Core\Access\CsrfTokenGenerator $csrf_token */
      $csrf_token = \Drupal::service('csrf_token');
      $token = $csrf_token->get($file_uri);
      $query_options = [
        'query' => [
          'token' => $token,
          'file' => $file_uri,
        ],
      ];

      $download_url = Url::fromRoute('exo_list_builder.action.export.download', [
        'exo_entity_list' => $entity_list->id(),
      ], $query_options)->toString();
      \Drupal::messenger()->addStatus(t("Export successful. The download should automatically start shortly. If it doesn't, click <a data-auto-download href='@download_url'>Download</a>.", [
        '@download_url' => $download_url,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function notifyEmailFinish(EntityListInterface $entity_list, array $results, $email, $subject = NULL, $message = NULL, $link_text = NULL, $link_url = NULL, $attachments = []) {
    if ($this->supportsJobQueue()) {
      $link_text = $link_text ?: $this->t('Download CSV');
      $link_url = \Drupal::service('file_url_generator')->generateAbsoluteString($results['csv_file_uri']);

      $attachments[] = [
        'filepath' => $results['csv_file_uri'],
        'filename' => $results['csv_filename'],
        'filemime' => 'text/csv',
      ];
    }
    return parent::notifyEmailFinish($entity_list, $results, $email, $subject, $message, $link_text, $link_url, $attachments);
  }

  /**
   * Prepare the export file.
   *
   * @param \Drupal\exo_list_builder\EntityListInterface $entity_list
   *   The entity list.
   * @param array $context
   *   An array of the batch context.
   *
   * @return false|string
   *   The file path or false
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function prepareCsvFile(EntityListInterface $entity_list, array &$context) {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $directory = $this->getCsvDirectory($entity_list, $context);
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $filename = $this->getCsvFilename($entity_list, $context);
    $file_uri = $directory . '/' . $filename;
    $file_path = $file_system->realpath($file_uri);

    if (static::CSV_MANAGED) {
      /** @var \Drupal\file\FileRepositoryInterface $file_repository */
      $file_repository = \Drupal::service('file.repository');
      if (file_exists($file_uri)) {
        $file_system->delete($file_uri);
      }
      $file = $file_repository->writeData('', $file_uri, FileSystemInterface::EXISTS_ERROR);
      if (static::CSV_PRESERVE) {
        $file->setPermanent();
      }
      else {
        $file->setTemporary();
      }
      $file->save();
      \Drupal::service('file.usage')->add($file, 'file', $entity_list->getEntityTypeId(), $entity_list->id());
      // URI may have changed.
      $file_uri = $file->getFileUri();
    }
    else {
      if (file_exists($file_uri)) {
        $file_system->delete($file_uri);
      }
      $file_system->saveData('', $file_uri);
    }

    $context['results']['csv_filename'] = $filename;
    $context['results']['csv_file_path'] = $file_path;
    $context['results']['csv_file_uri'] = $file_uri;
    return $file_path;
  }

  /**
   * Get CSV file directory.
   *
   * @param \Drupal\exo_list_builder\EntityListInterface $entity_list
   *   The entity list.
   * @param array $context
   *   An array of the batch context.
   *
   * @return string
   *   The file directory.
   */
  protected function getCsvDirectory(EntityListInterface $entity_list, array &$context) {
    return static::CSV_DIRECTORY;
  }

  /**
   * Get CSV file filename.
   *
   * @param \Drupal\exo_list_builder\EntityListInterface $entity_list
   *   The entity list.
   * @param array $context
   *   An array of the batch context.
   *
   * @return string
   *   The file name.
   */
  protected function getCsvFilename(EntityListInterface $entity_list, array &$context) {
    $filename = md5($entity_list->id() . $this->getPluginId());
    if (static::CSV_PRESERVE) {
      $filename .= '-' . \Drupal::time()->getRequestTime();
    }
    return $filename . '.csv';
  }

  /**
   * Get CSV file headers.
   *
   * @param \Drupal\exo_list_builder\EntityListInterface $entity_list
   *   The entity list.
   * @param array $context
   *   The batch context.
   *
   * @return array
   *   The CSV file headers.
   */
  protected function getCsvHeader(EntityListInterface $entity_list, array &$context) {
    $headers = [];
    foreach ($entity_list->getFields() as $field_id => $field) {
      $headers[$field_id] = $field['display_label'] ?: $field['label'];
    }
    return $headers;
  }

  /**
   * Get CSV file row.
   *
   * @param string $entity_id
   *   The entity id.
   * @param \Drupal\exo_list_builder\EntityListInterface $entity_list
   *   The entity list.
   * @param bool $selected
   *   Will be true if entity was selected.
   * @param array $context
   *   The batch context.
   *
   * @return array
   *   The CSV file row.
   */
  protected function getCsvRow($entity_id, EntityListInterface $entity_list, $selected, array &$context) {
    $row = [];
    $entity = $this->loadEntity($entity_list->getTargetEntityTypeId(), $entity_id);
    foreach ($entity_list->getFields() as $field_id => $field) {
      $field_entity = $entity_list->getHandler()->getFieldEntity($entity, $field);
      if (!$field_entity) {
        $row[$field_id] = '';
        continue;
      }
      /** @var \Drupal\exo_list_builder\Plugin\ExoListElementInterface $instance */
      $instance = $this->elementManager->createInstance($field['view']['type'], $field['view']['settings']);
      $row[$field_id] = $instance->buildPlainView($field_entity, $field);
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function overview(array $context) {
    if (!empty($context['job_finish'])) {
      return [
        '#type' => 'link',
        '#title' => $this->t('Download CSV'),
        '#url' => \Drupal::service('file_url_generator')->generate($context['results']['csv_file_uri']),
      ];
    }
    return parent::overview($context);
  }

}
