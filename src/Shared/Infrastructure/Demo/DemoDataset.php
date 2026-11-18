<?php

namespace App\Shared\Infrastructure\Demo;

use App\Cliente\Domain\ClientKind;
use App\Documento\Domain\Tax;
use App\Identidad\Domain\Role;
use Symfony\Component\Uid\Uuid;

final class DemoDataset
{
    public const PASSWORD = 'aselto-demo';

    public function __construct(private readonly string $namespace = '')
    {
    }

    public function id(string $key): string
    {
        return Uuid::v5(Uuid::fromString('c1a9e4d0-6756-4ca4-b48f-a942ecdd3700'), 'aselto-demo-v1/'.$this->namespace.'/'.$key)->toRfc4122();
    }

    /** @return array<string, string> */
    public static function firms(): array
    {
        return ['sol' => 'Asesoría Sol · Demostración', 'atlantico' => 'Asesoría Atlántico · Demostración'];
    }

    /** @return array<string, array{name: string, firm: ?string, kind: ClientKind, province: string, postal: string, tax: Tax}> */
    public static function clients(): array
    {
        return [
            'autonomo-sol' => ['name' => 'Autónomo IVA · Demo', 'firm' => 'sol', 'kind' => ClientKind::SelfEmployed, 'province' => 'Madrid', 'postal' => '28001', 'tax' => Tax::Vat],
            'sociedad-sol' => ['name' => 'Sociedad IPSI · Demo', 'firm' => 'sol', 'kind' => ClientKind::Company, 'province' => 'Ceuta', 'postal' => '51001', 'tax' => Tax::Ipsi],
            'autonomo-atlantico' => ['name' => 'Autónomo IGIC · Demo', 'firm' => 'atlantico', 'kind' => ClientKind::SelfEmployed, 'province' => 'Las Palmas', 'postal' => '35001', 'tax' => Tax::Igic],
            'sociedad-atlantico' => ['name' => 'Sociedad IVA · Demo', 'firm' => 'atlantico', 'kind' => ClientKind::Company, 'province' => 'Madrid', 'postal' => '28001', 'tax' => Tax::Vat],
            'independiente' => ['name' => 'Autónomo sin asesoría · Demo', 'firm' => null, 'kind' => ClientKind::SelfEmployed, 'province' => 'Madrid', 'postal' => '28001', 'tax' => Tax::Vat],
        ];
    }

    /** @return array<string, array{role: Role, firm: ?string}> */
    public static function accounts(): array
    {
        $accounts = ['superadmin' => ['role' => Role::Superadmin, 'firm' => null]];
        foreach (self::firms() as $firm => $name) {
            $accounts['admin-'.$firm] = ['role' => Role::FirmAdmin, 'firm' => $firm];
            $accounts['gestor-'.$firm] = ['role' => Role::Advisor, 'firm' => $firm];
        }
        foreach (self::clients() as $key => $client) {
            $accounts[$key] = ['role' => Role::Client, 'firm' => $client['firm']];
        }

        return $accounts;
    }

    public function email(string $account): string
    {
        return 'demo-'.('' === $this->namespace ? '' : $this->namespace.'-').$account.'@example.test';
    }

    /** @return list<string> */
    public static function documentKinds(bool $personal): array
    {
        return $personal ? ['q3-income', 'q3-expense', 'q4-income', 'q4-draft', 'undated'] : ['q3-income', 'q3-expense', 'q4-income', 'q4-expense', 'q4-submitted', 'q4-rejected', 'q4-requested'];
    }

    /** @return list<string> */
    public static function conversationKinds(): array
    {
        return ['conversation', 'pending', 'resolved'];
    }

    public static function pdf(string $label): string
    {
        $label = preg_replace('/[^a-zA-Z0-9 -]/', '-', $label);
        $text = 'BT /F1 16 Tf 40 780 Td (ASELTO - DEMOSTRACION) Tj 0 -30 Td /F1 11 Tf (Documento ficticio sin validez fiscal) Tj 0 -24 Td ('.$label.') Tj ET';
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length '.strlen($text).">>\nstream\n".$text."\nendstream"];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
