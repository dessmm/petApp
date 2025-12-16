<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\StaffProfile;
use App\Entity\Services;
use App\Entity\User;
use App\Service\ActivityLogger;
use App\Repository\AppointmentRepository;
use Symfony\Component\Form\FormError;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface as ORMEntityManagerInterface;
use App\Form\ChangePasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;

#[Route('/staff')]
class StaffController extends AbstractController
{
    private function ensureStaff(): void
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
    }
    // Dashboard
    #[Route('/', name: 'staff_dashboard')]
    public function index(AppointmentRepository $appointmentRepo, EntityManagerInterface $em): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        $staffProfile = null;
        if ($user instanceof User) {
            $staffProfile = $user->getStaffProfile();
            if (!$staffProfile) {
                $staffProfile = new StaffProfile();
                $staffProfile->setUser($user);
                $em->persist($staffProfile);
                $em->flush();
            }
        }

        $today = new \DateTime('today');

        $todaysAppointments = [];
        $pending = 0;
        $accepted = 0;
        $upcomingCount = 0;
        $availablePending = 0;

        if ($staffProfile instanceof StaffProfile && $user instanceof User) {
            // Appointments are related to the User entity (not StaffProfile), so query by User
            $todaysAppointments = $appointmentRepo->createQueryBuilder('a')
                ->where('a.staff = :staff')
                ->andWhere('a.appointmentDate = :today')
                ->setParameter('staff', $user)
                ->setParameter('today', $today->format('Y-m-d'))
                ->orderBy('a.appointmentTime', 'ASC')
                ->getQuery()
                ->getResult();
            // Count assigned pending appointments for this staff
            $assignedPending = $appointmentRepo->count([
                'staff' => $user,
                'status' => 'Pending'
            ]);

            // Count unassigned pending appointments (available pool)
            $availablePending = (int) $appointmentRepo->createQueryBuilder('a')
                ->select('count(a.id)')
                ->andWhere('a.staff IS NULL')
                ->andWhere('a.status = :status')
                ->setParameter('status', 'Pending')
                ->getQuery()
                ->getSingleScalarResult();

            // Total pending shown on dashboard includes assigned + available pool
            $pending = $assignedPending + $availablePending;

            $accepted = $appointmentRepo->count([
                'staff' => $user,
                'status' => 'Accepted'
            ]);

            // Upcoming appointments for the next 7 days (excluding today)
            $end = (new \DateTime('today'))->modify('+7 days');
            $upcomingCount = (int) $appointmentRepo->createQueryBuilder('a')
                ->select('count(a.id)')
                ->andWhere('a.staff = :staff')
                ->andWhere('a.appointmentDate > :today')
                ->andWhere('a.appointmentDate <= :end')
                ->setParameter('staff', $user)
                ->setParameter('today', $today->format('Y-m-d'))
                ->setParameter('end', $end->format('Y-m-d'))
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $this->render('staff/dashboard.html.twig', [
            'pending' => $pending,
            'accepted' => $accepted,
            'todaysAppointments' => $todaysAppointments,
            'upcomingCount' => $upcomingCount,
            'availablePending' => $availablePending,
        ]);
    }

    // Accept appointment
    #[Route('/appointments/{id}/accept', name: 'staff_accept_appt', methods: ['POST'])]
    public function accept(Request $request, Appointment $appointment, EntityManagerInterface $em): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        // Validate CSRF
        if (!$this->isCsrfTokenValid('appointment_accept_' . $appointment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('staff_dashboard');
        }

        if (!$user) {
            $this->addFlash('error', 'You must be logged in to accept appointments.');
            return $this->redirectToRoute('staff_dashboard');
        }

        // If already accepted by another staff, prevent claiming
        if ($appointment->getStatus() === 'Accepted' && $appointment->getStaff() && $appointment->getStaff() !== $user) {
            $this->addFlash('error', 'This appointment has already been taken by another staff member.');
            return $this->redirectToRoute('staff_appointments');
        }

        // Prevent claiming a completed appointment
        if ($appointment->getStatus() === 'Completed') {
            $this->addFlash('error', 'Cannot accept a completed appointment.');
            return $this->redirectToRoute('staff_appointments');
        }

        // Claim and accept the appointment
        $appointment->setStaff($user);
        $appointment->setStatus('Accepted');
        $em->flush();

        $this->addFlash('success', 'Appointment accepted.');
        // Log staff action
        try {
            /** @var ActivityLogger|null $logger */
            $logger = null;
            // fetch from container if available
            if (method_exists($this, 'get') && $this->container->has(ActivityLogger::class)) {
                $logger = $this->container->get(ActivityLogger::class);
            }
            if ($logger) {
                $logger->logActivity('Appointment Accepted', 'Appointment ID ' . $appointment->getId());
            }
        } catch (\Throwable $e) {
            // ignore logging failures
        }
        return $this->redirectToRoute('staff_dashboard');
    }

    // Decline appointment
    #[Route('/appointments/{id}/decline', name: 'staff_decline_appt', methods: ['POST'])]
    public function decline(Request $request, Appointment $appointment, EntityManagerInterface $em): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        if (!$this->isCsrfTokenValid('appointment_decline_' . $appointment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('staff_dashboard');
        }
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to decline appointments.');
            return $this->redirectToRoute('staff_dashboard');
        }
        // If assigned to another staff, not allowed
        if ($appointment->getStaff() && $appointment->getStaff() !== $user) {
            $this->addFlash('error', 'You are not authorized to modify this appointment.');
            return $this->redirectToRoute('staff_dashboard');
        }

        // Do not allow declining a completed appointment
        if ($appointment->getStatus() === 'Completed') {
            $this->addFlash('error', 'Cannot decline a completed appointment.');
            return $this->redirectToRoute('staff_dashboard');
        }

        // If assigned to current user and accepted, mark declined and return to pool (unassigned)
        if ($appointment->getStaff() === $user) {
            $appointment->setStatus('Pending');
            $appointment->setStaff(null);
            $em->flush();
            $this->addFlash('info', 'Appointment declined and returned to the available pool.');
        } else {
            // Unassigned appointment: staff chooses not to take it; leave it available
            $this->addFlash('info', 'You declined this appointment; it remains available for others.');
        }
        try {
            if (method_exists($this, 'get') && $this->container->has(ActivityLogger::class)) {
                $this->container->get(ActivityLogger::class)->logActivity('Appointment Declined', 'Appointment ID ' . $appointment->getId());
            }
        } catch (\Throwable $e) {}
        return $this->redirectToRoute('staff_dashboard');
    }

    // Complete appointment
    #[Route('/appointments/{id}/complete', name: 'staff_mark_complete', methods: ['POST'])]
    public function complete(Request $request, Appointment $appointment, EntityManagerInterface $em): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        if (!$this->isCsrfTokenValid('appointment_complete_' . $appointment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('staff_dashboard');
        }
        if (!$user || $appointment->getStaff() !== $user) {
            $this->addFlash('error', 'You are not authorized to modify this appointment.');
            return $this->redirectToRoute('staff_dashboard');
        }

        // Prevent marking as complete if it's already completed
        if ($appointment->getStatus() === 'Completed') {
            $this->addFlash('info', 'This appointment is already completed.');
            return $this->redirectToRoute('staff_dashboard');
        }

        $appointment->setStatus('Completed');
        $em->flush();

        $this->addFlash('success', 'Appointment marked as completed.');
        try {
            if (method_exists($this, 'get') && $this->container->has(ActivityLogger::class)) {
                $this->container->get(ActivityLogger::class)->logActivity('Appointment Completed', 'Appointment ID ' . $appointment->getId());
            }
        } catch (\Throwable $e) {}
        return $this->redirectToRoute('staff_dashboard');
    }

    // Reschedule appointment (staff can change date/time)
    #[Route('/appointments/{id}/reschedule', name: 'staff_reschedule_appt', methods: ['GET','POST'])]
    public function reschedule(Request $request, Appointment $appointment, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'You must be logged in to reschedule appointments.');
            return $this->redirectToRoute('staff_appointments');
        }

        // Only the staff who is assigned may reschedule the appointment
        if ($appointment->getStaff() === null || $appointment->getStaff() !== $user) {
            $this->addFlash('error', 'You are not authorized to reschedule this appointment.');
            return $this->redirectToRoute('staff_appointments');
        }

        // Do not allow rescheduling a completed appointment
        if ($appointment->getStatus() === 'Completed') {
            $this->addFlash('error', 'Cannot reschedule a completed appointment.');
            return $this->redirectToRoute('staff_appointments');
        }

        $form = $this->createFormBuilder($appointment)
            ->add('appointmentDate', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                // allow selecting previous dates when rescheduling (no min attribute)
                'attr' => ['class' => 'mt-1 w-full rounded-lg border-gray-300 shadow-sm p-2'],
            ])
            ->add('appointmentTime', TimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                // no min/max time restrictions for staff rescheduling
                'attr' => ['class' => 'mt-1 w-full rounded-lg border-gray-300 shadow-sm p-2'],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Appointment rescheduled.');
            try {
                $actor = $user instanceof \App\Entity\User ? ($user->getUserIdentifier() ?? (string)$user) : 'system';
                $activityLogger->logActivity('Appointment Rescheduled', 'Appointment ID ' . $appointment->getId() . ' rescheduled by ' . $actor . ' to ' . ($appointment->getAppointmentDate() ? $appointment->getAppointmentDate()->format('Y-m-d') : 'N/A') . ' ' . ($appointment->getAppointmentTime() ? $appointment->getAppointmentTime()->format('H:i') : 'N/A'));
            } catch (\Throwable $e) {}
            return $this->redirectToRoute('staff_appointments');
        }

        return $this->render('staff/appointments/reschedule.html.twig', [
            'form' => $form->createView(),
            'appointment' => $appointment,
        ]);
    }

    // Services offered by staff
    #[Route('/services', name: 'staff_services')]
    public function services(\App\Repository\ServicesRepository $servicesRepository): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        $staffProfile = null;
        if ($user instanceof User) {
            $staffProfile = $user->getStaffProfile();
        }

        // Show all services in the system so staff can view services
        // created by any owner (not just their own or their profile).
        $services = $servicesRepository->findAll();

        return $this->render('staff/services.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('/services/new', name: 'staff_services_new', methods: ['GET','POST'])]
    public function newService(Request $request, EntityManagerInterface $em, ActivityLogger $activityLogger, \App\Repository\ServicesRepository $servicesRepository): Response
    {
        $this->ensureStaff();
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'You must be logged in to add services.');
            return $this->redirectToRoute('app_login');
        }

        $service = new \App\Entity\Services();
        $form = $this->createForm(\App\Form\ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Prevent duplicate service names
            $existing = $servicesRepository->findOneBy(['name' => $service->getName()]);
            if ($existing) {
                $form->get('name')->addError(new FormError('A service with this name already exists.'));
                return $this->render('staff/services/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Set owner to current user
            $service->setOwner($user);
            $em->persist($service);
            // also attach to staff profile if present
            $staffProfile = null;
            if ($user instanceof User) {
                $staffProfile = $user->getStaffProfile();
            }
            if ($staffProfile instanceof StaffProfile) {
                $staffProfile->addServiceOffered($service);
                $em->persist($staffProfile);
            }
            $em->flush();

            // Log activity
            try {
                $actor = $user instanceof \App\Entity\User ? ($user->getUserIdentifier() ?? (string)$user) : 'system';
                $svcName = $service->getName() ?: ('ID ' . $service->getId());
                $activityLogger->logActivity('Service Created', 'Service "' . $svcName . '" created by ' . $actor);
            } catch (\Throwable $e) {}

            $this->addFlash('success', 'Service added.');
            return $this->redirectToRoute('staff_services');
        }

        return $this->render('staff/services/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/services/{id}/show', name: 'staff_services_show', methods: ['GET'])]
    public function showService(Services $service): Response
    {
        $this->ensureStaff();
        // Allow staff to view any service. Editing/deleting is still owner-restricted.
        $user = $this->getUser();

        return $this->render('staff/services/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/services/{id}/edit', name: 'staff_services_edit', methods: ['GET','POST'])]
    public function editService(Request $request, Services $service, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        // Allow staff users to edit any service. Ensure user is logged in.
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to edit services.');
        }

        // Allow staff to edit services created by other staff. Only block admin-owned services.
        $owner = $service->getOwner();
        if ($owner && in_array('ROLE_ADMIN', $owner->getRoles(), true)) {
            $this->addFlash('error', 'Admin-owned services cannot be edited by staff.');
            return $this->redirectToRoute('staff_services');
        }

        $form = $this->createForm(\App\Form\ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Prevent renaming to an existing service name (different id)
            $sr = $em->getRepository(Services::class)->findOneBy(['name' => $service->getName()]);
            if ($sr && $sr->getId() !== $service->getId()) {
                $form->get('name')->addError(new FormError('A service with that name already exists.'));
                return $this->render('staff/services/edit.html.twig', [
                    'form' => $form->createView(),
                    'service' => $service,
                ]);
            }

            $em->flush();
            try {
                $actor = $user instanceof \App\Entity\User ? ($user->getUserIdentifier() ?? (string)$user) : 'system';
                $svcName = $service->getName() ?: ('ID ' . $service->getId());
                $activityLogger->logActivity('Service Updated', 'Service "' . $svcName . '" updated by ' . $actor);
            } catch (\Throwable $e) {}
            $this->addFlash('success', 'Service updated.');
            return $this->redirectToRoute('staff_services');
        }

        return $this->render('staff/services/edit.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
        ]);
    }

    #[Route('/services/{id}/delete', name: 'staff_services_delete', methods: ['POST'])]
    public function deleteService(Request $request, Services $service, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to delete services.');
            return $this->redirectToRoute('staff_services');
        }

        // Allow staff to delete services created by other staff. Only block admin-owned services.
        $owner = $service->getOwner();
        if ($owner && in_array('ROLE_ADMIN', $owner->getRoles(), true)) {
            $this->addFlash('error', 'Admin-owned services cannot be deleted by staff.');
            return $this->redirectToRoute('staff_services');
        }

        // Prevent deleting a service that is referenced by appointments.
        $appointmentRepo = $em->getRepository(\App\Entity\Appointment::class);
        $dependentCount = null;
        try {
            $dependentCount = (int) $appointmentRepo->count(['service' => $service]);
        } catch (\Throwable $e) {
            // Fallback: if count failed, attempt via query builder
            try {
                $dependentCount = (int) $appointmentRepo->createQueryBuilder('a')
                    ->select('count(a.id)')
                    ->andWhere('a.service = :s')
                    ->setParameter('s', $service)
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (\Throwable $e) {
                $dependentCount = null; // unknown
            }
        }

        if ($dependentCount === null) {
            $this->addFlash('error', 'Unable to verify whether this service is used by any appointments. Delete aborted for safety. Check logs for details.');
            return $this->redirectToRoute('staff_services');
        }

        // If we couldn't determine dependency count we abort; otherwise allow deletion
        if ($dependentCount === null) {
            $this->addFlash('error', 'Unable to verify whether this service is used by any appointments. Delete aborted for safety. Check logs for details.');
            return $this->redirectToRoute('staff_services');
        }

        if ($this->isCsrfTokenValid('delete-service-' . $service->getId(), $request->request->get('_token'))) {
            // If there are appointments referencing this service and DB schema
            // hasn't been migrated to SET NULL, proactively nullify the relation
            // on those appointments before deleting the service.
            if ($dependentCount > 0) {
                try {
                    $appointments = $appointmentRepo->findBy(['service' => $service]);
                    foreach ($appointments as $appt) {
                        $appt->setService(null);
                        $em->persist($appt);
                    }
                    $em->flush();
                } catch (\Throwable $e) {
                    // If nullifying fails, abort delete to avoid FK constraint
                    $this->addFlash('error', 'Failed to clear dependent appointments. Delete aborted.');
                    return $this->redirectToRoute('staff_services');
                }
            }

            $em->remove($service);
            $em->flush();
            try {
                $actor = $user instanceof \App\Entity\User ? ($user->getUserIdentifier() ?? (string)$user) : 'system';
                $svcName = $service->getName() ?: ('ID ' . $service->getId());
                $activityLogger->logActivity('Service Deleted', 'Service "' . $svcName . '" deleted by ' . $actor);
            } catch (\Throwable $e) {}
            $this->addFlash('success', 'Service deleted.');
        }

        return $this->redirectToRoute('staff_services');
    }

    // Availability feature removed from staff side: related routes and handlers deleted.

    // Profile view (read-only)
    #[Route('/profile', name: 'staff_profile')]
    public function profile(Request $request): Response
    {
        $this->ensureStaff();

        $user = $this->getUser();
        $staffProfile = null;
        if ($user instanceof User) {
            $staffProfile = $user->getStaffProfile();
        }

        return $this->render('staff/profile.html.twig', [
            'staff' => $staffProfile,
            'profileUser' => $user,
        ]);
    }

    #[Route('/profile/change-password', name: 'staff_profile_change_password', methods: ['GET','POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        $this->ensureStaff();

        $user = $this->getUser();
        if (!($user instanceof \App\Entity\User)) {
            $this->addFlash('error', 'Unable to update password.');
            return $this->redirectToRoute('staff_profile');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();
            $hashed = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashed);
            $em->flush();

            $this->addFlash('success', 'Password updated successfully.');
            return $this->redirectToRoute('staff_profile');
        }

        return $this->render('staff/profile/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // staff change-password feature removed

    // Staff appointments
    #[Route('/appointments', name: 'staff_appointments')]
    public function myAppointments(AppointmentRepository $appointmentRepo): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        $staffProfile = null;
        if ($user instanceof User) {
            $staffProfile = $user->getStaffProfile();
        }

        $assigned = [];
        $available = [];
        if ($user instanceof User) {
            $assigned = $appointmentRepo->findBy(['staff' => $user], ['appointmentDate' => 'DESC']);
            // available pool: unassigned and pending
            $available = $appointmentRepo->createQueryBuilder('a')
                ->andWhere('a.staff IS NULL')
                ->andWhere('a.status = :status')
                ->setParameter('status', 'Pending')
                ->orderBy('a.appointmentDate', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('staff/appointments.html.twig', [
            'assignedAppointments' => $assigned,
            'availableAppointments' => $available,
        ]);
    }

    #[Route('/appointments/{id}/delete', name: 'staff_delete_appt', methods: ['POST'])]
    public function deleteAppointment(Request $request, Appointment $appointment, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $this->ensureStaff();
        $user = $this->getUser();
        $staffProfile = null;
        if ($user instanceof User) {
            $staffProfile = $user->getStaffProfile();
        }
        // Validate CSRF token for delete
        if (!$this->isCsrfTokenValid('appointment_delete_' . $appointment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('staff_appointments');
        }

        if ($user instanceof User && $appointment->getStaff() === $user) {
            // Prevent deleting a completed appointment
            if ($appointment->getStatus() === 'Completed') {
                $this->addFlash('error', 'Cannot delete a completed appointment.');
                return $this->redirectToRoute('staff_appointments');
            }

            $em->remove($appointment);
            $em->flush();
            try {
                $activityLogger->logActivity('Appointment Deleted', 'Appointment ID ' . $appointment->getId());
            } catch (\Throwable $e) {}
            $this->addFlash('success', 'Appointment deleted.');
        } else {
            $this->addFlash('error', 'Cannot delete this appointment.');
        }

        return $this->redirectToRoute('staff_appointments');
    }
}
