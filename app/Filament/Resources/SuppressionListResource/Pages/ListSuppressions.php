<?php

namespace App\Filament\Resources\SuppressionListResource\Pages;

use App\Filament\Resources\SuppressionListResource;
use Filament\Resources\Pages\ListRecords;

class ListSuppressions extends ListRecords
{
    protected static string $resource = SuppressionListResource::class;
    protected static ?string $title = 'Liste de Suppression';
}
