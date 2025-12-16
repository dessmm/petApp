<?php

namespace App\Entity;

use App\Repository\StaffProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Services;

#[ORM\Entity(repositoryClass: StaffProfileRepository::class)]
class StaffProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'staffProfile', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(nullable: true)]
    private ?array $services = null;

    #[ORM\Column(nullable: true)]
    private ?array $availability = null;

    #[ORM\ManyToMany(targetEntity: Services::class)]
    private Collection $servicesOffered;

    /**
     * @var Collection<int, Availability>
     */
    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: 'staffProfile', orphanRemoval: true)]
    private Collection $availabilities;

     public function __construct()
    {
        $this->servicesOffered = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
    }

    /**
     * @return Collection|Services[]
     */
    public function getServicesOffered(): Collection
    {
        return $this->servicesOffered;
    }

    public function setServicesOffered(array|Collection $services): self
    {
        $this->servicesOffered = new ArrayCollection();

        foreach ($services as $service) {
            $this->servicesOffered->add($service);
        }

        return $this;
    }

    public function addServiceOffered(Services $service): self
    {
        if (!$this->servicesOffered->contains($service)) {
            $this->servicesOffered->add($service);
        }
        return $this;
    }

    public function removeServiceOffered(Services $service): self
    {
        $this->servicesOffered->removeElement($service);
        return $this;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getServices(): ?array
    {
        return $this->services;
    }

    public function setServices(?array $services): static
    {
        $this->services = $services;

        return $this;
    }

    public function getAvailability(): ?array
    {
        return $this->availability;
    }

    public function setAvailability(?array $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    /**
     * @return Collection<int, Availability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(Availability $availability): static
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setStaffProfile($this);
        }

        return $this;
    }

    public function removeAvailability(Availability $availability): static
    {
        if ($this->availabilities->removeElement($availability)) {
            // set the owning side to null (unless already changed)
            if ($availability->getStaffProfile() === $this) {
                $availability->setStaffProfile(null);
            }
        }

        return $this;
    }
}
