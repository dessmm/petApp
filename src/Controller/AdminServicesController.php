<?php

namespace App\Controller;

use App\Entity\Services;
use App\Service\ActivityLogger;
use App\Form\ServicesType;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/services')]
final class AdminServicesController extends AbstractController
{
    #[Route(name: 'app_admin_services_index', methods: ['GET'])]
    public function index(ServicesRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin_dashboard/admin_services/index.html.twig', [
            'services' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_services_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $service = new Services();
        $form = $this->createForm(ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($service);
            $em->flush();
            try {
                $user = $this->getUser();
                $actor = $user ? (method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : (string)$user) : 'system';
                $svcName = $service->getName() ?: ('ID ' . $service->getId());
                $activityLogger->logActivity('Service Created (Admin)', 'Service "' . $svcName . '" created by ' . $actor);
            } catch (\Throwable $e) {}
            $this->addFlash('success', 'Service created successfully!');
            return $this->redirectToRoute('app_admin_services_index');
        }

        return $this->render('admin_dashboard/admin_services/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_services_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Services $service, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $form = $this->createForm(ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            try {
                $user = $this->getUser();
                $actor = $user ? (method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : (string)$user) : 'system';
                $svcName = $service->getName() ?: ('ID ' . $service->getId());
                $activityLogger->logActivity('Service Updated (Admin)', 'Service "' . $svcName . '" updated by ' . $actor);
            } catch (\Throwable $e) {}
            $this->addFlash('success', 'Service updated successfully!');
            return $this->redirectToRoute('app_admin_services_index');
        }

        return $this->render('admin_dashboard/admin_services/edit.html.twig', [
            'form' => $form,
            'service' => $service,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_services_delete', methods: ['POST'])]
    public function delete(Request $request, Services $service, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        // Prevent deletion if appointments reference this service
        $appointmentRepo = $em->getRepository(\App\Entity\Appointment::class);
        $dependentCount = null;
        try {
            $dependentCount = (int) $appointmentRepo->count(['service' => $service]);
        } catch (\Throwable $e) {
            try {
                $dependentCount = (int) $appointmentRepo->createQueryBuilder('a')
                    ->select('count(a.id)')
                    ->andWhere('a.service = :s')
                    ->setParameter('s', $service)
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (\Throwable $e) {
                $dependentCount = null;
            }
        }

        if ($dependentCount === null) {
            $this->addFlash('error', 'Unable to verify whether this service is used by any appointments. Delete aborted for safety. Check logs for details.');
            return $this->redirectToRoute('app_admin_services_index');
        }

        // If we couldn't determine dependency count we abort; otherwise allow deletion
        if ($dependentCount === null) {
            $this->addFlash('error', 'Unable to verify whether this service is used by any appointments. Delete aborted for safety. Check logs for details.');
            return $this->redirectToRoute('app_admin_services_index');
        }

        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            // If appointments reference this service and DB not migrated, clear relation first
            if ($dependentCount > 0) {
                try {
                    $appointments = $appointmentRepo->findBy(['service' => $service]);
                    foreach ($appointments as $appt) {
                        $appt->setService(null);
                        $em->persist($appt);
                    }
                    $em->flush();
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Failed to clear dependent appointments. Delete aborted.');
                    return $this->redirectToRoute('app_admin_services_index');
                }
            }

            $em->remove($service);
            $em->flush();
            try {
                $user = $this->getUser();
                $actor = $user ? (method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : (string)$user) : 'system';
                $svcName = $service->getName() ?: ('ID ' . $service->getId());
                $activityLogger->logActivity('Service Deleted (Admin)', 'Service "' . $svcName . '" deleted by ' . $actor);
            } catch (\Throwable $e) {}
            $this->addFlash('success', 'Service deleted successfully!');
        }

        return $this->redirectToRoute('app_admin_services_index');
    }

    #[Route('/{id}/show', name: 'app_admin_services_show', methods: ['GET'])]
    public function show(Services $service): Response
    {
    return $this->render('admin_dashboard/admin_services/show.html.twig', [
        'service' => $service,
    ]);
}

}
