<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailSequenceResource\Pages;
use App\Models\EmailSequence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailSequenceResource extends Resource
{
    protected static ?string $model = EmailSequence::class;
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Campagnes';
    protected static ?string $navigationLabel = 'Séquences Email';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Séquence')->schema([
                Forms\Components\TextInput::make('name')->label('Nom')->required(),
                Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true),
                Forms\Components\Textarea::make('description')->label('Description'),
                Forms\Components\TextInput::make('trigger_event')->label('Événement déclencheur')->default('prospect.imported'),
                Forms\Components\Select::make('status')->label('Statut')->options([
                    'draft' => 'Brouillon', 'active' => 'Active', 'paused' => 'En pause', 'archived' => 'Archivée',
                ])->default('draft'),
                Forms\Components\TextInput::make('priority')->label('Priorité')->numeric()->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->copyable(),
                Tables\Columns\BadgeColumn::make('status')->label('Statut')
                    ->colors(['gray' => 'draft', 'success' => 'active', 'warning' => 'paused', 'danger' => 'archived']),
                Tables\Columns\TextColumn::make('trigger_event')->label('Déclencheur'),
                Tables\Columns\TextColumn::make('steps_count')->label('Étapes')->counts('steps')->alignCenter(),
                Tables\Columns\TextColumn::make('enrollments_count')->label('Inscrits')->counts('enrollments')->alignCenter(),
                Tables\Columns\TextColumn::make('priority')->label('Priorité')->sortable()->alignCenter(),
            ])
            ->defaultSort('priority', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailSequences::route('/'),
            'create' => Pages\CreateEmailSequence::route('/create'),
            'edit' => Pages\EditEmailSequence::route('/{record}/edit'),
        ];
    }
}
