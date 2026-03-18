<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuppressionListResource\Pages;
use App\Models\SuppressionList;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SuppressionListResource extends Resource
{
    protected static ?string $model = SuppressionList::class;
    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';
    protected static ?string $navigationGroup = 'Système';
    protected static ?string $navigationLabel = 'Suppressions';
    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prospect.email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('channel')->label('Canal'),
                Tables\Columns\BadgeColumn::make('reason')->label('Raison')
                    ->colors(['danger' => 'bounce', 'warning' => 'unsubscribe', 'gray' => 'complaint', 'info' => 'effacement_rgpd']),
                Tables\Columns\TextColumn::make('source')->label('Source'),
                Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('lifted_at')->label('Levée le')->dateTime('d/m/Y H:i')->placeholder('Actif'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppressions::route('/'),
        ];
    }
}
