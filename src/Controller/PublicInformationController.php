<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicInformationController extends AbstractController
{
    #[Route('/quienes-somos', name: 'commercial_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->page('Quiénes somos', false, [
            'Un proyecto para trabajar con más claridad' => 'Aselto es el proyecto TFM de Daniel Correa Placeres. Nace para reunir documentos, personas y cifras: el cliente aporta información y la asesoría revisa y valida dentro de su propio espacio.',
            'Tecnología con revisión humana' => 'La IA puede ayudar a preparar una propuesta de datos. Una persona revisa y confirma; la extracción no decide por sí sola la validez fiscal del documento.',
            'Qué puedes hacer' => 'Organizar facturas y justificantes, consultar ingresos y gastos, resolver solicitudes con tu asesoría y conservar cierres e histórico del trimestre. También puedes llevar tu control personal sin asesoría.',
            'Alcance del TFM' => 'La plataforma no emite facturas ni presenta impuestos. La contratación, identificación legal del prestador y publicación en producción requieren completar su preparación. La autoría del proyecto no sustituye esa identificación.',
        ]);
    }

    #[Route('/privacidad', name: 'commercial_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->page('Política de privacidad', true, [
            'Responsable y contacto' => 'Pendientes de confirmar por el titular: identidad legal del responsable, domicilio y correo para privacidad y ejercicio de derechos. Daniel Correa Placeres consta como autor del TFM; no se atribuye automáticamente la condición de responsable del servicio.',
            'Datos y procedencia' => 'La aplicación trata correo, credenciales protegidas, rol, datos fiscales y de contacto, documentos y datos extraídos o revisados, mensajes y adjuntos, historial de revisiones y cierres y registros técnicos de acceso. Los aportan el usuario, el equipo autorizado de su asesoría o el administrador en las altas e invitaciones. Los documentos pueden incluir datos de terceros.',
            'Finalidades y acceso' => 'Gestionar la cuenta y sus invitaciones, organizar documentación, preparar y revisar registros, mostrar cifras y comunicar al cliente con su asesoría. El equipo accede dentro de su asesoría; el superadmin administra datos principales sin acceso funcional a la actividad privada. No se implementan campañas publicitarias ni perfiles de marketing.',
            'IA y proveedores' => 'Si se configura la extracción real, el adaptador remite el contenido del documento a la API de OpenAI para obtener una propuesta. Sin esa configuración se utiliza el extractor de ejemplo y la interfaz lo identifica. La revisión no es una decisión fiscal automatizada. Deben revisarse antes de producción los contratos, ubicación, conservación y posibles transferencias del proveedor de IA, alojamiento y correo. En local se usan Docker y Mailpit; Hostinger es el destino previsto de una tarea posterior, no un alojamiento productivo ya verificado.',
            'Bases y plazos pendientes' => 'El titular debe concretar para cada finalidad la base jurídica, los plazos de conservación y supresión, las obligaciones aplicables y la relación entre plataforma y asesorías como responsables o encargados. Este borrador no fija plazos ni bases sin esa evaluación. Tampoco sustituye los contratos de encargo o las garantías de transferencias que correspondan.',
            'Tus derechos' => 'Puedes solicitar acceso, rectificación, supresión, oposición, limitación y portabilidad cuando correspondan, y retirar el consentimiento cuando ese sea el fundamento. El canal de ejercicio debe completarse antes de producción. Puedes presentar una reclamación ante la Agencia Española de Protección de Datos. No envíes documentos sensibles a un contacto no verificado.',
            'Antes de utilizar datos reales' => 'Este TFM incluye datos ficticios de demostración. Antes de ofrecer el servicio con datos personales reales deben completarse la identificación, información básica en los formularios de recogida, contratos, medidas y revisión jurídica. El texto no es una declaración de cumplimiento.',
        ]);
    }

    #[Route('/cookies', name: 'commercial_cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->page('Política de cookies', true, [
            'Qué utiliza esta versión' => 'El código revisado utiliza mecanismos técnicos de sesión, protección de formularios y autorización temporal de actualizaciones del trimestre. No se han incorporado analítica, publicidad comportamental ni persistencia del estado de DataTables. Fuentes y librerías de la interfaz se sirven localmente.',
            'Sesión' => 'PHPSESSID en el entorno local identifica la sesión al entrar y utilizar formularios. Su cookie es de sesión del navegador; su validez en servidor depende de la configuración de sesión. El nombre y los atributos definitivos deben comprobarse en el dominio HTTPS de producción.',
            'Protección de formularios' => 'En formularios con CSRF sin estado, el controlador puede crear una cookie técnica de nombre dinámico para contrastar la petición. En HTTPS utiliza el prefijo __Host-. Se elimina al finalizar el envío gestionado por Turbo. Los formularios con tokens de sesión no necesitan crear esa cookie adicional.',
            'Actualizaciones privadas' => 'mercure_access_token en desarrollo autoriza la suscripción del usuario a avisos privados del trimestre; caduca a los 60 segundos y se renueva durante el uso. En producción el hub puede usar un nombre con prefijo seguro según su configuración. El token es HttpOnly y se limita a la ruta del hub. No se utiliza para publicidad.',
            'Finalidad técnica y consentimiento' => 'La clasificación se basa en el uso técnico observado. La guía de la AEPD exceptúa del consentimiento las cookies necesarias para prestar el servicio solicitado, dentro de sus condiciones. Por ello no se añade un panel ficticio de aceptar analítica inexistente. Antes de producción hay que verificar el inventario real y revisar cualquier servicio nuevo; las cookies no exceptuadas requerirán su gestión de consentimiento antes de activarse.',
            'Cómo gestionarlas' => 'Puedes consultar y eliminar cookies desde la configuración de privacidad de tu navegador. Bloquear las necesarias puede impedir entrar, enviar formularios o recibir actualizaciones. Cerrar sesión no garantiza que el navegador borre inmediatamente todos los mecanismos técnicos; la cookie temporal de Mercure caduca según su plazo.',
            'Datos pendientes' => 'La identidad y contacto del responsable siguen pendientes. La política debe revisarse en el despliegue definitivo, con nombres, dominios, duración y proveedores reales. Este inventario de desarrollo no acredita por sí solo el comportamiento de producción.',
        ]);
    }

    /** @param array<string, string> $sections */
    private function page(string $title, bool $draft, array $sections): Response
    {
        return $this->render('commercial/information.html.twig', compact('title', 'draft', 'sections'));
    }
}
