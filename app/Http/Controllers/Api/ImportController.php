<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function upload(Request $request, ImportService $importService): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
            'source' => 'nullable|string|max:100',
            'source_detail' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $rows = $this->parseFile($file);

        if (empty($rows)) {
            return response()->json(['error' => 'Aucune ligne valide trouvée dans le fichier'], 422);
        }

        $batch = $importService->importBatch(
            $rows,
            $file->getClientOriginalName(),
            $request->input('source', 'manual'),
            $request->input('source_detail'),
        );

        return response()->json([
            'message' => 'Import terminé',
            'batch' => $batch,
        ], 201);
    }

    public function batches(Request $request): JsonResponse
    {
        $batches = ImportBatch::latest()->paginate($request->integer('per_page', 20));
        return response()->json($batches);
    }

    public function batchDetail(string $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);
        $prospects = \App\Models\Prospect::where('import_batch_id', $id)->select('id', 'email', 'first_name', 'last_name', 'lifecycle_state', 'is_sos_expat_user', 'created_at')->get();

        return response()->json([
            'batch' => $batch,
            'prospects' => $prospects,
        ]);
    }

    private function parseFile($file): array
    {
        $rows = [];
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if (!$handle) {
            return [];
        }

        // Strip BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ',');
        if (!$header) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-', "\xEF\xBB\xBF"], ['_', '_', ''], $h))), $header);

        $columnMap = [
            'e_mail' => 'email', 'mail' => 'email', 'courriel' => 'email',
            'firstname' => 'first_name', 'prenom' => 'first_name', 'prénom' => 'first_name',
            'lastname' => 'last_name', 'nom' => 'last_name', 'nom_de_famille' => 'last_name',
            'telephone' => 'phone', 'tel' => 'phone', 'téléphone' => 'phone',
            'pays' => 'country', 'langue' => 'language',
            'poste' => 'job_title', 'titre' => 'job_title', 'intitulé' => 'job_title', 'intitule' => 'job_title',
            'entreprise' => 'company', 'societe' => 'company', 'société' => 'company',
        ];

        $header = array_map(fn($h) => $columnMap[$h] ?? $h, $header);

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (count($data) >= count($header)) {
                $row = array_combine($header, array_slice($data, 0, count($header)));
                if (!empty($row['email'])) {
                    $rows[] = $row;
                }
            }
        }

        fclose($handle);
        return $rows;
    }
}
