<?php

namespace Rafik\PscWorldWebservice\Model;

use Symfony\Component\Serializer\Annotation\SerializedName;

final class Issuer
{
    #[SerializedName('@organizacion')]
    private string $organization;

    #[SerializedName('#')]
    private string $name;

    #[SerializedName('@oid')]
    private string $oid;

    #[SerializedName('@certificado')]
    private string $certificate;

    public function __construct(string $organization, string $name, string $oid, string $certificate)
    {
        $this->organization = $organization;
        $this->name = $name;
        $this->oid = $oid;
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

    public function getOid(): string
    {
        return $this->oid;
    }

    public function setOid(string $oid): void
    {
        $this->oid = $oid;
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