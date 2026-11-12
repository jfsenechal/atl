<?php

namespace AcMarche\Mercredi\Entity;

use AcMarche\Mercredi\Entity\Traits\IdTrait;
use AcMarche\Mercredi\Message\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Contract\Entity\TimestampableInterface;
use Knp\DoctrineBehaviors\Model\Timestampable\TimestampableTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'message')]
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message implements TimestampableInterface
{
    use IdTrait;
    use TimestampableTrait;
    public bool $attachCourriers;

    #[Assert\NotBlank]
    private ?string $from = null;
    private ?string $to = null;
    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank]
    private ?string $sujet = null;
    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank]
    private ?string $texte = null;
    private ?UploadedFile $file = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $destinataires = [];

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(?string $from): void
    {
        $this->from = $from;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    public function setTo(?string $to): void
    {
        $this->to = $to;
    }

    public function getSujet(): ?string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): self
    {
        $this->sujet = $sujet;

        return $this;
    }

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function setTexte(string $texte): self
    {
        $this->texte = $texte;

        return $this;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): void
    {
        $this->file = $file;
    }

    /**
     * @return list<string>
     */
    public function getDestinataires(): array
    {
        return $this->destinataires;
    }

    public function setDestinataires(array $destinataires): self
    {
        $this->destinataires = array_values($destinataires);

        return $this;
    }
}
