<?php

namespace App\Shared\Ui;

use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\InvalidFiscalContact;

final class FiscalContactForm
{
    public static function read(
        string $taxId,
        string $phone,
        string $street,
        string $postalCode,
        string $municipality,
        string $province,
    ): FiscalContact {
        return FiscalContact::required($taxId, $phone, $street, $postalCode, $municipality, $province);
    }

    public static function message(InvalidFiscalContact $error): string
    {
        return match ($error->field()) {
            InvalidFiscalContact::TAX_ID_MISSING => 'Indica el NIF.',
            InvalidFiscalContact::TAX_ID => 'El NIF no es válido.',
            InvalidFiscalContact::PHONE_MISSING => 'Indica el teléfono.',
            InvalidFiscalContact::PHONE => 'El teléfono no es válido. Usa 9 cifras.',
            InvalidFiscalContact::STREET_MISSING => 'Indica la dirección.',
            InvalidFiscalContact::STREET => 'La dirección es demasiado larga.',
            InvalidFiscalContact::POSTAL_CODE_MISSING => 'Indica el código postal.',
            InvalidFiscalContact::POSTAL_CODE => 'El código postal tiene que tener 5 cifras.',
            InvalidFiscalContact::POSTAL_CODE_MISMATCH => 'El código postal no corresponde a la provincia.',
            InvalidFiscalContact::MUNICIPALITY_MISSING => 'Indica el municipio.',
            InvalidFiscalContact::MUNICIPALITY => 'El municipio es demasiado largo.',
            InvalidFiscalContact::PROVINCE => 'Elige la provincia.',
            default => 'Revisa los datos fiscales.',
        };
    }
}
