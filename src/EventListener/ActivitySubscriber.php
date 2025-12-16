<?php

namespace App\EventListener;

use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivitySubscriber implements EventSubscriberInterface
{
    private ActivityLogger $activityLogger;
    private RequestStack $requestStack;

    public function __construct(ActivityLogger $activityLogger, RequestStack $requestStack)
    {
        $this->activityLogger = $activityLogger;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Newer Symfony login event
            LoginSuccessEvent::class => 'onLoginSuccess',
            // Fallback for older InteractiveLoginEvent
            InteractiveLoginEvent::class => 'onInteractiveLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // If another login handler already logged this request, skip
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->attributes->get('_activity_login_logged')) {
            return;
        }

        $token = $event->getAuthenticatedToken();
        $user = $token?->getUser();
        $username = null;
        if (is_object($user)) {
            if (method_exists($user, 'getUserIdentifier')) {
                $username = $user->getUserIdentifier();
            } else {
                // Fallback: cast user to string if possible
                $username = (string) $user;
            }
        }

        // Mark this request as logged to prevent duplicates
        if ($request) {
            $request->attributes->set('_activity_login_logged', true);
        }

        $this->activityLogger->logActivity('Login', (string) $username);
    }

    // Compatibility for older interactive login event
    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        // If this request already has a login record, skip; otherwise mark and log
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->attributes->get('_activity_login_logged')) {
            return;
        }

        $user = $event->getAuthenticationToken()?->getUser();
        $username = null;
        if (is_object($user)) {
            if (method_exists($user, 'getUserIdentifier')) {
                $username = $user->getUserIdentifier();
            } else {
                $username = (string) $user;
            }
        }

        if ($request) {
            $request->attributes->set('_activity_login_logged', true);
        }

        $this->activityLogger->logActivity('Login', (string) $username);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        $username = null;
        if (is_object($user)) {
            if (method_exists($user, 'getUserIdentifier')) {
                $username = $user->getUserIdentifier();
            } else {
                $username = (string) $user;
            }
        }

        $this->activityLogger->logActivity('Logout', (string) $username);
    }
}
