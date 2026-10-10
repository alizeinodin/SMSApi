<?php

namespace Alizeinodin\SmsApi\Contracts;

interface SmsLineAware
{
    public function setLineNumber(int|string $lineNumber): static;

    public function getLineNumber(): ?int;
}
