<?php

namespace Rafik\PscWorldWebservice\Model;

use Symfony\Component\Serializer\Annotation\SerializedName;

final class Certificate
{
    #[SerializedName('esValida')]
    private bool $valid;

    #[SerializedName('emisor')]
    private ?Issuer $issuer;

    #[SerializedName('raiz')]
    private ?Root $root;

    public function __construct(bool $valid, Issuer $issuer = null, Root $root = null)
    {
        $this->valid = $valid;
        $this->issuer = $issuer;
        $this->root = $root;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getIssuer(): ?Issuer
    {
        return $this->issuer;
    }

    public function getRoot(): ?Root
    {
        return $this->root;
    }
}