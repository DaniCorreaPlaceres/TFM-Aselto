<?php

namespace App\Shared\Domain;

final class SpanishProvince
{
    private const BY_CODE = [
        '01' => 'Álava',
        '02' => 'Albacete',
        '03' => 'Alicante',
        '04' => 'Almería',
        '05' => 'Ávila',
        '06' => 'Badajoz',
        '07' => 'Illes Balears',
        '08' => 'Barcelona',
        '09' => 'Burgos',
        '10' => 'Cáceres',
        '11' => 'Cádiz',
        '12' => 'Castellón',
        '13' => 'Ciudad Real',
        '14' => 'Córdoba',
        '15' => 'A Coruña',
        '16' => 'Cuenca',
        '17' => 'Girona',
        '18' => 'Granada',
        '19' => 'Guadalajara',
        '20' => 'Gipuzkoa',
        '21' => 'Huelva',
        '22' => 'Huesca',
        '23' => 'Jaén',
        '24' => 'León',
        '25' => 'Lleida',
        '26' => 'La Rioja',
        '27' => 'Lugo',
        '28' => 'Madrid',
        '29' => 'Málaga',
        '30' => 'Murcia',
        '31' => 'Navarra',
        '32' => 'Ourense',
        '33' => 'Asturias',
        '34' => 'Palencia',
        '35' => 'Las Palmas',
        '36' => 'Pontevedra',
        '37' => 'Salamanca',
        '38' => 'Santa Cruz de Tenerife',
        '39' => 'Cantabria',
        '40' => 'Segovia',
        '41' => 'Sevilla',
        '42' => 'Soria',
        '43' => 'Tarragona',
        '44' => 'Teruel',
        '45' => 'Toledo',
        '46' => 'Valencia',
        '47' => 'Valladolid',
        '48' => 'Bizkaia',
        '49' => 'Zamora',
        '50' => 'Zaragoza',
        '51' => 'Ceuta',
        '52' => 'Melilla',
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_values(self::BY_CODE);
    }

    public static function codeFor(string $name): ?string
    {
        $code = array_search($name, self::BY_CODE, true);

        if (!is_int($code) && !is_string($code)) {
            return null;
        }

        return str_pad((string) $code, 2, '0', STR_PAD_LEFT);
    }
}
