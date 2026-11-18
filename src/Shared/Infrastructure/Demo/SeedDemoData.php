<?php

namespace App\Shared\Infrastructure\Demo;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Comunicacion\Domain\Attachment;
use App\Comunicacion\Domain\Conversation;
use App\Comunicacion\Domain\ConversationRepository;
use App\Comunicacion\Domain\Message;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\Document;
use App\Documento\Domain\DocumentRepository;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\Tax;
use App\Finanzas\Domain\FinancialRecords;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterCalculation;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\FiscalContact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedDemoData
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UserRepository $users, private readonly FirmRepository $firms, private readonly ClientRepository $clients, private readonly DocumentRepository $documents, private readonly QuarterRepository $quarters, private readonly FinancialRecords $records, private readonly ConversationRepository $conversations, private readonly UserPasswordHasherInterface $hasher, private readonly DemoDataset $dataset, #[Autowire('%kernel.project_dir%')] private readonly string $projectDirectory, #[Autowire('%kernel.environment%')] private readonly string $environment)
    {
    }

    public function execute(): bool
    {
        if ('dev' !== $this->environment) {
            throw new \RuntimeException('La demostración solo se carga en desarrollo.');
        }
        $db = $this->em->getConnection();
        $lock = 'aselto-demo-v1-'.sha1((string) $db->getDatabase());
        if (1 !== (int) $db->fetchOne('SELECT GET_LOCK(?, 5)', [$lock])) {
            throw new \RuntimeException('Otra carga de demostración está en marcha.');
        }
        $paths = [];
        try {
            if ($this->alreadyLoaded()) {
                return false;
            }
            $db->transactional(function () use (&$paths): void {
                $this->createIdentities();
                foreach (DemoDataset::clients() as $key => $client) {
                    foreach (['q3-income', 'q3-expense'] as $kind) {
                        $this->createDocument($key, $kind, $paths);
                    }
                    $owner = $this->dataset->id('user/'.$key);
                    $firm = null === $client['firm'] ? null : $this->dataset->id('firm/'.$client['firm']);
                    $period = new QuarterPeriod(2026, 3);
                    $calculation = new QuarterCalculation($owner, $firm, $period, $this->records->forClient($owner, $firm));
                    if ([] !== $calculation->pending) {
                        throw new \RuntimeException('El trimestre de demostración contiene pendientes inesperados.');
                    }
                    $actor = null === $client['firm'] ? $owner : $this->dataset->id('user/admin-'.$client['firm']);
                    $this->quarters->save(Quarter::firstClose($owner, $firm, $period, $calculation->summary, $actor, new \DateTimeImmutable('2026-10-05T10:00:00+00:00')));
                    foreach (array_diff(DemoDataset::documentKinds(null === $client['firm']), ['q3-income', 'q3-expense']) as $kind) {
                        $this->createDocument($key, $kind, $paths);
                    }
                    if (null !== $client['firm']) {
                        foreach (DemoDataset::conversationKinds() as $kind) {
                            $this->createConversation($key, $kind, $paths);
                        }
                    }
                }
            });

            return true;
        } catch (\Throwable $error) {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $this->em->clear();
            throw $error;
        } finally {
            $db->fetchOne('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private function alreadyLoaded(): bool
    {
        $present = [];
        foreach (DemoDataset::firms() as $key => $name) {
            $present[] = null !== $this->firms->find($this->dataset->id('firm/'.$key));
        }
        foreach (DemoDataset::accounts() as $key => $account) {
            $id = $this->dataset->id('user/'.$key);
            $existing = $this->users->findByEmail($this->dataset->email($key));
            if (null !== $existing && $existing->id() !== $id) {
                throw new \RuntimeException('Un correo de demostración ya pertenece a otro usuario. No se ha modificado.');
            }
            $present[] = null !== $this->users->find($id);
        }
        foreach (DemoDataset::clients() as $key => $client) {
            $present[] = null !== $this->clients->find($this->dataset->id('client/'.$key));
            $present[] = null !== $this->quarters->find($this->dataset->id('user/'.$key), null === $client['firm'] ? null : $this->dataset->id('firm/'.$client['firm']), new QuarterPeriod(2026, 3));
            foreach (DemoDataset::documentKinds(null === $client['firm']) as $kind) {
                $present[] = null !== $this->documents->find($this->dataset->id('document/'.$key.'/'.$kind));
            }
            if (null !== $client['firm']) {
                foreach (DemoDataset::conversationKinds() as $kind) {
                    $present[] = null !== $this->conversations->find($this->dataset->id('conversation/'.$key.'/'.$kind));
                }
            }
        }
        if (!in_array(false, $present, true)) {
            return true;
        }
        if (in_array(true, $present, true)) {
            throw new \RuntimeException('La demostración está incompleta o sus identificadores están ocupados. No se sobrescribe ni se repara automáticamente.');
        }

        return false;
    }

    private function createIdentities(): void
    {
        foreach (DemoDataset::firms() as $key => $name) {
            $this->firms->save(new Firm($this->dataset->id('firm/'.$key), $name, $this->contact('sol' === $key ? 'Madrid' : 'Las Palmas', 'sol' === $key ? '28001' : '35001', true)));
        }
        foreach (DemoDataset::accounts() as $key => $account) {
            $user = new User($this->dataset->id('user/'.$key), $this->dataset->email($key), 'pending', $account['role'], null === $account['firm'] ? null : $this->dataset->id('firm/'.$account['firm']), true);
            $this->users->save(new User($user->id(), $user->email(), $this->hasher->hashPassword(SecurityUser::fromDomain($user), DemoDataset::PASSWORD), $user->role(), $user->firmId(), true));
        }
        foreach (DemoDataset::clients() as $key => $client) {
            $this->clients->save(new Client($this->dataset->id('client/'.$key), $this->dataset->id('user/'.$key), $client['kind'], $client['name'], null === $client['firm'] ? null : $this->dataset->id('firm/'.$client['firm']), $this->contact($client['province'], $client['postal'], ClientKind::Company === $client['kind'])));
        }
    }

    private function contact(string $province, string $postal, bool $company): FiscalContact
    {
        return FiscalContact::required($company ? 'B12345674' : '00000000T', '600000000', 'Dirección ficticia de demostración 1', $postal, 'Municipio de ejemplo', $province);
    }

    /** @param list<string> $paths */
    private function createDocument(string $key, string $kind, array &$paths): void
    {
        $client = DemoDataset::clients()[$key];
        $id = $this->dataset->id('document/'.$key.'/'.$kind);
        $owner = $this->dataset->id('user/'.$key);
        $firm = null === $client['firm'] ? null : $this->dataset->id('firm/'.$client['firm']);
        $path = $this->file('documents', $id, $key.' '.$kind, $paths);
        $at = new \DateTimeImmutable(str_starts_with($kind, 'q3') ? '2026-08-15T10:00:00+00:00' : '2026-11-01T10:00:00+00:00');
        $document = Document::receive($id, $owner, $firm, $key.'-'.$kind.'-demo.pdf', 'documents/'.$path, 'application/pdf', $at, $client['tax']);
        if ('undated' !== $kind) {
            $income = str_ends_with($kind, 'income');
            $rate = match ($client['tax']) {
                Tax::Vat => '21', Tax::Igic => '7', Tax::Ipsi => '3',
            };
            $details = DocumentForm::read($income ? 'ingreso' : 'gasto', $at->format('Y-m-d'), 'Contraparte ficticia · Demo', '00000000T', $income ? '1000' : '200', $rate, $client['tax'], !$income && Tax::Vat === $client['tax'] ? '5.2' : '', $income && ClientKind::SelfEmployed === $client['kind'] ? '15' : '');
            $document = $document->propose($details, ProposalOrigin::Sample);
            if ('q4-draft' !== $kind) {
                $document = $document->confirm($details, $at->modify('+1 hour'));
                if (null !== $firm) {
                    $actor = $this->dataset->id('user/admin-'.$client['firm']);
                    $document = match ($kind) {
                        'q4-submitted' => $document,
                        'q4-rejected' => $document->reject('Revisa la fecha del documento ficticio y vuelve a enviarlo.', $actor, $at->modify('+2 hours')),
                        'q4-requested' => $document->requestSupportingDocument('Adjunta el justificante adicional de este ejemplo.', $actor, $at->modify('+2 hours')),
                        default => $document->validate($details, $actor, $at->modify('+2 hours')),
                    };
                }
            }
        }
        $this->documents->save($document);
    }

    /** @param list<string> $paths */
    private function createConversation(string $key, string $kind, array &$paths): void
    {
        $client = DemoDataset::clients()[$key];
        $owner = $this->dataset->id('user/'.$key);
        $firm = $this->dataset->id('firm/'.$client['firm']);
        $actor = $this->dataset->id('user/gestor-'.$client['firm']);
        $at = new \DateTimeImmutable('2026-11-02T10:00:00+00:00');
        $attachments = [];
        if ('conversation' === $kind) {
            $fileId = $this->dataset->id('attachment/'.$key);
            $path = $this->file('messages', $fileId, 'Adjunto ficticio '.$key, $paths);
            $attachments[] = new Attachment($fileId, 'informacion-demo.pdf', $path, 'application/pdf');
        }
        $conversation = new Conversation($this->dataset->id('conversation/'.$key.'/'.$kind), $owner, $firm, match ($kind) {
            'conversation' => 'Consulta del trimestre · Demo', 'pending' => 'Información pendiente · Demo', default => 'Solicitud resuelta · Demo',
        }, 'conversation' !== $kind, $this->dataset->id('document/'.$key.'/q4-submitted'), [new Message($actor, $at, 'Mensaje ficticio para recorrer la comunicación con la asesoría.', $attachments)]);
        $conversation = $conversation->reply(new Message($owner, $at->modify('+1 hour'), 'Respuesta de ejemplo del cliente.'));
        $conversation = $conversation->read($owner, 1);
        if ('resolved' === $kind) {
            $conversation = $conversation->resolve($actor, $at->modify('+2 hours'));
        }
        $this->conversations->save($conversation);
    }

    /** @param list<string> $paths */
    private function file(string $area, string $id, string $label, array &$paths): string
    {
        $directory = $this->projectDirectory.'/var/storage/'.$area;
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se puede preparar el almacenamiento de demostración.');
        }
        $directory = realpath($directory);
        if (false === $directory) {
            throw new \RuntimeException('No se encuentra el almacenamiento de demostración.');
        }
        $path = $directory.'/'.$id.'.pdf';
        $handle = @fopen($path, 'x');
        if (false === $handle) {
            throw new \RuntimeException('Un archivo de demostración ya existe o no se puede crear. No se sobrescribe.');
        }
        $paths[] = $path;
        try {
            $contents = DemoDataset::pdf($label);
            if (strlen($contents) !== fwrite($handle, $contents)) {
                throw new \RuntimeException('No se pudo guardar el PDF de demostración completo.');
            }
        } finally {
            fclose($handle);
        }

        return $id.'.pdf';
    }
}
