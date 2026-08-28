<?php

namespace Drupal\media_folders\Controller;

use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides functionality for managing cookies.
 *
 * This controller handles setting cookies and provides both standard and AJAX
 * methods for cookie management.
 */
class CookieController extends ControllerBase {

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * Sets a cookie and redirects to a destination.
   *
   * @param string|null $name
   *   The name of the cookie to set.
   * @param string|bool $value
   *   The value of the cookie to set. Defaults to FALSE.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the specified destination.
   */
  public function setCookie($name = NULL, $value = FALSE) : RedirectResponse {
    $destination = $this->requestStack->getCurrentRequest()->query->get('destination');

    $response = new Response();
    $cookie = Cookie::create($name, $value, 0, '/', NULL, FALSE);
    $response->headers->setCookie($cookie);
    $response->send();

    return new RedirectResponse($destination);
  }

  /**
   * Sets a cookie via an AJAX request.
   *
   * @param string|null $name
   *   The name of the cookie to set.
   * @param string|bool $value
   *   The value of the cookie to set. Defaults to FALSE.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the cookie name and value.
   */
  public function setCookieAjax($name = NULL, $value = FALSE) : JsonResponse {
    $response = new JsonResponse(['name' => $name, 'value' => $value]);
    $cookie = Cookie::create($name, $value, 0, '/', NULL, FALSE);
    $response->headers->setCookie($cookie);
    Cache::invalidateTags(['media_folders_page']);

    return $response;
  }

}
