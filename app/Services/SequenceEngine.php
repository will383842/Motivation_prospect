<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailSequence;
use App\Models\Prospect;
use App\Models\ProspectSequence;
use App\Models\SequenceStep;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Moteur de séquences email automatisées.
 * Gère l'inscription, l'avancement et les conditions de sortie.
 */
class SequenceEngine
{
    private const MAX_SEQUENCES_SIMULTANÉES = 2;

    public function __construct(
        private ProspectDispatcher $dispatcher,
    ) {}

    /**
     * Inscrire automatiquement un prospect dans la meilleure séquence pour le trigger donné.
     */
    public function autoEnroll(Prospect $prospect, string $triggerEvent): void
    {
        $sequence = EmailSequence::where('trigger_event', $triggerEvent)
            ->where('status', 'active')
            ->orderByDesc('priority')
            ->first();

        if ($sequence) {
            $this->enroll($prospect, $sequence);
        }
    }

    public function enroll(Prospect $prospect, EmailSequence $sequence): ?ProspectSequence
    {
        $activeCount = ProspectSequence::where('prospect_id', $prospect->id)
            ->where('status', 'active')
            ->count();

        if ($activeCount >= self::MAX_SEQUENCES_SIMULTANÉES) {
            Log::info("Prospect {$prospect->id} déjà dans {$activeCount} séquences, inscription ignorée");
            return null;
        }

        if (!$sequence->is_repeatable) {
            $existing = ProspectSequence::where('prospect_id', $prospect->id)
                ->where('sequence_id', $sequence->id)
                ->exists();

            if ($existing) {
                return null;
            }
        }

        $premièreEtape = $sequence->steps()->orderBy('step_order')->first();

        return ProspectSequence::create([
            'prospect_id' => $prospect->id,
            'sequence_id' => $sequence->id,
            'current_step_id' => $premièreEtape?->id,
            'current_step_order' => 1,
            'status' => 'active',
            'enrolled_at' => now(),
            'next_step_at' => now(),
        ]);
    }

    /**
     * Avancer toutes les inscriptions actives qui sont dues.
     * Appelé chaque minute par cron.
     */
    public function advanceAll(): int
    {
        $traites = 0;

        ProspectSequence::where('status', 'active')
            ->where('next_step_at', '<=', now())
            ->with(['prospect', 'sequence', 'currentStep'])
            ->chunkById(100, function ($enrollments) use (&$traites) {
                foreach ($enrollments as $enrollment) {
                    $lockKey = "seq_lock:{$enrollment->id}";
                    if (!Redis::set($lockKey, '1', 'EX', 300, 'NX')) {
                        continue;
                    }

                    try {
                        $this->advanceOne($enrollment);
                        $traites++;
                    } catch (\Throwable $e) {
                        Log::error("Échec avancement séquence", [
                            'enrollment_id' => $enrollment->id,
                            'erreur' => $e->getMessage(),
                        ]);
                    } finally {
                        Redis::del($lockKey);
                    }
                }
            });

        return $traites;
    }

    private function advanceOne(ProspectSequence $enrollment): void
    {
        $prospect = $enrollment->prospect;
        $step = $enrollment->currentStep;

        if (!$prospect || !$step) {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
            return;
        }

        if ($this->shouldExit($prospect, $enrollment)) {
            return;
        }

        match ($step->type) {
            'email' => $this->processEmailStep($enrollment, $prospect, $step),
            'delay' => $this->processDelayStep($enrollment, $step),
            'condition' => $this->processConditionStep($enrollment, $prospect, $step),
            'ab_split' => $this->processAbSplitStep($enrollment, $prospect, $step),
            'action' => $this->processActionStep($enrollment, $prospect, $step),
            default => Log::warning("Type d'étape inconnu : {$step->type}"),
        };
    }

    private function processEmailStep(ProspectSequence $enrollment, Prospect $prospect, SequenceStep $step): void
    {
        if ($step->template_slug) {
            $this->dispatcher->send($prospect, $step->template_slug);
        }
        $this->moveToNextStep($enrollment);
    }

    private function processDelayStep(ProspectSequence $enrollment, SequenceStep $step): void
    {
        $delayHeures = $step->config['delay_hours'] ?? 24;
        $nextStep = $this->getNextStep($enrollment);

        if ($nextStep) {
            $enrollment->update([
                'current_step_id' => $nextStep->id,
                'current_step_order' => $nextStep->step_order,
                'next_step_at' => now()->addHours($delayHeures),
            ]);
        } else {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    private function processConditionStep(ProspectSequence $enrollment, Prospect $prospect, SequenceStep $step): void
    {
        $config = $step->config ?? [];
        $field = $config['field'] ?? null;
        $operator = $config['operator'] ?? 'eq';
        $value = $config['value'] ?? null;

        $actualValue = match ($field) {
            'lifecycle_state' => $prospect->lifecycle_state,
            'emails_opened' => $prospect->emails_opened,
            'emails_clicked' => $prospect->emails_clicked,
            'is_converted' => $prospect->isConverted(),
            'engagement_score' => $prospect->engagement_score,
            default => $prospect->{$field} ?? null,
        };

        $conditionRemplie = match ($operator) {
            'eq' => $actualValue == $value,
            'neq' => $actualValue != $value,
            'gt' => $actualValue > $value,
            'gte' => $actualValue >= $value,
            'lt' => $actualValue < $value,
            'lte' => $actualValue <= $value,
            'is_null' => is_null($actualValue),
            'is_not_null' => !is_null($actualValue),
            default => false,
        };

        if ($conditionRemplie) {
            if (isset($config['jump_to_step'])) {
                $jumpStep = SequenceStep::where('sequence_id', $enrollment->sequence_id)
                    ->where('step_order', $config['jump_to_step'])
                    ->first();
                if ($jumpStep) {
                    $enrollment->update([
                        'current_step_id' => $jumpStep->id,
                        'current_step_order' => $jumpStep->step_order,
                        'next_step_at' => now(),
                    ]);
                    return;
                }
            }
            $this->moveToNextStep($enrollment);
        } else {
            if ($config['exit_on_fail'] ?? false) {
                $enrollment->update([
                    'status' => 'exited',
                    'exit_reason' => "condition_echouee:{$field}",
                    'completed_at' => now(),
                ]);
            } else {
                $this->moveToNextStep($enrollment);
            }
        }
    }

    private function processAbSplitStep(ProspectSequence $enrollment, Prospect $prospect, SequenceStep $step): void
    {
        $variants = $step->config['variants'] ?? [];
        if (empty($variants)) {
            $this->moveToNextStep($enrollment);
            return;
        }

        $variantIndex = crc32($prospect->id) % count($variants);
        $variant = $variants[$variantIndex];

        if ($variant['template_slug'] ?? null) {
            $this->dispatcher->send($prospect, $variant['template_slug']);
        }

        $enrollment->update([
            'metadata' => array_merge($enrollment->metadata ?? [], [
                "ab_etape_{$step->step_order}" => $variantIndex,
            ]),
        ]);

        $this->moveToNextStep($enrollment);
    }

    private function processActionStep(ProspectSequence $enrollment, Prospect $prospect, SequenceStep $step): void
    {
        $action = $step->config['action'] ?? null;

        match ($action) {
            'add_tag' => $prospect->update([
                'tags' => array_unique(array_merge($prospect->tags ?? [], [$step->config['tag'] ?? ''])),
            ]),
            'change_lifecycle' => $prospect->update([
                'lifecycle_state' => $step->config['state'] ?? $prospect->lifecycle_state,
                'lifecycle_changed_at' => now(),
            ]),
            default => null,
        };

        $this->moveToNextStep($enrollment);
    }

    private function shouldExit(Prospect $prospect, ProspectSequence $enrollment): bool
    {
        // Converti → tout arrêter
        if ($prospect->isConverted()) {
            $enrollment->update(['status' => 'exited', 'exit_reason' => 'converti', 'completed_at' => now()]);
            return true;
        }

        // Supprimé ou désinscrit
        if ($prospect->isSuppressed() || !$prospect->is_active) {
            $enrollment->update(['status' => 'exited', 'exit_reason' => 'supprime', 'completed_at' => now()]);
            return true;
        }

        // Déjà inscrit sur SOS-Expat
        if ($prospect->is_sos_expat_user) {
            $enrollment->update(['status' => 'exited', 'exit_reason' => 'deja_utilisateur_sos', 'completed_at' => now()]);
            return true;
        }

        return false;
    }

    private function moveToNextStep(ProspectSequence $enrollment): void
    {
        $nextStep = $this->getNextStep($enrollment);

        if ($nextStep) {
            $enrollment->update([
                'current_step_id' => $nextStep->id,
                'current_step_order' => $nextStep->step_order,
                'next_step_at' => now(),
            ]);
        } else {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }

    private function getNextStep(ProspectSequence $enrollment): ?SequenceStep
    {
        return SequenceStep::where('sequence_id', $enrollment->sequence_id)
            ->where('step_order', '>', $enrollment->current_step_order)
            ->orderBy('step_order')
            ->first();
    }
}
