<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    
    public function load(ObjectManager $manager): void
    {
        // Create an admin user
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setEmail('admin@example.com');
        $admin->setFullName('Site Administrator');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'adminpass'));
        $admin->setIsActive(true);
        $admin->setCreatedAt(new \DateTime());
        $manager->persist($admin);

        // Create a staff user
        $staff = new User();
        $staff->setUsername('staff');
        $staff->setEmail('staff@example.com');
        $staff->setFullName('Staff Member');
        $staff->setRoles(['ROLE_STAFF']);
        $staff->setPassword($this->passwordHasher->hashPassword($staff, 'staffpass'));
        $staff->setIsActive(true);
        $staff->setCreatedAt(new \DateTime());
        $manager->persist($staff);

        $manager->flush();
    }
}
