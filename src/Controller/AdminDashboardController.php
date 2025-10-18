<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Repository\ServicesRepository;
use App\Repository\ContactRepository;
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
        ContactRepository $contactRepository
    ): Response {
        $totalAppointments = $appointmentRepository->count([]);
        $totalServices = $servicesRepository->count([]);
        $totalContacts = $contactRepository->count([]);
        $newMessages = $contactRepository->count(['status' => 'New']);

        $recentAppointments = $appointmentRepository->findBy([], ['appointmentDate' => 'DESC'], 5);

        return $this->render('admin_dashboard/dashboard.html.twig', [
            'totalAppointments' => $totalAppointments,
            'totalServices' => $totalServices,
            'totalContacts' => $totalContacts,
            'newMessages' => $newMessages,
            'recentAppointments' => $recentAppointments,
        ]);
    }


    #[Route('/appointments', name: 'app_admin_appointments_index')]
    public function appointmentsIndex(AppointmentRepository $appointmentRepository): Response
    {
        $appointments = $appointmentRepository->findBy([], ['appointmentDate' => 'DESC']);
        return $this->render('admin_dashboard/appointment/index.html.twig', [
            'appointments' => $appointments,
        ]);
    }

    #[Route('/appointments/{id}', name: 'app_admin_appointments_show', methods: ['GET'])]
    public function appointmentShow(Appointment $appointment): Response
    {
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
        ServicesRepository $servicesRepository
    ): Response {
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
                $entityManager->flush();
                $this->addFlash('success', 'Appointment updated successfully.');
                return $this->redirectToRoute('app_admin_appointments_index');
            }
        }

        return $this->render('admin_dashboard/appointment/edit.html.twig', [
            'form' => $form->createView(),
            'appointment' => $appointment,
            'services' => $servicesRepository->findAll(),
        ]);
    }

    #[Route('/appointments/{id}/delete', name: 'app_admin_appointments_delete', methods: ['POST', 'GET'])]
    public function appointmentDelete(Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($appointment);
        $entityManager->flush();

        $this->addFlash('success', 'Appointment deleted successfully.');
        return $this->redirectToRoute('app_admin_appointments_index');
    }
        
}
