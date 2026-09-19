<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\ReceiptClosedException;
use App\Exceptions\Domain\ReceiptLinesPendingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiptCloseRequest;
use App\Http\Requests\ReceiptIndexRequest;
use App\Http\Requests\ReceiptScanRequest;
use App\Http\Resources\ReceiptListResource;
use App\Http\Resources\ReceiptResource;
use App\Models\AuditLog;
use App\Models\Receipt;
use App\Services\PutawaySuggester;
use App\Services\ReceiptScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptScanService $scans,
        private readonly PutawaySuggester $suggester,
    ) {}

    public function index(ReceiptIndexRequest $request): JsonResponse
    {
        $statuses = $request->statuses();

        $receipts = Receipt::query()
            ->where('warehouse_id', $request->integer('warehouse_id'))
            ->when($statuses !== [], fn ($q) => $q->whereIn('status', $statuses))
            ->withCount([
                'lines as lines_total',
                'lines as lines_done' => fn ($q) => $q->whereIn('status', ['COMPLETA', 'EXCEDIDA']),
            ])
            ->orderBy('expected_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => ReceiptListResource::collection($receipts),
        ]);
    }

    public function show(Request $request, int $receipt): JsonResponse
    {
        $model = Receipt::with(['lines.item.baseUom', 'lines.uom'])->findOrFail($receipt);

        // Sugerencias de ubicacion por linea (ubicacion fija > mismo item > vacia cercana).
        $model->lines->each(function ($line) use ($model) {
            $line->suggested_locations = $this->suggester->suggest(
                (int) $model->warehouse_id,
                (int) $line->item_id,
            );
        });

        return response()->json([
            'data' => new ReceiptResource($model),
        ]);
    }

    /** El posteo. Idempotente por client_uuid. */
    public function scans(ReceiptScanRequest $request, int $receipt): JsonResponse
    {
        $model = Receipt::findOrFail($receipt);

        $result = $this->scans->post($model, $request->validated(), $request->user());

        return response()->json($result['body'], $result['status']);
    }

    public function close(ReceiptCloseRequest $request, int $receipt): JsonResponse
    {
        $model = Receipt::with('lines')->findOrFail($receipt);

        if (! in_array($model->status, Receipt::OPEN_STATUSES, true)) {
            throw new ReceiptClosedException($model->number, $model->status);
        }

        $user = $request->user();

        if (! in_array('receipt.close', $user->permissions(), true)) {
            abort(403, 'El usuario no puede cerrar notas de ingreso');
        }

        $pending = $model->lines
            ->filter(fn ($line) => ! in_array($line->status, ['COMPLETA', 'EXCEDIDA'], true))
            ->pluck('id')
            ->all();

        $force = $request->boolean('force');

        if ($pending !== [] && ! $force) {
            throw new ReceiptLinesPendingException($pending);
        }

        $before = $model->only(['status', 'closed_at', 'closed_by']);

        $model->forceFill([
            'status' => 'CERRADA',
            'closed_at' => Carbon::now(),
            'closed_by' => $user->id,
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'model' => Receipt::class,
            'model_id' => $model->id,
            'action' => $force ? 'CIERRE_FORZADO' : 'CIERRE',
            'before' => $before,
            'after' => array_merge($model->only(['status', 'closed_at', 'closed_by']), [
                'reason' => $request->input('reason'),
                'pending_line_ids' => $pending,
            ]),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'data' => new ReceiptResource($model->fresh()),
        ]);
    }
}
