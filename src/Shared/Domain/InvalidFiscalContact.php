<?php

namespace App\Shared\Domain;

final class InvalidFiscalContact extends \DomainException
{
    public const TAX_ID_MISSING = 'taxIdMissing';

    public const TAX_ID = 'taxId';

    public const PHONE_MISSING = 'phoneMissing';

    public const PHONE = 'phone';

    public const STREET_MISSING = 'streetMissing';

    public const STREET = 'street';

    public const POSTAL_CODE_MISSING = 'postalCodeMissing';

    public const POSTAL_CODE = 'postalCode';

    public const POSTAL_CODE_MISMATCH = 'postalCodeMismatch';

    public const MUNICIPALITY_MISSING = 'municipalityMissing';

    public const MUNICIPALITY = 'municipality';

    public const PROVINCE = 'province';

    public function __construct(private readonly string $field)
    {
        parent::__construct('The fiscal contact is not valid.');
    }

    public function field(): string
    {
        return $this->field;
    }
}
