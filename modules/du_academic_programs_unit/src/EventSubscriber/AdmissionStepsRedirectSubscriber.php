<?php

namespace Drupal\du_academic_programs_unit\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class AdmissionStepsRedirectSubscriber.
 *
 * @package Drupal\du_academic_programs_unit
 */
class AdmissionStepsRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return([
      KernelEvents::REQUEST => [
        ['redirectAdmissionSteps'],
      ],
    ]);
  }

  /**
   * Redirect requests for admission_steps node to the same node on the core site.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The response event.
   */
  public function redirectAdmissionSteps(RequestEvent $event) {
    $request = $event->getRequest();

    // This is necessary because this also gets called on
    // node sub-tabs such as "edit", "revisions", etc.  This
    // prevents those pages from redirected.
    if ($request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }

    // Only redirect a certain content type.
    if ($request->attributes->get('node')->getType() !== 'admission_steps') {
      return;
    }

    // This is where you set the destination.
    $redirect_url = 'https://www.du.edu/node-uuid/' . $request->attributes->get('node')->uuid();
    $response = new TrustedRedirectResponse($redirect_url, 301);
    $event->setResponse($response);
  }

}
