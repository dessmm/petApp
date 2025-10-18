<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\ServicesRepository;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\FormError;

#[Route('/appointment')]
final class AppointmentController extends AbstractController
{
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
                $form->get('appointmentTime')->addError(new FormError('This time slot is already booked.'));
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

                    $this->addFlash('success', 'Your appointment has been booked successfully!');

                    return $this->redirectToRoute('app_home');
                }
            }
        }

        return $this->render('appointment/new.html.twig', [
            'form' => $form->createView(),
            'services' => $servicesRepository->findAll(),
        ]);
    }
}
