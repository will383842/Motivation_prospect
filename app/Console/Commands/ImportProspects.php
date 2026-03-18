<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;

class ImportProspects extends Command
{
    protected $signature = 'prospects:import {file : Chemin vers le fichier CSV} {--source=job_ads : Nom de la source} {--detail= : Détail source (quel site/annonce)}';
    protected $description = 'Importer des prospects depuis un fichier CSV';

    public function handle(ImportService $importService): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Fichier introuvable : {$filePath}");
            return self::FAILURE;
        }

        $this->info("Import depuis {$filePath}...");
        $rows = $this->parseCsv($filePath);

        if (empty($rows)) {
            $this->error('Aucune ligne valide trouvée dans le fichier.');
            return self::FAILURE;
        }

        $this->info("Trouvé " . count($rows) . " lignes.");

        $batch = $importService->importBatch(
            $rows,
            basename($filePath),
            $this->option('source'),
            $this->option('detail'),
        );

        $this->info("Import terminé :");
        $this->table(
            ['Métrique', 'Nombre'],
            [
                ['Lignes totales', $batch->total_rows],
                ['Importés', $batch->imported],
                ['Doublons ignorés', $batch->duplicates_skipped],
                ['Utilisateurs SOS-Expat ignorés', $batch->sos_users_skipped],
                ['Invalides ignorés', $batch->invalid_skipped],
            ]
        );

        if ($batch->errors) {
            $this->warn('Erreurs :');
            foreach ($batch->errors as $error) {
                $this->line("  - {$error}");
            }
        }

        return self::SUCCESS;
    }

    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        // Supprimer le BOM UTF-8 si présent
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ',');
        if (!$header) {
            fclose($handle);
            return [];
        }

        // Normaliser les noms de colonnes
        $header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

        // Mapper les variations courantes de noms de colonnes
        $columnMap = [
            'e_mail' => 'email', 'mail' => 'email',
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
