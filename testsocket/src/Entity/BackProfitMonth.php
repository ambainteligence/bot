<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BackProfitMonthRepository")
 */
class BackProfitMonth
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $uid;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $exchange;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $created_date;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $percent;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $data;

    public function getId()
    {
        return $this->id;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getExchange(): ?string
    {
        return $this->exchange;
    }

    public function setExchange(?string $exchange): self
    {
        $this->exchange = $exchange;

        return $this;
    }

    public function getCreatedDate(): ?string
    {
        return $this->created_date;
    }

    public function setCreatedDate(?string $created_date): self
    {
        $this->created_date = $created_date;

        return $this;
    }

    public function getPercent(): ?string
    {
        return $this->percent;
    }

    public function setPercent(string $percent): self
    {
        $this->percent = $percent;

        return $this;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;

        return $this;
    }
}
