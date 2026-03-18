<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProspectResource\Pages;
use App\Models\Prospect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ProspectResource extends Resource
{
    protected static ?string $model = Prospect::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Prospects';
    protected static ?string $navigationLabel = 'Prospects';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::where('is_active', true)->where('lifecycle_state', '!=', 'converted')->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations personnelles')->schema([
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('first_name')->label('Prénom'),
                Forms\Components\TextInput::make('last_name')->label('Nom'),
                Forms\Components\TextInput::make('phone')->label('Téléphone'),
            ])->columns(2),

            Forms\Components\Section::make('Profil professionnel')->schema([
                Forms\Components\TextInput::make('job_title')->label('Poste'),
                Forms\Components\TextInput::make('company')->label('Entreprise'),
                Forms\Components\TextInput::make('country')->label('Pays'),
                Forms\Components\Select::make('language')->label('Langue')->options([
                    'fr' => 'Français', 'en' => 'English', 'es' => 'Español', 'de' => 'Deutsch',
                    'pt' => 'Português', 'ar' => 'العربية', 'zh' => '中文', 'ru' => 'Русский', 'hi' => 'हिन्दी',
                ])->default('fr'),
            ])->columns(2),

            Forms\Components\Section::make('État & Source')->schema([
                Forms\Components\Select::make('lifecycle_state')->label('État')->options([
                    'lead' => 'Lead', 'nurtured' => 'Nurtured', 'hot' => 'Hot',
                    'converted' => 'Converti', 'churned' => 'Churned', 'unsubscribed' => 'Désinscrit',
                ])->required(),
                Forms\Components\TextInput::make('source')->label('Source'),
                Forms\Components\TextInput::make('source_detail')->label('Détail source'),
                Forms\Components\Toggle::make('is_active')->label('Actif')->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Tags')->schema([
                Forms\Components\TagsInput::make('tags')->label('Tags'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('first_name')->label('Prénom')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('last_name')->label('Nom')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('lifecycle_state')->label('État')
                    ->colors([
                        'gray' => 'lead', 'info' => 'nurtured', 'warning' => 'hot',
                        'success' => 'converted', 'danger' => 'churned', 'secondary' => 'unsubscribed',
                    ]),
                Tables\Columns\TextColumn::make('source')->label('Source')->sortable(),
                Tables\Columns\TextColumn::make('company')->label('Entreprise')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('job_title')->label('Poste')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('emails_sent')->label('Envoyés')->sortable()->alignCenter(),
                Tables\Columns\TextColumn::make('emails_opened')->label('Ouverts')->sortable()->alignCenter(),
                Tables\Columns\TextColumn::make('emails_clicked')->label('Cliqués')->sortable()->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')->label('Actif')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('lifecycle_state')->label('État')->options([
                    'lead' => 'Lead', 'nurtured' => 'Nurtured', 'hot' => 'Hot',
                    'converted' => 'Converti', 'churned' => 'Churned', 'unsubscribed' => 'Désinscrit',
                ]),
                SelectFilter::make('source')->label('Source')->options(
                    Prospect::distinct()->whereNotNull('source')->pluck('source', 'source')->toArray()
                ),
                TernaryFilter::make('is_active')->label('Actif'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->searchPlaceholder('Rechercher par email, nom, entreprise...');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Prospect')->schema([
                Infolists\Components\TextEntry::make('email'),
                Infolists\Components\TextEntry::make('full_name')->label('Nom complet'),
                Infolists\Components\TextEntry::make('phone')->label('Téléphone'),
                Infolists\Components\TextEntry::make('country')->label('Pays'),
                Infolists\Components\TextEntry::make('language')->label('Langue'),
                Infolists\Components\TextEntry::make('lifecycle_state')->label('État')->badge(),
            ])->columns(3),

            Infolists\Components\Section::make('Professionnel')->schema([
                Infolists\Components\TextEntry::make('job_title')->label('Poste'),
                Infolists\Components\TextEntry::make('company')->label('Entreprise'),
                Infolists\Components\TextEntry::make('source')->label('Source'),
                Infolists\Components\TextEntry::make('source_detail')->label('Détail source'),
            ])->columns(2),

            Infolists\Components\Section::make('Engagement Email')->schema([
                Infolists\Components\TextEntry::make('emails_sent')->label('Envoyés'),
                Infolists\Components\TextEntry::make('emails_opened')->label('Ouverts'),
                Infolists\Components\TextEntry::make('emails_clicked')->label('Cliqués'),
                Infolists\Components\TextEntry::make('emails_bounced')->label('Bounces'),
                Infolists\Components\TextEntry::make('fatigue_score')->label('Score fatigue'),
                Infolists\Components\TextEntry::make('fatigue_multiplier')->label('Multiplicateur'),
            ])->columns(3),

            Infolists\Components\Section::make('Conversion')->schema([
                Infolists\Components\TextEntry::make('join_code')->label('Code inscription')->copyable(),
                Infolists\Components\TextEntry::make('firebase_uid')->label('Firebase UID'),
                Infolists\Components\TextEntry::make('converted_at')->label('Converti le')->dateTime('d/m/Y H:i'),
                Infolists\Components\TextEntry::make('converted_via')->label('Converti via'),
            ])->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProspects::route('/'),
            'create' => Pages\CreateProspect::route('/create'),
            'view' => Pages\ViewProspect::route('/{record}'),
            'edit' => Pages\EditProspect::route('/{record}/edit'),
        ];
    }
}
