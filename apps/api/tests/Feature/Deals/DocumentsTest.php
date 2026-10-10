<?php

namespace Tests\Feature\Deals;

use App\Application\Documents\DocumentService;
use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Identity\Enums\RoleName;
use App\Infrastructure\Documents\ClamdScanner;
use App\Jobs\ScanDocumentVersion;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    private const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    private User $agentUser;

    private User $client;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        $listingAgent = Agent::factory()->create();
        $buyerAgent = Agent::factory()->create();
        $this->agentUser = User::find($listingAgent->user_id)->fresh();
        $property = Property::factory()->forAgent($listingAgent)->published()->create();
        $offer = Offer::query()->create([
            'property_id' => $property->id, 'buyer_agent_id' => $buyerAgent->id, 'buyer_name' => 'Jordan', 'amount' => 500000, 'financing' => 'cash', 'side' => 'buyer', 'created_by' => $this->agentUser->id,
        ]);
        $offer->forceFill(['status' => 'accepted'])->save();
        $this->transaction = Transaction::query()->create([
            'property_id' => $property->id, 'offer_id' => $offer->id, 'listing_agent_id' => $listingAgent->id, 'buyer_agent_id' => $buyerAgent->id,
            'sale_price' => 500000, 'contract_date' => '2027-06-01', 'created_by' => $this->agentUser->id,
        ]);
        $this->client = $this->userWithRole(RoleName::Seller);
        $this->transaction->participants()->create(['role' => 'seller', 'name' => 'Sam Seller', 'user_id' => $this->client->id]);
    }

    private function upload(string $visibility = 'internal', ?UploadedFile $file = null)
    {
        return $this->post("/api/v1/transactions/{$this->transaction->id}/documents", [
            'file' => $file ?? UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n% test contract\n"),
            'title' => 'Contract of sale', 'category' => 'contract', 'visibility' => $visibility,
        ], ['Accept' => 'application/json']);
    }

    public function test_agents_upload_version_and_download_through_signed_links(): void
    {
        Sanctum::actingAs($this->agentUser);
        $doc = $this->upload()->assertCreated()->assertJsonPath('data.versions.0.version', 1)
            ->assertJsonPath('data.versions.0.scan_status', 'unscanned')->assertJsonMissingPath('data.versions.0.path')->json('data');
        $stored = DocumentVersion::query()->sole();
        Storage::disk('local')->assertExists($stored->path);
        $this->assertStringStartsWith("documents/{$this->transaction->id}/", $stored->path);
        $this->assertSame(hash('sha256', "%PDF-1.4\n% test contract\n"), $stored->sha256);

        $this->post("/api/v1/documents/{$doc['id']}/versions", ['file' => UploadedFile::fake()->createWithContent('contract-v2.pdf', "%PDF-1.4\n% v2\n")], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.versions.0.version', 2)->assertJsonCount(2, 'data.versions');

        $versionId = DocumentVersion::query()->where('version', 2)->value('id');
        $url = $this->postJson("/api/v1/documents/{$doc['id']}/versions/{$versionId}/link")->assertOk()->json('data.url');
        $this->assertStringContainsString('signature=', $url);

        $response = $this->get($url)->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame("%PDF-1.4\n% v2\n", $response->streamedContent());
        $this->assertSame(['upload', 'upload', 'link_issued', 'download'], DB::table('document_access_logs')->orderBy('id')->pluck('action')->all());

        // Tampered or expired links are refused.
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_clients_only_see_shared_documents_and_cannot_upload(): void
    {
        Sanctum::actingAs($this->agentUser);
        $internal = $this->upload('internal')->assertCreated()->json('data.id');
        $shared = $this->upload('shared')->assertCreated()->json('data.id');

        Sanctum::actingAs($this->client);
        $this->getJson("/api/v1/transactions/{$this->transaction->id}/documents")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $shared)->assertJsonPath('meta.can_upload', false);
        $internalVersion = Document::find($internal)->versions()->value('id');
        $sharedVersion = Document::find($shared)->versions()->value('id');
        $this->postJson("/api/v1/documents/{$internal}/versions/{$internalVersion}/link")->assertForbidden();
        $this->postJson("/api/v1/documents/{$shared}/versions/{$sharedVersion}/link")->assertOk();
        $this->upload('shared')->assertForbidden();
        $this->patchJson("/api/v1/documents/{$shared}", ['visibility' => 'internal'])->assertForbidden();

        // Strangers see nothing.
        $this->actingAsRole(RoleName::Buyer);
        $this->getJson("/api/v1/transactions/{$this->transaction->id}/documents")->assertForbidden();
        $this->actingAs(User::find(Agent::factory()->create()->user_id)->fresh(), 'sanctum');
        $this->getJson("/api/v1/transactions/{$this->transaction->id}/documents")->assertForbidden();

        // Changing visibility and deleting are audited.
        Sanctum::actingAs($this->agentUser);
        $this->patchJson("/api/v1/documents/{$internal}", ['visibility' => 'shared'])->assertOk()->assertJsonPath('data.visibility', 'shared');
        $this->deleteJson("/api/v1/documents/{$internal}")->assertNoContent();
        $this->assertSoftDeleted('documents', ['id' => $internal]);
        $this->assertSame(1, AuditLog::query()->where('event', 'documents.visibility_changed')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'documents.deleted')->count());
    }

    public function test_uploads_are_validated(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->upload('internal', UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'))->assertStatus(422);
        $this->upload('internal', UploadedFile::fake()->create('huge.pdf', 30000, 'application/pdf'))->assertStatus(422);
        $this->post("/api/v1/transactions/{$this->transaction->id}/documents", ['title' => '', 'category' => 'x', 'visibility' => 'public'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['fields' => ['file', 'title', 'category', 'visibility']]]]);
    }

    public function test_with_a_scanner_files_are_downloadable_only_after_a_clean_scan(): void
    {
        config(['app_inmobiliaria.documents.scanner' => 'clamd']);
        Queue::fake();
        Sanctum::actingAs($this->agentUser);
        $doc = $this->upload()->assertCreated()->assertJsonPath('data.versions.0.scan_status', 'pending')->json('data.id');
        Queue::assertPushed(ScanDocumentVersion::class);
        $version = DocumentVersion::query()->sole();

        $this->postJson("/api/v1/documents/{$doc}/versions/{$version->id}/link")->assertStatus(409)->assertJsonPath('error.code', 'document_unavailable');

        $version->forceFill(['scan_status' => 'infected'])->save();
        $this->postJson("/api/v1/documents/{$doc}/versions/{$version->id}/link")->assertForbidden()->assertJsonPath('error.code', 'document_unavailable');

        $version->forceFill(['scan_status' => 'clean'])->save();
        $this->postJson("/api/v1/documents/{$doc}/versions/{$version->id}/link")->assertOk();
    }

    public function test_clamd_scanner_speaks_instream(): void
    {
        $portFile = tempnam(sys_get_temp_dir(), 'clamd');
        unlink($portFile);
        $process = proc_open([PHP_BINARY, base_path('tests/Fixtures/fake-clamd.php'), $portFile], [], $pipes);
        try {
            $deadline = microtime(true) + 5;
            while (! is_file($portFile) || filesize($portFile) === 0) {
                $this->assertLessThan($deadline, microtime(true), 'fake clamd did not start');
                usleep(20_000);
            }
            $scanner = new ClamdScanner('127.0.0.1', (int) file_get_contents($portFile), 5);

            $clean = fopen('php://memory', 'r+');
            fwrite($clean, str_repeat('safe document ', 10000));
            rewind($clean);
            $this->assertSame('clean', $scanner->scan($clean)[0]->value);

            $infected = fopen('php://memory', 'r+');
            fwrite($infected, self::EICAR);
            rewind($infected);
            [$status, $signature] = $scanner->scan($infected);
            $this->assertSame('infected', $status->value);
            $this->assertSame('Eicar-Test-Signature', $signature);
        } finally {
            proc_terminate($process);
            proc_close($process);
            @unlink($portFile);
        }

        // Unreachable daemon → error (never "clean").
        $this->assertSame('error', (new ClamdScanner('127.0.0.1', 1, 1))->scan(fopen('php://memory', 'r'))[0]->value);
    }

    public function test_infected_scans_are_recorded_as_security_events(): void
    {
        config(['app_inmobiliaria.documents.scanner' => 'clamd']);
        Queue::fake();
        Sanctum::actingAs($this->agentUser);
        $this->upload('internal', UploadedFile::fake()->createWithContent('bad.pdf', self::EICAR))->assertCreated();
        $version = DocumentVersion::query()->sole();

        $this->app->bind(DocumentScanner::class, fn () => new class implements DocumentScanner
        {
            public function scan($stream): array
            {
                return [ScanStatus::Infected, 'Eicar-Test-Signature'];
            }

            public function isConfigured(): bool
            {
                return true;
            }
        });
        (new ScanDocumentVersion($version))->handle(app(DocumentService::class));
        $this->assertSame('infected', $version->fresh()->scan_status->value);
        $this->assertTrue(AuditLog::query()->where('event', 'documents.malware_detected')->exists());
    }

    public function test_purge_is_a_dry_run_unless_forced(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->upload()->assertCreated();
        $version = DocumentVersion::query()->sole();
        Document::query()->update(['retain_until' => '2020-01-01']);

        $this->artisan('app-inmobiliaria:documents:purge')->expectsOutputToContain('1 document(s) past retention (dry run')->assertSuccessful();
        Storage::disk('local')->assertExists($version->path);

        $this->artisan('app-inmobiliaria:documents:purge --force')->expectsOutput('Purged 1 document(s).')->assertSuccessful();
        Storage::disk('local')->assertMissing($version->path);
        $this->assertSame(0, Document::withTrashed()->count());
    }
}
