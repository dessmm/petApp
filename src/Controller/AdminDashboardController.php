<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Repository\ServicesRepository;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\FormError;

#[Route('/admin')]
final class AdminDashboardController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(
        AppointmentRepository $appointmentRepository,
        ServicesRepository $servicesRepository,
        ContactRepository $contactRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // ✅ Fetch summary stats
        $totalAppointments = $appointmentRepository->count([]);
        $totalServices = $servicesRepository->count([]);
        $totalContacts = $contactRepository->count([]);
        $totalUsers = $userRepository->count([]);
        // Count users with ROLE_STAFF in their roles field (assumes roles stored as JSON/array)
        $totalStaff = count($userRepository->createQueryBuilder('u')
            ->andWhere("u.roles LIKE :role")
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getResult());
        $newMessages = $contactRepository->count(['status' => 'New']);

        // ✅ Get recent items
        $recentAppointments = $appointmentRepository->findBy([], ['appointmentDate' => 'DESC'], 5);
        $recentMessages = $contactRepository->findBy([], ['submittedAt' => 'DESC'], 5);
        $recentActivities = $activityLogRepository->findBy([], ['createdAt' => 'DESC'], 10);

        return $this->render('admin_dashboard/dashboard.html.twig', [
            'totalAppointments' => $totalAppointments,
            'totalServices' => $totalServices,
            'totalContacts' => $totalContacts,
            'newMessages' => $newMessages,
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'recentActivities' => $recentActivities,
            'recentAppointments' => $recentAppointments,
            'recentMessages' => $recentMessages, // ✅ added for dashboard display
        ]);
    }

    #[Route('/appointments', name: 'app_admin_appointments_index')]
    public function appointmentsIndex(AppointmentRepository $appointmentRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $appointments = $appointmentRepository->findBy([], ['appointmentDate' => 'DESC']);
        return $this->render('admin_dashboard/appointment/index.html.twig', [
            'appointments' => $appointments,
        ]);
    }

    #[Route('/appointments/{id}', name: 'app_admin_appointments_show', methods: ['GET'])]
    public function appointmentShow(Appointment $appointment): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin_dashboard/appointment/show.html.twig', [
            'appointment' => $appointment,
        ]);
    }

    #[Route('/appointments/{id}/edit', name: 'app_admin_appointments_edit', methods: ['GET', 'POST'])]
    public function appointmentEdit(
        int $id,
        Request $request,
        AppointmentRepository $appointmentRepository,
        EntityManagerInterface $entityManager,
        ServicesRepository $servicesRepository,
        \App\Service\ActivityLogger $activityLogger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $appointment = $appointmentRepository->find($id);

        if (!$appointment) {
            throw $this->createNotFoundException('Appointment not found.');
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date = $appointment->getAppointmentDate();
            $time = $appointment->getAppointmentTime();

            $existing = $appointmentRepository->createQueryBuilder('a')
                ->andWhere('a.appointmentDate = :date')
                ->andWhere('a.appointmentTime = :time')
                ->andWhere('a.id != :id')
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('time', $time->format('H:i:s'))
                ->setParameter('id', $appointment->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing) {
                $form->get('appointmentTime')->addError(new FormError('This time slot is already booked.'));
            } else {
                // detect status change for activity logging
                try {
                    $beforeStatus = $beforeStatus ?? null;
                    $afterStatus = $appointment->getStatus();
                    $entityManager->flush();
                    $this->addFlash('success', 'Appointment updated successfully.');

                    // log update and status change if any
                    $actor = $this->getUser();
                    $actorName = is_object($actor) && method_exists($actor, 'getUserIdentifier') ? $actor->getUserIdentifier() : 'system';
                    $identifier = $appointment->getPetName() ?: ('ID ' . $appointment->getId());
                    $activityLogger->logActivity('Appointment Updated', 'Appointment "' . $identifier . '" updated by ' . $actorName);
                    if ($beforeStatus !== null && $beforeStatus !== $afterStatus) {
                        $activityLogger->logActivity('Appointment Status Changed', 'Appointment "' . $identifier . '" status changed from ' . ($beforeStatus ?: 'unknown') . ' to ' . ($afterStatus ?: 'unknown') . ' by ' . $actorName);
                    }

                    return $this->redirectToRoute('app_admin_appointments_index');
                } catch (\Throwable $e) {
                    // if flush fails, fall through to render with error
                    error_log('[AdminDashboardController] Exception updating appointment: ' . $e->getMessage());
                    $form->addError(new FormError('There was a problem updating the appointment.'));
                }
            }
        }

        return $this->render('admin_dashboard/appointment/edit.html.twig', [
            'form' => $form->createView(),
            'appointment' => $appointment,
            'services' => $servicesRepository->findAll(),
        ]);
    }

    #[Route('/appointments/{id}/delete', name: 'app_admin_appointments_delete', methods: ['POST', 'GET'])]
    public function appointmentDelete(Appointment $appointment, EntityManagerInterface $entityManager, \App\Service\ActivityLogger $activityLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $identifier = $appointment->getPetName() ?: ('ID ' . $appointment->getId());
        $actor = $this->getUser();
        $actorName = is_object($actor) && method_exists($actor, 'getUserIdentifier') ? $actor->getUserIdentifier() : 'system';

        $entityManager->remove($appointment);
        $entityManager->flush();

        try {
            $activityLogger->logActivity('Appointment Deleted', 'Appointment "' . $identifier . '" deleted by ' . $actorName);
        } catch (\Throwable $e) {}

        $this->addFlash('success', 'Appointment deleted successfully.');
        return $this->redirectToRoute('app_admin_appointments_index');
    }
}
