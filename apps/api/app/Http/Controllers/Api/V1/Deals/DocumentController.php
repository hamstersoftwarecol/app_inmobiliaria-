<?php

namespace App\Http\Controllers\Api\V1\Deals;

use App\Application\Documents\DocumentService;
use App\Domain\Documents\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Transaction;
use App\Policies\DocumentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const MIMES = 'pdf,jpg,jpeg,png,docx,xlsx';

    public function __construct(private readonly DocumentService $documents, private readonly DocumentPolicy $policy) {}

    public function index(Request $request, Transaction $transaction): AnonymousResourceCollection
    {
        Gate::authorize('viewAnyFor', [Document::class, $transaction]);
        $internal = $this->policy->seesInternal($request->user(), $transaction);

        $list = $transaction->documents()->with(['versions.uploader'])
            ->when(! $internal, fn ($q) => $q->where('visibility', 'shared'))
            ->orderBy('category')->orderBy('title')->get();

        return DocumentResource::collection($list)->additional(['meta' => [
            'can_upload' => Gate::allows('createFor', [Document::class, $transaction]),
            'scan_required' => $this->documents->scanRequired(),
            'max_kb' => (int) config('hitlease.documents.max_kilobytes'),
        ]]);
    }

    public function store(Request $request, Transaction $transaction): JsonResponse
    {
        Gate::authorize('createFor', [Document::class, $transaction]);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:'.self::MIMES, 'max:'.(int) config('hitlease.documents.max_kilobytes')],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'visibility' => ['required', Rule::in(['internal', 'shared'])],
        ]);

        $document = $this->documents->upload($request->user(), $transaction, $request->file('file'), $data['title'], DocumentCategory::from($data['category']), $data['visibility']);

        return (new DocumentResource($document->load('versions.uploader')))->response()->setStatusCode(201);
    }

    public function storeVersion(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('manage', $document);
        $request->validate(['file' => ['required', 'file', 'mimes:'.self::MIMES, 'max:'.(int) config('hitlease.documents.max_kilobytes')]]);
        $this->documents->addVersion($request->user(), $document, $request->file('file'));

        return (new DocumentResource($document->load('versions.uploader')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Document $document): DocumentResource
    {
        Gate::authorize('manage', $document);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'category' => ['sometimes', Rule::enum(DocumentCategory::class)],
            'visibility' => ['sometimes', Rule::in(['internal', 'shared'])],
        ]);

        return new DocumentResource($this->documents->update($request->user(), $document, $data)->load('versions.uploader'));
    }

    public function destroy(Request $request, Document $document): Response
    {
        Gate::authorize('manage', $document);
        $this->documents->delete($request->user(), $document);

        return response()->noContent();
    }

    /** Issues a short-lived signed URL for one version. */
    public function link(Request $request, Document $document, DocumentVersion $version): JsonResponse
    {
        Gate::authorize('view', $document);
        abort_unless($version->document_id === $document->id, 404);
        $url = $this->documents->downloadLink($request->user(), $version, $request->ip(), $request->userAgent());

        return response()->json(['data' => [
            'url' => $url,
            'expires_at' => now()->addMinutes((int) config('hitlease.documents.link_minutes', 5))->toIso8601String(),
        ]]);
    }

    /** Signed-URL download (no session needed: the signature is the credential and expires quickly). */
    public function download(Request $request, DocumentVersion $version): StreamedResponse
    {
        abort_if($version->document === null || $version->document->trashed(), 404);

        return $this->documents->stream($version, $request->integer('u') ?: null, $request->ip(), $request->userAgent());
    }
}
