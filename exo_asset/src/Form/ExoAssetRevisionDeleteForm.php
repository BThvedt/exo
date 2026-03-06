<?php

namespace Drupal\exo_asset\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;

/**
 * Provides a form for deleting a Asset revision.
 *
 * @ingroup exo_asset
 */
class ExoAssetRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Asset revision.
   *
   * @var \Drupal\exo_asset\Entity\ExoAssetInterface
   */
  protected $revision;

  /**
   * The Asset storage.
   *
   * @var \Drupal\Core\Entity\RevisionableStorageInterface
   */
  protected $exoAssetStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new ExoAssetRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(RevisionableStorageInterface $entity_storage, Connection $connection, DateFormatterInterface $date_formatter) {
    $this->exoAssetStorage = $entity_storage;
    $this->connection = $connection;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('exo_asset'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exo_asset_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.exo_asset.version_history', ['exo_asset' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $exo_asset_revision = NULL) {
    $this->revision = $this->exoAssetStorage->loadRevision($exo_asset_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->exoAssetStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Asset: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    \Drupal::messenger()->addMessage(t('Revision from %revision-date of Asset %title has been deleted.', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.exo_asset.edit_form',
       ['exo_asset' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {exo_asset_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.exo_asset.version_history',
         ['exo_asset' => $this->revision->id()]
      );
    }
  }

}
