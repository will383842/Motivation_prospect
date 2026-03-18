<?php

namespace App\Filament\Resources\EmailSequenceResource\Pages;

use App\Filament\Resources\EmailSequenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmailSequences extends ListRecords
{
    protected static string $resource = EmailSequenceResource::class;
    protected static ?string $title = 'Séquences Email';

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouvelle séquence')];
    }
}
