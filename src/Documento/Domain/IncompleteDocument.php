<?php

namespace App\Documento\Domain;

final class IncompleteDocument extends \DomainException
{
    /**
     * @param list<string> $fields
     */
    public function __construct(private readonly array $fields)
    {
        parent::__construct('The document is incomplete.');
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
