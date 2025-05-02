<?php

namespace Drupal\exo_image\Controller;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\image\Controller\ImageStyleDownloadController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\exo_image\ExoImageStyleManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Simple extension over the default image download controller.
 *
 * We inherit from it so we have all functions and logic available. We just
 * override the way the image is generated to suit the needs of the dynamically
 * generated image styles.
 *
 * Images are scaled by default but cropping can be activated on the formatter
 * settings form.
 * When cropping is not activated a height of 0 is passed to the Controller.
 */
class ExoImageController extends ImageStyleDownloadController {

  /**
   * The exo image style manager.
   *
   * @var \Drupal\exo_image\ExoImageStyleManagerInterface
   */
  protected $exoImageStyleManager;

  /**
   * Constructs a ImageStyleDownloadController object.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The system service.
   * @param \Drupal\exo_image\ExoImageStyleManagerInterface $exo_image_style_manager
   *   The exo image style manager.
   */
  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory, StreamWrapperManagerInterface $stream_wrapper_manager, FileSystemInterface $file_system, ExoImageStyleManagerInterface $exo_image_style_manager) {
    parent::__construct($lock, $image_factory, $stream_wrapper_manager, $file_system);
    $this->exoImageStyleManager = $exo_image_style_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lock'),
      $container->get('image.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_system'),
      $container->get('exo_image.style.manager')
    );
  }

  /**
   * Generates a derivative, given a style and image path.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   * @param string $required_derivative_scheme
   *   The required scheme for the derivative image.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file request is invalid.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   *   Thrown when the file is still being generated.
   */
  public function deliver(Request $request, $scheme, ImageStyleInterface $image_style, ?string $required_derivative_scheme = NULL) {
    $response = parent::deliver($request, $scheme, $image_style, $required_derivative_scheme);
    if ($response->getStatusCode() === 200) {
      $file = $response->getFile();
      $uri = $file->getPath() . '/' . $file->getBasename();
      $pathInfo = pathinfo($request->getRequestUri());
      if ($pathInfo['extension'] === 'webp' && ($webp = $this->exoImageStyleManager->createWebpCopy($uri))) {
        return $this->webpResponse($webp, $response->headers->all(), $scheme);
      }
    }
    return $response;
  }

  /**
   * Deliver an image from the requested parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $width
   *   The requested width in pixels that came from the JS.
   * @param int $height
   *   The requested height in pixels that came from the JS.
   * @param int $fid
   *   The file id to render.
   * @param string $filename
   *   The filename, only here for SEO purposes.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   *   Thrown when the file is still being generated.
   */
  public function image(Request $request, $width, $height, $fid, $filename) {
    // Bail out if the image is not valid.
    $file = File::load($fid);
    $image = $this->imageFactory->get($file->getFileUri());
    if (!$image->isValid()) {
      return new Response($this->t('Error generating image, invalid file.'), 500);
    }

    // Bail out if the arguments are not numbers.
    if (!is_numeric($width) || !is_numeric($height) || !is_numeric($fid)) {
      $error_msg = $this->t('Error generating image, invalid parameters.');
    }

    // Try and find a matching image style.
    $image_style = $this->exoImageStyleManager->findImageStyle($width, $height);
    if (empty($image_style)) {
      $error_msg = $this->t('Could not find matching image style.');
    }

    // Variable translation to make the original imageStyle deliver method work.
    $image_uri = explode('://', $file->getFileUri());
    $scheme = $image_uri[0];

    $request->query->set('file', $image_uri[1]);

    if (!empty($image_style)) {
      // Because exo image does not use itok, we simulate it.
      if (!$this->config('image.settings')->get('allow_insecure_derivatives')) {
        $image_uri = $image_uri[0] . '://' . $image_uri[1];
        $request->query->set(IMAGE_DERIVATIVE_TOKEN, $image_style->getPathToken($image_uri));
      }

      // Uncomment to test the loading effect:
      // usleep(1000000);.
      return $this->deliver($request, $scheme, $image_style);
    }

    return new Response($error_msg, 500);
  }

  /**
   * Returns a WebP image as response.
   *
   * @param string $file
   *   Path to image file.
   * @param array $headers
   *   Response headers.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The transferred file as response.
   */
  protected function webpResponse($file, array $headers, $scheme) {
    $headers += [
      'Content-Type' => 'image/webp',
      'Content-Length' => filesize($file),
    ];
    // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
    // sets response as not cacheable if the Cache-Control header is not
    // already modified. We pass in FALSE for non-private schemes for the
    // $public parameter to make sure we don't change the headers.
    return new BinaryFileResponse($file, 200, $headers, $scheme !== 'private');
  }

}
