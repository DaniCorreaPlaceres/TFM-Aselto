<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AudienceLandingController extends AbstractController
{
    #[Route('/autonomos', name: 'commercial_self_employed', methods: ['GET'])]
    public function selfEmployed(): Response
    {
        return $this->page('Autónomos', 'autonomo', 'Menos papeles. Más tiempo para tu actividad.', 'Reúne facturas y justificantes, revisa los datos y consulta cómo va tu trimestre. Puedes empezar por tu cuenta y trabajar con una asesoría cuando la necesites.', ['De la foto al registro' => 'Añade una foto o un archivo y revisa la propuesta junto al original.', 'Tu trimestre, comprensible' => 'Distingue ingresos, gastos y documentos pendientes antes de cerrar tu periodo personal.', 'Tu asesoría, conectada' => 'Si estás vinculado, comparte los documentos y atiende sus solicitudes en el mismo lugar.'], 'Un gasto de material, sin perder el justificante', 'Subes el documento, corriges la propuesta y lo confirmas. Sin asesoría cuenta para tu control personal; con asesoría queda pendiente de su validación.');
    }

    #[Route('/empresas', name: 'commercial_company', methods: ['GET'])]
    public function company(): Response
    {
        return $this->page('Empresas', 'sociedad', 'La documentación de tu empresa, con orden.', 'Deja de buscar facturas entre carpetas y mensajes. Centraliza los documentos de tu sociedad y sigue la revisión con tu asesoría sin perder el contexto.', ['Cada documento con sus datos' => 'Conserva el original y revisa tercero, fecha, base e impuesto antes de confirmar.', 'Pendientes que se ven' => 'Distingue qué está por revisar, rechazado o pendiente de un justificante.', 'Un histórico para consultar' => 'Conserva cierres y reaperturas del trimestre, diferenciando control personal y validación de asesoría.'], 'Una factura que necesita otro justificante', 'La asesoría lo solicita en el registro. Aportas el archivo adicional sin sustituir la factura original y el equipo puede continuar la revisión.');
    }

    #[Route('/asesorias-profesionales', name: 'commercial_advisory', methods: ['GET'])]
    public function advisory(): Response
    {
        return $this->page('Asesorías', null, 'Tus clientes aportan. Tu equipo tiene el control.', 'Una bandeja para revisar la documentación de tus clientes, pedir lo que falta y validar sus registros. Menos información dispersa y más claridad antes del cierre.', ['Revisión compartida' => 'Admin y gestores atienden los documentos dentro de su asesoría.', 'Solicitudes con contexto' => 'Pide justificantes en el propio documento o abre una conversación con el cliente.', 'Cifras basadas en lo validado' => 'Consulta pendientes, valida y cierra cuando el trimestre esté resuelto. Conserva su histórico.'], 'Del documento pendiente al trimestre revisado', 'Tu cliente confirma una factura. El equipo comprueba los datos y, si falta algo, solicita un justificante. Solo tras validar entra en las cifras validadas de la asesoría.');
    }

    /** @param array<string, string> $benefits */
    private function page(string $audience, ?string $kind, string $title, string $intro, array $benefits, string $exampleTitle, string $example): Response
    {
        return $this->render('commercial/audience.html.twig', compact('audience', 'kind', 'title', 'intro', 'benefits', 'exampleTitle', 'example'));
    }
}
