<?php

namespace App\Documento\Ui;

use App\Documento\Domain\DocumentStatus;
use App\Documento\Domain\MovementDirection;

final class DocumentLabel
{
    public static function status(DocumentStatus $status): string
    {
        return match ($status) {
            DocumentStatus::Uploaded => 'Subido',
            DocumentStatus::Extracting => 'Extrayendo',
            DocumentStatus::Draft => 'Borrador',
            DocumentStatus::Submitted => 'Enviado',
            DocumentStatus::Validated => 'Validado',
            DocumentStatus::Rejected => 'Rechazado',
        };
    }

    public static function direction(?MovementDirection $direction): string
    {
        return match ($direction) {
            MovementDirection::Income => 'Ingreso',
            MovementDirection::Expense => 'Gasto',
            null => '',
        };
    }

    public static function euros(?int $cents): string
    {
        if (null === $cents) {
            return '';
        }

        return number_format($cents / 100, 2, ',', '.');
    }
}
