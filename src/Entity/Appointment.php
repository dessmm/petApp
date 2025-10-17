<?php

namespace App\Entity;

use App\Repository\AppointmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private ?string $petName = null;

    #[ORM\Column(length: 128)]
    private ?string $ownerName = null;

    #[ORM\Column(length: 128)]
    #[Assert\Email(
        message: 'The email {{ value }} is not a valid email.',
    )]
    private ?string $ownerEmail = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $appointmentDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $appointmentTime = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Services $service = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPetName(): ?string
    {
        return $this->petName;
    }

    public function setPetName(string $petName): static
    {
        $this->petName = $petName;

        return $this;
    }

    public function getOwnerName(): ?string
    {
        return $this->ownerName;
    }

    public function setOwnerName(string $ownerName): static
    {
        $this->ownerName = $ownerName;

        return $this;
    }

    public function getOwnerEmail(): ?string
    {
        return $this->ownerEmail;
    }

    public function setOwnerEmail(string $ownerEmail): static
    {
        $this->ownerEmail = $ownerEmail;

        return $this;
    }

    public function getAppointmentDate(): ?\DateTime
    {
        return $this->appointmentDate;
    }

    public function setAppointmentDate(\DateTime $appointmentDate): static
    {
        $this->appointmentDate = $appointmentDate;

        return $this;
    }

    public function getAppointmentTime(): ?\DateTime
    {
        return $this->appointmentTime;
    }

    public function setAppointmentTime(\DateTime $appointmentTime): static
    {
        $this->appointmentTime = $appointmentTime;

        return $this;
    }

    public function getService(): ?Services
    {
        return $this->service;
    }

    public function setService(?Services $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }
}
