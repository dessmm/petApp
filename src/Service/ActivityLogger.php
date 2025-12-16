<?php

namespace App\Service;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogger
{
    private $em;
    private $security;
    private $requestStack;

    public function __construct(
        EntityManagerInterface $em, 
        Security $security, 
        RequestStack $requestStack)
    {
        $this->em = $em;
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    public function logActivity(string $action, string $targetData): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        
        $log = new ActivityLog();
        // Required fields
        $log->setTargetData($targetData);
        $log->setAction($action);
        $log->setCreatedAt(new \DateTime());

        // IP address: fallback to 'unknown' when not available
        $ip = $request?->getClientIp() ?? 'unknown';
        $log->setIpAddress($ip);

        if ($user) {
            $log->setUserId($user);
            // Prefer getUserIdentifier(), fallback to string cast
            $username = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : (string) $user;
            $log->setUsername($username ?: 'unknown');
            $roles = $user->getRoles() ?: ['ROLE_USER'];
            $log->setRole(implode(',', $roles));
        } else {
            // Ensure non-null DB columns are satisfied
            $log->setUsername('system');
            $log->setRole('SYSTEM');
        }

        try {
            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable $e) {
            // Don't break the main flow if logging fails; record to PHP log for investigation
            error_log('[ActivityLogger] Failed to persist activity log: ' . $e->getMessage());
        }
    }
}