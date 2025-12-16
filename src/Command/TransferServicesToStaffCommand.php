<?php

namespace App\Command;

use App\Entity\Services;
use App\Repository\ServicesRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:services:transfer-to-staff', description: 'Transfer services owned by admin or without owner to a staff user')]
class TransferServicesToStaffCommand extends Command
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;
    private ServicesRepository $servicesRepository;

    public function __construct(EntityManagerInterface $em, UserRepository $userRepository, ServicesRepository $servicesRepository)
    {
        parent::__construct();
        $this->em = $em;
        $this->userRepository = $userRepository;
        $this->servicesRepository = $servicesRepository;
    }

    protected function configure(): void
    {
        $this->addArgument('staffId', InputArgument::OPTIONAL, 'ID of the Staff user to reassign services to (optional). If omitted, the first staff user is used.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $staffId = $input->getArgument('staffId');

        // find candidate staff users (ROLE_STAFF)
        $staffUsers = $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_STAFF%')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();

        if (count($staffUsers) === 0) {
            $output->writeln('<error>No staff users found in the system. Create a staff user first.</error>');
            return Command::FAILURE;
        }

        $target = null;
        if ($staffId) {
            $target = $this->userRepository->find($staffId);
            if (!$target) {
                $output->writeln('<error>Staff user with ID ' . $staffId . ' not found.</error>');
                return Command::FAILURE;
            }
            // basic role check
            if (strpos(json_encode($target->getRoles()), 'ROLE_STAFF') === false) {
                $output->writeln('<error>User with ID ' . $staffId . ' is not a staff user.</error>');
                return Command::FAILURE;
            }
        } else {
            $target = $staffUsers[0];
        }

        // find services with owner NULL or owner that has ROLE_ADMIN
        $qb = $this->servicesRepository->createQueryBuilder('s')
            ->leftJoin('s.owner', 'o')
            ->andWhere('o.id IS NULL OR o.roles LIKE :adminRole')
            ->setParameter('adminRole', '%ROLE_ADMIN%');

        $services = $qb->getQuery()->getResult();

        if (count($services) === 0) {
            $output->writeln('<info>No services require transfer.</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Transferring ' . count($services) . ' services to staff user ID ' . $target->getId() . ' ('.$target->getUserIdentifier().').</info>');

        foreach ($services as $service) {
            /** @var Services $service */
            $service->setOwner($target);
            $this->em->persist($service);
        }

        $this->em->flush();

        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }
}
