<?php

namespace Rafik\PscWorldWebservice\Model;

use Symfony\Component\Serializer\Annotation\SerializedName;

final class Root
{
    #[SerializedName('@organizacion')]
    private string $organization;

    #[SerializedName('#')]
    private string $name;

    #[SerializedName('@certificado')]
    private string $certificate;

    public function __construct(string $organization, string $name, string $certificate)
    {
        $this->organization = $organization;
        $this->name = $name;
        $this->certificate = $certificate;
    }

    public function getOrganization(): string
    {
        return $this->organization;
    }

    public function setOrganization(string $organization): void
    {
        $this->organization = $organization;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCertificate(): string
    {
        return $this->certificate;
    }

    public function setCertificate(string $certificate): void
    {
        $this->certificate = $certificate;
    }
}