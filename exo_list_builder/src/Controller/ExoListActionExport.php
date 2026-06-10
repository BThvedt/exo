<?php

namespace Drupal\exo_list_builder\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\exo_list_builder\EntityListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Define the entity export csv download controller.
 */
class ExoListActionExport implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The request.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The csrf token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The entity export csv download constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The csrf token generator.
   */
  public function __construct(RequestStack $request_stack, CsrfTokenGenerator $csrf_token) {
    $this->request = $request_stack->getCurrentRequest();
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('csrf_token')
    );
  }

  /**
   * Download exo list action export file.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function download(EntityListInterface $exo_entity_list) {
    $token = $this->getRequest()->query->get('token');
    $file_uri = $this->getRequest()->query->get('file');
    if (empty($token) || !$this->csrfToken->validate($token, $file_uri)) {
      throw new AccessDeniedHttpException();
    }
    if (!isset($file_uri) || !file_exists($file_uri)) {
      throw new NotFoundHttpException(
        $this->t('Missing or not found entity content exported file.')
      );
    }
    // Do not delete the file after sending. The auto-download behavior
    // (data-auto-download) triggers one download and the visible "Download"
    // link may be clicked as well; deleting after the first send would 404 any
    // subsequent attempt. Temporary managed files are cleaned up by cron.
    return (new BinaryFileResponse($file_uri))
      ->setContentDisposition('attachment', basename($file_uri));
  }

  /**
   * Get current request object.
   *
   * @return null|\Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  protected function getRequest() {
    return $this->request;
  }

}
