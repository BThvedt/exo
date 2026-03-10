<?php

namespace Drupal\exo_asset\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\exo_asset\Entity\ExoAssetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;

/**
 * Class ExoAssetController.
 *
 *  Returns responses for Asset routes.
 */
class ExoAssetController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a ExoAssetController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter')
    );
  }

  /**
   * Displays a Asset  revision.
   *
   * @param int $exo_asset_revision
   *   The Asset  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($exo_asset_revision) {
    // I guess the $storage variable needs to be typed or I get warnings with upgrade status
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage('exo_asset');
    $exo_asset = $storage->loadRevision($exo_asset_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('exo_asset');
    return $view_builder->view($exo_asset);
  }

  /**
   * Page title callback for a Asset  revision.
   *
   * @param int $exo_asset_revision
   *   The Asset  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($exo_asset_revision) {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage('exo_asset');
    $exo_asset = $storage->loadRevision($exo_asset_revision);
    return $this->t('Revision of %title from %date', ['%title' => $exo_asset->label(), '%date' => $this->dateFormatter->format($exo_asset->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Asset .
   *
   * @param \Drupal\exo_asset\Entity\ExoAssetInterface $exo_asset
   *   A Asset  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(ExoAssetInterface $exo_asset) {
    $account = $this->currentUser();
    $langcode = $exo_asset->language()->getId();
    $langname = $exo_asset->language()->getName();
    $languages = $exo_asset->getTranslationLanguages();
    $has_translations = (count($languages) > 1);

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $exo_asset_storage */
    $exo_asset_storage = $this->entityTypeManager()->getStorage('exo_asset');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $exo_asset->label()]) : $this->t('Revisions for %title', ['%title' => $exo_asset->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all asset revisions") || $account->hasPermission('administer asset entities')));
    $delete_permission = (($account->hasPermission("delete all asset revisions") || $account->hasPermission('administer asset entities')));

    $rows = [];

    $vids = $exo_asset_storage->revisionIds($exo_asset);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\exo_asset\ExoAssetInterface $revision */
      $revision = $exo_asset_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $exo_asset->getRevisionId()) {
          $link = Link::fromTextAndUrl($date, new Url('entity.exo_asset.revision', ['exo_asset' => $exo_asset->id(), 'exo_asset_revision' => $vid]));
        }
        else {
          $link = $exo_asset->toLink($date)->toString();
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')->renderPlain($username),
              'message' => ['#markup' => $revision->getRevisionLogMessage(), '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.exo_asset.translation_revert', [
                'exo_asset' => $exo_asset->id(),
                'exo_asset_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.exo_asset.revision_revert', [
                'exo_asset' => $exo_asset->id(),
                'exo_asset_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.exo_asset.revision_delete', ['exo_asset' => $exo_asset->id(), 'exo_asset_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['exo_asset_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
