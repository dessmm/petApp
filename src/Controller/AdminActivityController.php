<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/activity')]
final class AdminActivityController extends AbstractController
{
    #[Route('/', name: 'app_admin_activity_index', methods: ['GET'])]
    public function index(ActivityLogRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $logs = $repo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin_dashboard/activity/index.html.twig', [
            'logs' => $logs,
        ]);
    }
}
