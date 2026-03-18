<?php

namespace App\Filament\Resources\ImportBatchResource\Pages;

use App\Filament\Resources\ImportBatchResource;
use App\Services\ImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateImportBatch extends CreateRecord
{
    protected static string $resource = ImportBatchResource::class;
    protected static ?string $title = 'Importer des Prospects';

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $filePath = storage_path('app/public/' . $data['csv_file']);

        if (!file_exists($filePath)) {
            Notification::make()->title('Fichier introuvable')->danger()->send();
            return new \App\Models\ImportBatch();
        }

        $rows = $this->parseCsv($filePath);

        $importService = app(ImportService::class);
        $batch = $importService->importBatch(
            $rows,
            basename($filePath),
            $data['source'] ?? 'manual',
            $data['source_detail'] ?? null,
        );

        Notification::make()
            ->title('Import terminé')
            ->body("{$batch->imported} importés, {$batch->duplicates_skipped} doublons, {$batch->sos_users_skipped} déjà SOS-Expat")
            ->success()
            ->send();

        return $batch;
    }

    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) return [];

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $header = fgetcsv($handle, 0, ',');
        if (!$header) { fclose($handle); return []; }

        $header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);
        $columnMap = [
            'e_mail' => 'email', 'mail' => 'email', 'courriel' => 'email',
            'firstname' => 'first_name', 'prenom' => 'first_name', 'prénom' => 'first_name',
            'lastname' => 'last_name', 'nom' => 'last_name',
            'telephone' => 'phone', 'tel' => 'phone', 'téléphone' => 'phone',
            'pays' => 'country', 'langue' => 'language',
            'poste' => 'job_title', 'titre' => 'job_title',
            'entreprise' => 'company', 'societe' => 'company', 'société' => 'company',
        ];
        $header = array_map(fn($h) => $columnMap[$h] ?? $h, $header);

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (count($data) >= count($header)) {
                $row = array_combine($header, array_slice($data, 0, count($header)));
                if (!empty($row['email'])) $rows[] = $row;
            }
        }
        fclose($handle);
        return $rows;
    }
}
