<?php

namespace App\Filament\Resources\ImportBatchResource\Pages;

use App\Filament\Resources\ImportBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImportBatches extends ListRecords
{
    protected static string $resource = ImportBatchResource::class;
    protected static ?string $title = 'Historique des Imports';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Importer un CSV'),
        ];
    }
}
