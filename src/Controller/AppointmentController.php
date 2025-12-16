<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Form\AppointmentType;
use App\Repository\ServicesRepository;
use App\Repository\AppointmentRepository;
use App\Repository\StaffProfileRepository;
use App\Repository\UserRepository;
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
        AppointmentRepository $appointmentRepository,
        StaffProfileRepository $staffProfileRepository,
        UserRepository $userRepository
    ): Response {
        $appointment = new Appointment();

        // Optional preselected staff via query (staff is stored as User on Appointment)
        $staffId = $request->query->get('staff');
        if ($staffId) {
            // Try to resolve as StaffProfile id first, otherwise try User id
            $staffProfile = $staffProfileRepository->find($staffId);
            if ($staffProfile && method_exists($staffProfile, 'getUser') && $staffProfile->getUser()) {
                $appointment->setStaff($staffProfile->getUser());
            } else {
                $maybeUser = $userRepository->find($staffId);
                if ($maybeUser) {
                    $appointment->setStaff($maybeUser);
                }
            }
        }

        // Optional preselected service via query
        $serviceId = $request->query->get('service');
        if ($serviceId) {
            $service = $servicesRepository->find($serviceId);
            if ($service) {
                $appointment->setService($service);
            }
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        // Find available staff users for the form; prefer StaffProfile lookup but fall back to users with ROLE_STAFF
        $staffProfiles = $staffProfileRepository->findAll();
        if (count($staffProfiles) > 0) {
            // convert to User list
            $staffs = array_map(function($sp) { return $sp->getUser(); }, $staffProfiles);
        } else {
            $staffs = $userRepository->createQueryBuilder('u')
                ->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%ROLE_STAFF%')
                ->orderBy('u.username', 'ASC')
                ->getQuery()
                ->getResult();
        }
        $noStaff = (count($staffs) === 0);

        // If form contains an unmapped 'staff' field, map that selection onto the appointment
        if ($form->isSubmitted() && $form->has('staff')) {
            $selected = $form->get('staff')->getData();
            // If the form returned a User instance (preferred), set directly
            if ($selected && $selected instanceof \App\Entity\User) {
                $appointment->setStaff($selected);
            } elseif ($selected && method_exists($selected, 'getUser') && $selected->getUser()) {
                // If legacy StaffProfile was returned, extract its User
                $appointment->setStaff($selected->getUser());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // If no staff exist at all, do not allow booking — surface error to user
            if ($noStaff) {
                $form->addError(new FormError('No staff are available right now. Please contact support or try again later.'));
            } else {
                $date = $appointment->getAppointmentDate();
                $time = $appointment->getAppointmentTime();

                // Only enforce slot uniqueness when the appointment has a specific staff assigned
                if ($appointment->getStaff()) {
                    $existing = $appointmentRepository->createQueryBuilder('a')
                        ->andWhere('a.appointmentDate = :date')
                        ->andWhere('a.appointmentTime = :time')
                        ->andWhere('a.id != :id')
                        ->setParameter('date', $date->format('Y-m-d'))
                        ->setParameter('time', $time->format('H:i:s'))
                        ->setParameter('id', $appointment->getId() ?: 0)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if ($existing) {
                        $form->get('appointmentTime')->addError(new FormError('This time slot is already booked for the selected staff.'));
                    } else {
                        // proceed to persist
                        $hour = (int)$time->format('H');
                        $minute = (int)$time->format('i');

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
                } else {
                    // No staff assigned: allow booking into the pool so staff may claim later
                    $hour = (int)$time->format('H');
                    $minute = (int)$time->format('i');

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
        }

        return $this->render('appointment/new.html.twig', [
            'form' => $form->createView(),
            'services' => $servicesRepository->findAll(),
            'staffs' => $staffs,
            'noStaff' => $noStaff,
        ]);
    }
}
