<?php

namespace Database\Seeders;

use App\Models\EmailSequence;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateVariant;
use App\Models\SequenceStep;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Template 1 : Bienvenue
        $t1 = EmailTemplate::firstOrCreate(['slug' => 'welcome_prospect'], [
            'name' => 'Bienvenue Prospect',
            'category' => 'onboarding',
            'is_active' => true,
        ]);
        EmailTemplateVariant::firstOrCreate(['template_id' => $t1->id, 'language' => 'fr'], [
            'subject' => 'Bonjour {{prenom}}, une opportunité flexible pour vous !',
            'body_html' => '<h2>Bonjour {{prenom}},</h2><p>Vous cherchez un complément de revenu flexible ?</p><p>Des milliers de personnes comme vous gagnent entre 500$ et 5000$/mois en aidant des expatriés avec SOS-Expat.</p><p><strong>Aucune expérience requise</strong> — nous vous formons gratuitement.</p><p><a href="{{lien_inscription}}" style="background:#2563eb;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;">Découvrir comment ça marche</a></p><p style="font-size:12px;color:#999;margin-top:30px;"><a href="{{lien_desinscription}}">Se désinscrire</a></p>',
            'preview_text' => 'Gagnez un revenu flexible en aidant des expatriés',
            'is_active' => true,
        ]);
        EmailTemplateVariant::firstOrCreate(['template_id' => $t1->id, 'language' => 'en'], [
            'subject' => 'Hi {{first_name}}, a flexible opportunity for you!',
            'body_html' => '<h2>Hi {{first_name}},</h2><p>Looking for flexible extra income?</p><p>Thousands of people like you earn $500-$5000/month helping expats with SOS-Expat.</p><p><strong>No experience needed</strong> — we train you for free.</p><p><a href="{{join_link}}" style="background:#2563eb;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;">Learn how it works</a></p><p style="font-size:12px;color:#999;margin-top:30px;"><a href="{{unsubscribe_link}}">Unsubscribe</a></p>',
            'preview_text' => 'Earn flexible income helping expats',
            'is_active' => true,
        ]);

        // Template 2 : Témoignages
        $t2 = EmailTemplate::firstOrCreate(['slug' => 'testimonials_prospect'], [
            'name' => 'Témoignages Chatters',
            'category' => 'nurture',
            'is_active' => true,
        ]);
        EmailTemplateVariant::firstOrCreate(['template_id' => $t2->id, 'language' => 'fr'], [
            'subject' => '{{prenom}}, voici comment ils gagnent leur vie avec SOS-Expat',
            'body_html' => '<h2>{{prenom}}, des résultats concrets</h2><p><em>"J\'ai commencé il y a 3 mois, je gagne maintenant 1200$/mois en travaillant 2h par jour"</em> — Sarah, France</p><p><em>"Grâce à SOS-Expat, j\'ai quitté mon emploi et je suis mon propre patron"</em> — Ahmed, Sénégal</p><p><a href="{{lien_inscription}}" style="background:#2563eb;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;">Rejoindre SOS-Expat gratuitement</a></p><p style="font-size:12px;color:#999;margin-top:30px;"><a href="{{lien_desinscription}}">Se désinscrire</a></p>',
            'is_active' => true,
        ]);

        // Template 3 : Dernier push
        $t3 = EmailTemplate::firstOrCreate(['slug' => 'final_push_prospect'], [
            'name' => 'Dernier Push Inscription',
            'category' => 'conversion',
            'is_active' => true,
        ]);
        EmailTemplateVariant::firstOrCreate(['template_id' => $t3->id, 'language' => 'fr'], [
            'subject' => '{{prenom}}, dernière chance de rejoindre SOS-Expat',
            'body_html' => '<h2>{{prenom}}, ne ratez pas cette opportunité</h2><p>Nous avons encore quelques places disponibles dans votre région ({{pays}}).</p><p>Formation gratuite<br>Revenus dès la première semaine<br>Travaillez quand vous voulez, où vous voulez</p><p><a href="{{lien_inscription}}" style="background:#dc2626;color:white;padding:14px 28px;border-radius:8px;text-decoration:none;display:inline-block;font-weight:bold;">S\'inscrire maintenant</a></p><p style="font-size:12px;color:#999;margin-top:30px;"><a href="{{lien_desinscription}}">Se désinscrire</a></p>',
            'is_active' => true,
        ]);

        // Séquence d'onboarding
        $seq = EmailSequence::firstOrCreate(['slug' => 'prospect-onboarding'], [
            'name' => 'Onboarding Prospect',
            'description' => 'Séquence automatique pour convertir les prospects en chatters SOS-Expat',
            'trigger_event' => 'prospect.imported',
            'status' => 'active',
            'priority' => 10,
        ]);

        if ($seq->steps()->count() === 0) {
            SequenceStep::create(['sequence_id' => $seq->id, 'step_order' => 1, 'type' => 'email', 'template_slug' => 'welcome_prospect']);
            SequenceStep::create(['sequence_id' => $seq->id, 'step_order' => 2, 'type' => 'delay', 'config' => ['delay_hours' => 72]]);
            SequenceStep::create(['sequence_id' => $seq->id, 'step_order' => 3, 'type' => 'condition', 'config' => ['field' => 'emails_opened', 'operator' => 'gte', 'value' => 1]]);
            SequenceStep::create(['sequence_id' => $seq->id, 'step_order' => 4, 'type' => 'email', 'template_slug' => 'testimonials_prospect']);
            SequenceStep::create(['sequence_id' => $seq->id, 'step_order' => 5, 'type' => 'delay', 'config' => ['delay_hours' => 96]]);
            SequenceStep::create(['sequence_id' => $seq->id, 'step_order' => 6, 'type' => 'condition', 'config' => ['field' => 'lifecycle_state', 'operator' => 'neq', 'value' => 'converted', 'exit_on_fail' => false]]);
            SequenceStep::create(['sequence_id' => $seq->id, 'step_order' => 7, 'type' => 'email', 'template_slug' => 'final_push_prospect']);
        }
    }
}
