<?php

namespace App\Filament\Forms;

use App\Filament\Resources\MailTemplates\MailTemplateResource;
use App\Models\MailTemplate;
use App\Services\MailTemplates;
use Closure;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

/**
 * Subject + message fields for editing an e-mail just before it is sent.
 */
class MailFields
{
    /**
     * @param  (Closure(): bool)|bool  $visible
     * @param  string|null  $placeholdersFor  Template key: the text keeps its {placeholders}, filled in per member (bulk sends).
     * @return array<int, TextInput|MarkdownEditor>
     */
    public static function make(Closure|bool $visible = true, ?string $placeholdersFor = null): array
    {
        return [
            TextInput::make('subject')
                ->label('Onderwerp')
                ->required($visible)
                ->visible($visible)
                ->maxLength(255),
            MarkdownEditor::make('body')
                ->label('Bericht')
                ->required($visible)
                ->visible($visible)
                ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                ->helperText(fn (): HtmlString => new HtmlString(
                    ($placeholdersFor
                        ? 'Invulvelden worden per lid ingevuld: '.e(implode(' ', array_keys(MailTemplates::placeholders($placeholdersFor)))).'<br>Wijzigingen gelden alleen voor deze verzending. '
                        : 'Wijzigingen gelden alleen voor deze e-mail. ')
                    .'De standaardtekst past u aan onder '
                    .(auth()->user()?->can('viewAny', MailTemplate::class)
                        ? '<a class="underline" href="'.e(MailTemplateResource::getUrl()).'" target="_blank">Beheer → E-mailteksten</a>.'
                        : 'Beheer → E-mailteksten (beheerders).')
                )),
        ];
    }
}
