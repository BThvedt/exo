<?php

namespace Drupal\exo_toolbar\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ExoToolbarAdminController.
 */
class ExoToolbarAdminController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ExoToolbarAdminController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Updateitems.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A json response to use via javascript.
   */
  public function updateItems(?Request $request = NULL) {
    $response = [];
    $content = $request->getContent();
    if (!empty($content)) {
      $storage = $this->entityTypeManager->getStorage('exo_toolbar_item');
      $data = json_decode($content, TRUE);
      foreach ($storage->loadMultiple(array_keys($data)) as $id => $item) {
        /** @var \Drupal\exo_toolbar\Entity\ExoToolbarItemInterface $item */
        $item_data = $data[$id];
        foreach (['region', 'section', 'weight'] as $property) {
          if (isset($item_data[$property])) {
            $item->set($property, $item_data[$property]);
          }
        }
        $item->save();
      }
      $response['status'] = 'success';
    }
    else {
      $response['status'] = 'error';
      $response['message'] = $this->t('No POST information was found in request.');
    }
    return new JsonResponse($response);
  }

}
