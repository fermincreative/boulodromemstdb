<?php

namespace App\Filament\Resources\MailTemplates\Pages;

use App\Filament\Resources\MailTemplates\MailTemplateResource;
use App\Services\MailTemplates;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Mail\Markdown;
use Illuminate\Support\HtmlString;

class EditMailTemplate extends EditRecord
{
    protected static string $resource = MailTemplateResource::class;

    public function getTitle(): string
    {
        return "E-mailtekst: {$this->getRecord()->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Voorbeeld')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->modalHeading('Voorbeeld met voorbeeldgegevens (opgeslagen tekst)')
                ->modalWidth(Width::ThreeExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Sluiten')
                ->modalContent(fn (): HtmlString => $this->preview()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl();
    }

    private function preview(): HtmlString
    {
        $mail = app(MailTemplates::class)->renderExample($this->getRecord()->key);

        return new HtmlString(
            '<p><strong>Onderwerp:</strong> '.e($mail['subject']).'</p><hr class="my-3">'
            .'<div class="prose max-w-none dark:prose-invert">'.Markdown::parse(e($mail['body'])).'</div>'
        );
    }
}
