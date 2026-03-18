<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Prospect;
use App\Models\ProspectEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportService
{
    public function __construct(
        private SosExpatChecker $sosExpatChecker,
        private SequenceEngine $sequenceEngine,
    ) {}

    /**
     * Importer un lot de prospects depuis un fichier CSV/Excel parsé.
     * Chaque ligne : ['email', 'first_name', 'last_name', 'phone', 'country', 'language', 'job_title', 'company']
     */
    public function importBatch(array $rows, string $filename, ?string $source = null, ?string $sourceDetail = null): ImportBatch
    {
        $batch = ImportBatch::create([
            'filename' => $filename,
            'source' => $source ?? 'manual',
            'source_detail' => $sourceDetail,
            'total_rows' => count($rows),
            'status' => 'processing',
        ]);

        $imported = 0;
        $duplicates = 0;
        $sosUsers = 0;
        $invalid = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                try {
                    $result = $this->importRow($row, $batch);
                    match ($result) {
                        'imported' => $imported++,
                        'duplicate' => $duplicates++,
                        'sos_user' => $sosUsers++,
                        'invalid' => $invalid++,
                    };
                } catch (\Throwable $e) {
                    $invalid++;
                    $errors[] = "Ligne {$index} : {$e->getMessage()}";
                    Log::warning("Échec import ligne", ['ligne' => $index, 'erreur' => $e->getMessage()]);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $batch->update([
            'imported' => $imported,
            'duplicates_skipped' => $duplicates,
            'sos_users_skipped' => $sosUsers,
            'invalid_skipped' => $invalid,
            'errors' => !empty($errors) ? $errors : null,
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        Log::info("Import lot terminé", [
            'batch_id' => $batch->id,
            'importés' => $imported,
            'doublons' => $duplicates,
            'utilisateurs_sos' => $sosUsers,
            'invalides' => $invalid,
        ]);

        return $batch;
    }

    private function importRow(array $row, ImportBatch $batch): string
    {
        $email = $this->normalizeEmail($row['email'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid';
        }

        // 1. Vérifier doublon dans notre base
        if (Prospect::where('email', $email)->exists()) {
            return 'duplicate';
        }

        // 2. Vérifier si déjà inscrit sur SOS-Expat
        if ($this->sosExpatChecker->isRegistered($email)) {
            Prospect::create([
                'email' => $email,
                'first_name' => $this->cleanName($row['first_name'] ?? null),
                'last_name' => $this->cleanName($row['last_name'] ?? null),
                'is_sos_expat_user' => true,
                'is_active' => false,
                'lifecycle_state' => 'converted',
                'import_batch_id' => $batch->id,
            ]);
            return 'sos_user';
        }

        // 3. Créer le prospect
        $prospect = Prospect::create([
            'email' => $email,
            'first_name' => $this->cleanName($row['first_name'] ?? null),
            'last_name' => $this->cleanName($row['last_name'] ?? null),
            'phone' => $row['phone'] ?? null,
            'country' => $row['country'] ?? null,
            'language' => $this->detectLanguage($row),
            'timezone' => $row['timezone'] ?? $this->guessTimezone($row['country'] ?? null),
            'source' => $batch->source,
            'source_detail' => $batch->source_detail,
            'job_title' => $row['job_title'] ?? null,
            'company' => $row['company'] ?? null,
            'join_code' => $this->generateJoinCode(),
            'import_batch_id' => $batch->id,
            'lifecycle_state' => 'lead',
            'is_active' => true,
        ]);

        ProspectEvent::create([
            'prospect_id' => $prospect->id,
            'event_type' => 'imported',
            'event_data' => ['batch_id' => $batch->id, 'source' => $batch->source],
            'occurred_at' => now(),
        ]);

        // 4. Inscrire automatiquement dans la séquence par défaut
        $this->sequenceEngine->autoEnroll($prospect, 'prospect.imported');

        return 'imported';
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }
        return strtolower(trim($email));
    }

    private function cleanName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }
        $name = trim($name);
        return $name !== '' ? ucfirst(mb_strtolower($name)) : null;
    }

    private function generateJoinCode(): string
    {
        do {
            $code = Str::random(12);
        } while (Prospect::where('join_code', $code)->exists());

        return $code;
    }

    private function detectLanguage(array $row): string
    {
        if (!empty($row['language'])) {
            $lang = strtolower(trim($row['language']));
            $valid = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'];
            if (in_array($lang, $valid)) {
                return $lang;
            }
        }

        $countryLang = [
            'FR' => 'fr', 'BE' => 'fr', 'CH' => 'fr', 'CA' => 'fr', 'SN' => 'fr', 'CI' => 'fr',
            'CM' => 'fr', 'ML' => 'fr', 'BF' => 'fr', 'TG' => 'fr', 'BJ' => 'fr', 'NE' => 'fr',
            'MG' => 'fr', 'CD' => 'fr', 'CG' => 'fr', 'GA' => 'fr', 'DJ' => 'fr', 'KM' => 'fr',
            'US' => 'en', 'GB' => 'en', 'AU' => 'en', 'NG' => 'en', 'GH' => 'en', 'KE' => 'en',
            'ZA' => 'en', 'IN' => 'en', 'PH' => 'en',
            'ES' => 'es', 'MX' => 'es', 'CO' => 'es', 'AR' => 'es', 'PE' => 'es', 'CL' => 'es',
            'DE' => 'de', 'AT' => 'de',
            'BR' => 'pt', 'PT' => 'pt',
            'RU' => 'ru', 'CN' => 'zh',
            'SA' => 'ar', 'MA' => 'ar', 'DZ' => 'ar', 'TN' => 'ar', 'EG' => 'ar',
        ];

        $country = strtoupper($row['country'] ?? '');
        return $countryLang[$country] ?? 'fr';
    }

    private function guessTimezone(?string $country): string
    {
        if (!$country) {
            return 'UTC';
        }

        $countryTz = [
            'FR' => 'Europe/Paris', 'BE' => 'Europe/Brussels', 'CH' => 'Europe/Zurich',
            'DE' => 'Europe/Berlin', 'ES' => 'Europe/Madrid', 'IT' => 'Europe/Rome',
            'GB' => 'Europe/London', 'US' => 'America/New_York', 'CA' => 'America/Toronto',
            'BR' => 'America/Sao_Paulo', 'SN' => 'Africa/Dakar', 'CI' => 'Africa/Abidjan',
            'CM' => 'Africa/Douala', 'MA' => 'Africa/Casablanca', 'TN' => 'Africa/Tunis',
            'DZ' => 'Africa/Algiers', 'NG' => 'Africa/Lagos', 'GH' => 'Africa/Accra',
            'KE' => 'Africa/Nairobi', 'ZA' => 'Africa/Johannesburg',
            'IN' => 'Asia/Kolkata', 'CN' => 'Asia/Shanghai', 'RU' => 'Europe/Moscow',
        ];

        return $countryTz[strtoupper($country)] ?? 'UTC';
    }
}
