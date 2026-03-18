<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportBatchResource\Pages;
use App\Models\ImportBatch;
use App\Services\ImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportBatchResource extends Resource
{
    protected static ?string $model = ImportBatch::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Prospects';
    protected static ?string $navigationLabel = 'Imports CSV';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Import CSV')->schema([
                Forms\Components\FileUpload::make('csv_file')
                    ->label('Fichier CSV')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                    ->maxSize(10240)
                    ->required()
                    ->helperText('Colonnes attendues : email, prénom, nom, pays, langue, poste, entreprise, téléphone'),
                Forms\Components\TextInput::make('source')->label('Source')->default('job_ads')->required(),
                Forms\Components\TextInput::make('source_detail')->label('Détail (quel site, quelle annonce)'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('filename')->label('Fichier')->searchable(),
                Tables\Columns\TextColumn::make('source')->label('Source')->sortable(),
                Tables\Columns\TextColumn::make('source_detail')->label('Détail')->toggleable(),
                Tables\Columns\TextColumn::make('total_rows')->label('Lignes')->alignCenter(),
                Tables\Columns\TextColumn::make('imported')->label('Importés')->alignCenter()
                    ->color('success')->weight('bold'),
                Tables\Columns\TextColumn::make('duplicates_skipped')->label('Doublons')->alignCenter()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('sos_users_skipped')->label('Déjà SOS')->alignCenter()
                    ->color('info'),
                Tables\Columns\TextColumn::make('invalid_skipped')->label('Invalides')->alignCenter()
                    ->color('danger'),
                Tables\Columns\BadgeColumn::make('status')->label('Statut')
                    ->colors(['gray' => 'pending', 'warning' => 'processing', 'success' => 'completed', 'danger' => 'failed']),
                Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportBatches::route('/'),
            'create' => Pages\CreateImportBatch::route('/create'),
        ];
    }
}
