<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\FormError;

#[Route('/appointment')]
final class AppointmentController extends AbstractController
{
    #[Route('/', name: 'app_appointment_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $appointments = $entityManager->getRepository(Appointment::class)->findAll();

        return $this->render('appointment/index.html.twig', [
            'appointments' => $appointments,
        ]);
    }

    #[Route('/new', name: 'app_appointment_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ServicesRepository $servicesRepository,
        AppointmentRepository $appointmentRepository
    ): Response {
        $appointment = new Appointment();
        $serviceId = $request->query->get('service');
        if ($serviceId) {
            $service = $servicesRepository->find($serviceId);
            if ($service) {
                $appointment->setService($service);
            }
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date = $appointment->getAppointmentDate();
            $time = $appointment->getAppointmentTime();
            $existing = $appointmentRepository->createQueryBuilder('a')
                ->andWhere('a.appointmentDate = :date')
                ->andWhere('a.appointmentTime = :time')
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('time', $time->format('H:i:s'))
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing) {
                $form->get('appointmentTime')->addError(new FormError('Selected date and time are already booked. Please choose another slot.'));
            } else {
                $hour = (int) $time->format('H');
                $minute = (int) $time->format('i');
                if ($hour < 9 || $hour > 16) {
                    $form->get('appointmentTime')->addError(new FormError('Please choose a time between 09:00 and 16:00.'));
                } elseif ($minute !== 0) {
                    $form->get('appointmentTime')->addError(new FormError('Please select an exact hour (minutes must be 00).'));
                } else {
                    $entityManager->persist($appointment);
                    $entityManager->flush();

                    $this->addFlash('success', 'Appointment created successfully.');

                    return $this->redirectToRoute('app_appointment_index');
                }
            }
        }

        return $this->render('appointment/new.html.twig', [
            'form' => $form->createView(),
            'services' => $servicesRepository->findAll(), 
        ]);
    }

    #[Route('/{id}/edit', name: 'app_appointment_edit')]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ServicesRepository $servicesRepository,
        AppointmentRepository $appointmentRepository
    ): Response {
        $appointment = $entityManager->getRepository(Appointment::class)->find($id);

        if (!$appointment) {
            throw $this->createNotFoundException('Appointment not found.');
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $date = $appointment->getAppointmentDate();
            $time = $appointment->getAppointmentTime();
            $qb = $appointmentRepository->createQueryBuilder('a')
                ->andWhere('a.appointmentDate = :date')
                ->andWhere('a.appointmentTime = :time')
                ->andWhere('a.id != :id')
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('time', $time->format('H:i:s'))
                ->setParameter('id', $appointment->getId())
                ->getQuery();

            $existing = $qb->getOneOrNullResult();
            if ($existing) {
                $form->get('appointmentTime')->addError(new FormError('Selected date and time are already booked by another appointment.'));
            } else {
                $hour = (int) $appointment->getAppointmentTime()->format('H');
                $minute = (int) $appointment->getAppointmentTime()->format('i');
                if ($hour < 9 || $hour > 16) {
                    $form->get('appointmentTime')->addError(new FormError('Please choose a time between 09:00 and 16:00.'));
                } elseif ($minute !== 0) {
                    $form->get('appointmentTime')->addError(new FormError('Please select an exact hour (minutes must be 00).'));
                } else {
                    $entityManager->flush();
                    $this->addFlash('success', 'Appointment updated successfully.');
                    return $this->redirectToRoute('app_appointment_index');
                }
            }
        }

        return $this->render('appointment/edit.html.twig', [
            'form' => $form->createView(),
            'services' => $servicesRepository->findAll(), 
        ]);
    }

    #[Route('/{id}/delete', name: 'app_appointment_delete', methods: ['POST', 'GET'])]
    public function delete(Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($appointment);
        $entityManager->flush();

        $this->addFlash('success', 'Appointment deleted successfully.');

        return $this->redirectToRoute('app_appointment_index');
    }
    
    #[Route('/{id}', name: 'app_appointment_show', methods: ['GET'])]
    public function show(Appointment $appointment): Response {
        return $this->render('appointment/show.html.twig', [
            'appointment' => $appointment,
        ]);
    }


}
