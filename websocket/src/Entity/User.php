<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $api_key;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $secret_key;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $user_name;

    public function getId()
    {
        return $this->id;
    }

    public function getApiKey(): ?string
    {
        return $this->api_key;
    }

    public function setApiKey(?string $api_key): self
    {
        $this->api_key = $api_key;

        return $this;
    }

    public function getSecretKey(): ?string
    {
        return $this->secret_key;
    }

    public function setSecretKey(?string $secret_key): self
    {
        $this->secret_key = $secret_key;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->user_name;
    }

    public function setUserName(?string $user_name): self
    {
        $this->user_name = $user_name;

        return $this;
    }
}
