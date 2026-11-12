<?php

// Explicit local check: synthetic accounts and fiscal data roll back; created files are removed.
require dirname(__DIR__).'/bootstrap.php';

use App\Asesoria\Domain\Firm;
use App\Asesoria\Infrastructure\Persistence\DoctrineFirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Infrastructure\Persistence\DoctrineClientRepository;
use App\Documento\Application\DocumentForm;
use App\Documento\Domain\DocumentExtractor;
use App\Documento\Domain\Proposal;
use App\Documento\Domain\ProposalOrigin;
use App\Documento\Domain\Tax;
use App\Documento\Infrastructure\Persistence\DoctrineDocumentRepository;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterCalculation;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Infrastructure\Persistence\DoctrineFinancialRecords;
use App\Finanzas\Infrastructure\Persistence\DoctrineQuarterRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Persistence\DoctrineUserRepository;
use App\Kernel;
use App\Shared\Domain\FiscalContact;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class SyntheticApiDocumentExtractor implements DocumentExtractor
{
    public function propose(string $mediaType, string $contents, Tax $tax = Tax::Vat, bool $allowsWithholding = false): Proposal
    {
        return new Proposal(DocumentForm::read('gasto', '2026-11-01', 'Proveedor sintético', '00000000T', '100', match ($tax) {
            Tax::Vat => '21', Tax::Igic => '7', Tax::Ipsi => '3',
        }, $tax), ProposalOrigin::Sample);
    }
}

final class ApiDocumentsCheckKernel extends Kernel
{
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $container): void
            {
                $container->getDefinition('security.token_storage')->setPublic(true);
                $container->register(SyntheticApiDocumentExtractor::class, SyntheticApiDocumentExtractor::class);
                $container->setAlias(DocumentExtractor::class, SyntheticApiDocumentExtractor::class);
            }
        });
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'/api-documents-check';
    }
}

$kernel = new ApiDocumentsCheckKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$before = hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) { throw new RuntimeException($message); } ++$checks; };
$uuid = static fn (): string => Uuid::v4()->toRfc4122();
$owners = [];
$temporary = [];
$documents = new DoctrineDocumentRepository($em);
$db->beginTransaction();
try {
    $users = new DoctrineUserRepository($em);
    $clients = new DoctrineClientRepository($em);
    $firms = new DoctrineFirmRepository($em);
    $firmId = $uuid();
    $otherFirmId = $uuid();
    $firms->save(new Firm($firmId, 'Asesoría sintética documentos API'));
    $firms->save(new Firm($otherFirmId, 'Otra asesoría sintética documentos API'));
    $password = 'synthetic-api-password';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    $accounts = [];
    foreach (['personal', 'linked', 'igic', 'ipsi', 'company', 'admin', 'advisor', 'other', 'root'] as $name) {
        $role = match ($name) {
            'admin' => Role::FirmAdmin, 'advisor', 'other' => Role::Advisor, 'root' => Role::Superadmin, default => Role::Client,
        };
        $firm = match ($name) {
            'linked', 'admin', 'advisor' => $firmId, 'other' => $otherFirmId, default => null,
        };
        $user = new User($uuid(), $uuid().'@example.test', $hash, $role, $firm, true);
        $users->save($user);
        $accounts[$name] = $user;
        if (Role::Client === $role) {
            [$province, $postal] = match ($name) {
                'igic' => ['Las Palmas', '35001'], 'ipsi' => ['Ceuta', '51001'], default => ['Madrid', '28001'],
            };
            $clients->save(new Client($uuid(), $user->id(), 'company' === $name ? ClientKind::Company : ClientKind::SelfEmployed, 'Cliente sintético documentos API', $firm, FiscalContact::required('00000000T', '600000000', 'Dirección sintética', $postal, 'Municipio de ejemplo', $province)));
            $owners[] = $user->id();
        }
    }
    $http = static function (string $url, string $method = 'GET', ?array $data = null, ?string $token = null, array $uploads = [], bool $multipart = false) use ($kernel, $em): Symfony\Component\HttpFoundation\Response {
        $em->clear();
        $kernel->getContainer()->get('security.token_storage')->setToken(null);
        $server = ['CONTENT_TYPE' => $multipart ? 'multipart/form-data' : 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $request = Request::create('http://localhost'.$url, $method, $multipart ? ($data ?? []) : [], files: $uploads, server: $server, content: $multipart || null === $data ? null : json_encode($data, JSON_THROW_ON_ERROR));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    };
    $json = static fn (Symfony\Component\HttpFoundation\Response $response): array => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $tokens = [];
    foreach ($accounts as $name => $user) {
        $response = $http('/api/v1/auth/login', 'POST', ['email' => $user->email(), 'password' => $password]);
        $check(200 === $response->getStatusCode(), 'Synthetic account login failed.');
        $tokens[$name] = $json($response)['token'];
    }
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    $temporary[] = $source = tempnam(sys_get_temp_dir(), 'api-document-');
    file_put_contents($source, $pdf);
    $upload = static fn (): array => ['file' => new UploadedFile($source, 'áé 100%.pdf', 'application/pdf', UPLOAD_ERR_OK, true)];
    $uploadFor = static fn (string $name): Symfony\Component\HttpFoundation\Response => $http('/api/v1/documents', 'POST', [], $tokens[$name], $upload(), true);
    $check(401 === $http('/api/v1/documents')->getStatusCode(), 'Document routes require JWT.');
    foreach (['admin', 'advisor', 'root'] as $name) {
        $check(403 === $http('/api/v1/documents', token: $tokens[$name])->getStatusCode(), 'Only clients use owner routes.');
    }
    foreach (['personal', 'linked', 'root'] as $name) {
        $check(403 === $http('/api/v1/advisory/documents', token: $tokens[$name])->getStatusCode(), 'Only advisory staff use advisory routes.');
    }
    $response = $uploadFor('personal');
    $check(201 === $response->getStatusCode(), 'A valid original upload must create a document.');
    $personal = $json($response);
    $personalId = $personal['id'];
    $ownerUrl = '/api/v1/documents/'.$personalId;
    $check('draft' === $personal['status'] && 'sample' === $personal['proposal'] && 1 === $personal['version'], 'Extraction must remain a proposal, with the stored version.');
    $check('iva' === $personal['tax'] && !str_contains($response->getContent(), 'stored_path') && !str_contains($response->getContent(), '/var/storage'), 'Stored paths must never be exposed.');
    $check($ownerUrl === $response->headers->get('Location'), 'Creation must return the document location.');
    $file = $http($personal['file_url'], token: $tokens['personal']);
    $check(200 === $file->getStatusCode() && $pdf === $file->getContent() && 'nosniff' === $file->headers->get('X-Content-Type-Options'), 'Owner original download must be protected and byte-exact.');
    $check(str_contains($file->headers->get('Cache-Control'), 'no-store'), 'Downloads must not be cached.');
    $check(404 === $http($ownerUrl, token: $tokens['linked'])->getStatusCode() && 404 === $http($personal['file_url'], token: $tokens['linked'])->getStatusCode(), 'Another client must not read an original or its metadata.');
    $check(404 === $http('/api/v1/advisory/documents/'.$personalId, token: $tokens['admin'])->getStatusCode(), 'Personal originals must not be shared with an advisory.');
    $response = $http($ownerUrl.'/draft', 'PUT', ['version' => 1], $tokens['personal']);
    $check(200 === $response->getStatusCode() && 2 === $json($response)['version'] && null === $json($response)['base_cents'], 'Saving a partial draft must clear omitted fields and return the new version.');
    $response = $http($ownerUrl.'/confirm', 'POST', ['version' => 1], $tokens['personal']);
    $check(409 === $response->getStatusCode() && 'document_changed' === $json($response)['error']['code'], 'Stale mutations must return conflict.');
    $response = $http($ownerUrl.'/confirm', 'POST', ['version' => 2], $tokens['personal']);
    $check(422 === $response->getStatusCode() && in_array('issued_on', $json($response)['error']['fields'], true), 'Incomplete confirmation must name missing API fields.');
    $input = ['version' => 2, 'direction' => 'income', 'issued_on' => '2026-11-01', 'party_name' => 'Ejemplo sintético', 'tax_id' => '00000000T', 'base' => '100.00', 'tax_rate' => '21', 'withholding_rate' => '15'];
    foreach ([['base' => 100.50], ['owner_id' => $accounts['linked']->id()], ['tax' => 'ipsi'], ['version' => '2']] as $invalid) {
        $check(400 === $http($ownerUrl.'/confirm', 'POST', [...$input, ...$invalid], $tokens['personal'])->getStatusCode(), 'Unknown fields and numeric amounts must be rejected.');
    }
    $check(422 === $http($ownerUrl.'/confirm', 'POST', [...$input, 'base' => '1,000'], $tokens['personal'])->getStatusCode(), 'Ambiguous decimal commas must be rejected.');
    $check(422 === $http($ownerUrl.'/confirm', 'POST', [...$input, 'tax_rate' => '7'], $tokens['personal'])->getStatusCode(), 'A territory-incompatible tax rate must be rejected.');
    $response = $http($ownerUrl.'/confirm', 'POST', $input, $tokens['personal']);
    $personal = $json($response);
    $check(200 === $response->getStatusCode() && 'submitted' === $personal['status'] && 3 === $personal['version'] && 1500 === $personal['withholding_cents'] && 10600 === $personal['total_cents'], 'Confirmation must use existing withholding calculations.');
    $response = $http($ownerUrl.'/amend', 'POST', ['version' => 3], $tokens['personal']);
    $check(200 === $response->getStatusCode() && 'draft' === $json($response)['status'] && 4 === $json($response)['version'], 'Personal correction must explicitly return to draft.');
    $expense = [...$input, 'version' => 4, 'direction' => 'expense', 'withholding_rate' => null, 'surcharge_rate' => '5.2'];
    $response = $http($ownerUrl.'/confirm', 'POST', $expense, $tokens['personal']);
    $check(200 === $response->getStatusCode() && 12620 === $json($response)['total_cents'] && 520 === $json($response)['surcharge_cents'] && 3 === count($json($response)['history']), 'Reconfirmation must keep history and use surcharge calculations.');
    $period = new QuarterPeriod(2026, 4);
    $summary = (new QuarterCalculation($accounts['personal']->id(), null, $period, (new DoctrineFinancialRecords($em))->forClient($accounts['personal']->id(), null)))->summary;
    (new DoctrineQuarterRepository($em))->save(Quarter::firstClose($accounts['personal']->id(), null, $period, $summary, $accounts['personal']->id(), new DateTimeImmutable()));
    $response = $http($ownerUrl.'/amend', 'POST', ['version' => 5], $tokens['personal']);
    $check(409 === $response->getStatusCode() && 'quarter_closed' === $json($response)['error']['code'], 'A closed personal period must block corrections.');
    $late = $uploadFor('personal');
    $check(201 === $late->getStatusCode(), 'Late uploads must remain possible without changing a frozen quarter.');
    $lateId = $json($late)['id'];
    $check(409 === $http('/api/v1/documents/'.$lateId.'/confirm', 'POST', [...$input, 'version' => 1], $tokens['personal'])->getStatusCode(), 'Confirmation in a closed quarter must fail.');
    $check(409 === $http('/api/v1/documents/'.$lateId.'/draft', 'PUT', [...$input, 'version' => 1, 'issued_on' => '2027-01-01'], $tokens['personal'])->getStatusCode(), 'Moving a record out of a closed quarter must fail.');

    $linked = $json($uploadFor('linked'));
    $linkedUrl = '/api/v1/documents/'.$linked['id'];
    $staffUrl = '/api/v1/advisory/documents/'.$linked['id'];
    $check(404 === $http($staffUrl, token: $tokens['admin'])->getStatusCode() && 404 === $http($staffUrl.'/file', token: $tokens['admin'])->getStatusCode(), 'Advisory staff must not see client drafts.');
    $expense = [...$expense, 'version' => 1];
    $response = $http($linkedUrl.'/confirm', 'POST', $expense, $tokens['linked']);
    $check(200 === $response->getStatusCode() && 2 === $json($response)['version'], 'Linked clients must submit for review.');
    $list = $json($http('/api/v1/advisory/documents', token: $tokens['advisor']));
    $check(1 === count($list['documents']) && $linked['id'] === $list['documents'][0]['id'], 'The advisory list must contain only its shared records.');
    $check(404 === $http($staffUrl, token: $tokens['other'])->getStatusCode() && 404 === $http($staffUrl.'/file', token: $tokens['other'])->getStatusCode(), 'Other advisories must not read records or files.');
    $check(404 === $http($staffUrl.'/validate', 'POST', [...$expense, 'version' => 2], $tokens['other'])->getStatusCode(), 'Other advisories must not validate.');
    $check(422 === $http($staffUrl.'/request-supporting-file', 'POST', ['version' => 2, 'reason' => ''], $tokens['admin'])->getStatusCode(), 'Supporting requests require a reason.');
    $response = $http($staffUrl.'/request-supporting-file', 'POST', ['version' => 2, 'reason' => 'Aporta otro justificante sintético.'], $tokens['advisor']);
    $check(200 === $response->getStatusCode() && 3 === $json($response)['version'] && null !== $json($response)['pending_request'], 'A supporting request must persist reason and version.');
    $check(422 === $http($staffUrl.'/validate', 'POST', [...$expense, 'version' => 3], $tokens['admin'])->getStatusCode(), 'Pending evidence must block validation.');
    $response = $http($linkedUrl.'/supporting-files', 'POST', ['version' => '2'], $tokens['linked'], $upload(), true);
    $check(409 === $response->getStatusCode(), 'Supporting uploads must check the submitted version.');
    $response = $http($linkedUrl.'/supporting-files', 'POST', ['version' => '3'], $tokens['linked'], $upload(), true);
    $evidence = $json($response);
    $check(201 === $response->getStatusCode() && 4 === $evidence['version'] && 'submitted' === $evidence['status'] && null === $evidence['pending_request'], 'Evidence must clear the request without validating.');
    $check(1 === count($evidence['supporting_files']) && $linked['original_name'] === $evidence['original_name'], 'Evidence must retain the original and history.');
    $supportId = $evidence['supporting_files'][0]['id'];
    $check($pdf === $http($evidence['supporting_files'][0]['url'], token: $tokens['linked'])->getContent(), 'Owner evidence download must be byte-exact.');
    $check($pdf === $http($staffUrl.'/supporting-files/'.$supportId, token: $tokens['admin'])->getContent(), 'Advisory evidence download must be permitted.');
    $check(404 === $http($staffUrl.'/supporting-files/'.$supportId, token: $tokens['other'])->getStatusCode(), 'Other advisories must not download evidence.');
    $response = $http($staffUrl.'/reject', 'POST', ['version' => 4, 'reason' => 'Corrige el importe sintético.'], $tokens['admin']);
    $check(200 === $response->getStatusCode() && 'rejected' === $json($response)['status'] && 5 === $json($response)['version'], 'Rejection must preserve a resubmittable record.');
    $db->executeStatement('UPDATE client_profile SET province = ?, postal_code = ? WHERE user_id = ?', ['Las Palmas', '35001', $accounts['linked']->id()]);
    $response = $http($linkedUrl.'/draft', 'PUT', [...$expense, 'version' => 5, 'base' => '120'], $tokens['linked']);
    $check(200 === $response->getStatusCode() && 6 === $json($response)['version'] && 'iva' === $json($response)['tax'], 'Profile changes must not alter the stored document tax.');
    $response = $http($linkedUrl.'/confirm', 'POST', [...$expense, 'version' => 6, 'base' => '120'], $tokens['linked']);
    $check(200 === $response->getStatusCode() && 7 === $json($response)['version'], 'A rejected record must be resubmitted on the same id.');
    $response = $http($staffUrl.'/validate', 'POST', [...$expense, 'version' => 7, 'base' => '125'], $tokens['advisor']);
    $validated = $json($response);
    $check(200 === $response->getStatusCode() && 'validated' === $validated['status'] && 8 === $validated['version'] && 15775 === $validated['total_cents'], 'Staff validation must apply corrections and surcharge.');
    $last = $validated['history'][array_key_last($validated['history'])];
    $check(12000 === $last['before']['base_cents'] && 12500 === $last['after']['base_cents'], 'Validation history must retain previous and corrected values.');
    $check(422 === $http($linkedUrl.'/draft', 'PUT', [...$expense, 'version' => 8], $tokens['linked'])->getStatusCode(), 'Clients must not edit validated records.');
    foreach (['igic' => ['7', 700, 10700], 'ipsi' => ['3.5', 350, 10350]] as $name => [$rate, $quota, $total]) {
        $created = $json($uploadFor($name));
        $check($name === $created['tax'], 'New uploads must use the profile territory.');
        $response = $http('/api/v1/documents/'.$created['id'].'/confirm', 'POST', [...$input, 'version' => 1, 'tax_rate' => $rate, 'withholding_rate' => null], $tokens[$name]);
        $check(200 === $response->getStatusCode() && $quota === $json($response)['tax_cents'] && $total === $json($response)['total_cents'], 'Territorial rates must use the existing calculation.');
    }
    $company = $json($uploadFor('company'));
    $check(422 === $http('/api/v1/documents/'.$company['id'].'/confirm', 'POST', [...$input, 'version' => 1], $tokens['company'])->getStatusCode(), 'Company income must not accept personal income withholding.');
    $check(400 === $http($linkedUrl.'/draft', 'PUT', ['base' => '100'], $tokens['linked'])->getStatusCode(), 'Mutations must require version.');
    $check(415 === $http('/api/v1/documents', 'POST', [], $tokens['linked'])->getStatusCode(), 'Original uploads must require multipart.');
    $check(400 === $http('/api/v1/documents', 'POST', ['owner_id' => $accounts['personal']->id()], $tokens['linked'], $upload(), true)->getStatusCode(), 'Upload ownership must be server-determined.');
    $future = $json($uploadFor('linked'));
    $futureUrl = '/api/v1/documents/'.$future['id'];
    $futureInput = [...$input, 'direction' => 'expense', 'withholding_rate' => null, 'tax_rate' => '7', 'issued_on' => '2027-01-01', 'version' => 1];
    $check(200 === $http($futureUrl.'/confirm', 'POST', $futureInput, $tokens['linked'])->getStatusCode(), 'A pending record in another quarter must remain independent.');
    $linkedOwner = $accounts['linked']->id();
    $firmSummary = (new QuarterCalculation($linkedOwner, $firmId, $period, (new DoctrineFinancialRecords($em))->forClient($linkedOwner, $firmId)))->summary;
    (new DoctrineQuarterRepository($em))->save(Quarter::firstClose($linkedOwner, $firmId, $period, $firmSummary, $accounts['admin']->id(), new DateTimeImmutable()));
    $response = $http('/api/v1/advisory/documents/'.$future['id'].'/validate', 'POST', [...$futureInput, 'version' => 2, 'issued_on' => '2026-11-01'], $tokens['advisor']);
    $check(409 === $response->getStatusCode() && 'quarter_closed' === $json($response)['error']['code'], 'Advisory corrections must not move a record into a closed quarter.');
    $check(404 === $http($linkedUrl.'/supporting-files/'.$supportId, token: $tokens['personal'])->getStatusCode(), 'Another client must not read supporting evidence.');
    $check(422 === $http('/api/v1/documents', 'POST', [], $tokens['linked'], [], true)->getStatusCode(), 'A missing original file must be rejected.');
    file_put_contents($source, '<html>not a PDF</html>');
    $check(422 === $uploadFor('linked')->getStatusCode(), 'A misleading file name must not override its detected media type.');
    file_put_contents($source, $pdf.str_repeat('x', 8_000_001 - strlen($pdf)));
    clearstatcache(true, $source);
    $check(413 === $uploadFor('linked')->getStatusCode(), 'A file over eight million bytes must be rejected.');
    echo 'API document integration checks: '.$checks." passed.\n";
} finally {
    $paths = [];
    foreach ($owners as $owner) {
        foreach ($documents->findByOwner($owner) as $document) {
            $paths[] = $document->storedPath();
            foreach ($document->supportingFiles() as $file) {
                $paths[] = $file->storedPath;
            }
        }
    }
    $db->rollBack();
    $em->clear();
    $storage = realpath(dirname(__DIR__, 2).'/var/storage');
    foreach ($paths as $relative) {
        $absolute = false === $storage ? false : realpath($storage.'/'.$relative);
        if (false !== $absolute) {
            if (!str_starts_with($absolute, $storage.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Unexpected fixture cleanup path.');
            } unlink($absolute);
        }
    }
    foreach ($temporary as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    $check($before === hash('sha256', json_encode($db->fetchAllAssociative('SELECT * FROM client_document ORDER BY id'), JSON_THROW_ON_ERROR)), 'Existing documents changed after the test.');
    $kernel->shutdown();
}
echo "Synthetic data and files removed; no external extraction or credentials printed.\n";
