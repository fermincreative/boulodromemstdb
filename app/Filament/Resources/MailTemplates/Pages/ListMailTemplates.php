<?php

namespace App\Filament\Resources\MailTemplates\Pages;

use App\Filament\Resources\MailTemplates\MailTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListMailTemplates extends ListRecords
{
    protected static string $resource = MailTemplateResource::class;
}
