<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.projects.view';
    protected function getActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
