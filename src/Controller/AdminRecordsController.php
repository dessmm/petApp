<?php

namespace App\Controller;

use App\Repository\ServicesRepository;
use App\Repository\AppointmentRepository;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/records')]
final class AdminRecordsController extends AbstractController
{
    #[Route('/', name: 'app_admin_records_index', methods: ['GET'])]
    public function index(Request $request,
                          ServicesRepository $servicesRepo,
                          AppointmentRepository $appointmentRepo,
                          ContactRepository $contactRepo,
                          UserRepository $userRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $q = trim((string)$request->query->get('q', ''));
        $type = $request->query->get('type', 'all');
        $staffId = $request->query->get('staff_id');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $status = $request->query->get('status');

        $results = [];

        // Services
        if ($type === 'all' || $type === 'services') {
            $qb = $servicesRepo->createQueryBuilder('s');
            if ($q !== '') {
                $qb->andWhere('s.name LIKE :q OR s.description LIKE :q')
                    ->setParameter('q', '%' . $q . '%');
            }
            if ($staffId) {
                $qb->andWhere('s.owner = :staffId')->setParameter('staffId', $staffId);
            }
            $services = $qb->getQuery()->getResult();
            foreach ($services as $s) {
                $results[] = [
                    'type' => 'Service',
                    'id' => $s->getId(),
                    'title' => $s->getName(),
                    'staff' => $s->getOwner()?->getFullName() ?? $s->getOwner()?->getUserIdentifier() ?? '—',
                    'date' => method_exists($s, 'getCreatedAt') ? $s->getCreatedAt() : null,
                    'status' => method_exists($s, 'getStatus') ? $s->getStatus() : null,
                    'route_show' => 'app_admin_services_show',
                    'route_edit' => 'app_admin_services_edit',
                    'route_delete' => 'app_admin_services_delete',
                ];
            }
        }

        // Appointments
        if ($type === 'all' || $type === 'appointments') {
            $qb = $appointmentRepo->createQueryBuilder('a');
            if ($q !== '') {
                $qb->andWhere('a.petName LIKE :q OR a.ownerName LIKE :q')
                    ->setParameter('q', '%' . $q . '%');
            }
            if ($staffId) {
                $qb->andWhere('a.staff = :staffId')->setParameter('staffId', $staffId);
            }
            if ($status) {
                $qb->andWhere('a.status = :status')->setParameter('status', $status);
            }
            $appointments = $qb->orderBy('a.appointmentDate', 'DESC')->getQuery()->getResult();
            foreach ($appointments as $a) {
                $results[] = [
                    'type' => 'Appointment',
                    'id' => $a->getId(),
                    'title' => $a->getPetName(),
                    'staff' => $a->getStaff()?->getFullName() ?? $a->getStaff()?->getUserIdentifier() ?? '—',
                    'date' => $a->getAppointmentDate(),
                    'status' => $a->getStatus(),
                    'route_show' => 'app_admin_appointments_show',
                    'route_edit' => 'app_admin_appointments_edit',
                    'route_delete' => 'app_admin_appointments_delete',
                ];
            }
        }

        // Contacts
        if ($type === 'all' || $type === 'contacts') {
            $qb = $contactRepo->createQueryBuilder('c');
            if ($q !== '') {
                $qb->andWhere('c.subject LIKE :q OR c.message LIKE :q OR c.firstName LIKE :q OR c.lastName LIKE :q')
                    ->setParameter('q', '%' . $q . '%');
            }
            if ($status) {
                $qb->andWhere('c.status = :status')->setParameter('status', $status);
            }
            $contacts = $qb->orderBy('c.submittedAt', 'DESC')->getQuery()->getResult();
            foreach ($contacts as $c) {
                $results[] = [
                    'type' => 'Message',
                    'id' => $c->getId(),
                    'title' => $c->getSubject(),
                    'staff' => '—',
                    'date' => $c->getSubmittedAt(),
                    'status' => $c->getStatus(),
                    'route_show' => 'app_admin_contacts_show',
                    'route_edit' => 'app_admin_contacts_edit',
                    'route_delete' => 'app_admin_contacts_delete',
                ];
            }
        }

        // Sort combined results by date desc
        usort($results, function ($a, $b) {
            $ad = $a['date'] ? strtotime($a['date']->format('Y-m-d H:i:s')) : 0;
            $bd = $b['date'] ? strtotime($b['date']->format('Y-m-d H:i:s')) : 0;
            return $bd <=> $ad;
        });

        // Load staff list for filter
        $staffList = $userRepo->createQueryBuilder('u')
            ->andWhere("u.roles LIKE :role")
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getResult();

        return $this->render('admin_dashboard/records/index.html.twig', [
            'results' => $results,
            'staffList' => $staffList,
            'q' => $q,
            'type' => $type,
            'staffId' => $staffId,
            'status' => $status,
        ]);
    }
}
