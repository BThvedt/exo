<?php

namespace Drupal\exo_site_settings;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the config page type entity.
 *
 * @see \Drupal\exo_site_settings\Entity\SiteSettingsType.
 */
class SiteSettingsTypeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\exo_site_settings\Entity\SiteSettingsTypeInterface $entity */

    if ($operation == 'page_update') {
      return AccessResult::allowedIfHasPermission($account, 'edit config pages')->orIf(AccessResult::allowedIfHasPermission($account, 'edit ' . $entity->id() . ' config page'));
    }

    // Unknown operation, no opinion.
    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $access = AccessResult::allowedIfHasPermission($account, 'edit config pages');
    if ($entity_bundle) {
      $access->orIf(AccessResult::allowedIfHasPermission($account, 'edit ' . $entity_bundle . ' config page'));
    }
    return $access;
  }

}
