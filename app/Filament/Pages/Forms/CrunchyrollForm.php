<?php

namespace App\Filament\Pages\Forms;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;

class CrunchyrollForm
{
    public static function get(): array
    {
        return [
            Section::make('Crunchyroll')
                ->schema(components: [
                    TextInput::make('crunch_login_url')
                        ->label(__('filament.login url'))
                        ->autofocus()
                        ->columnSpanFull(),
                    TextInput::make('crunch_basic_auth')
                        ->label(__('filament.basic auth'))
                        ->autofocus()
                        ->columnSpanFull(),
                    TextInput::make('crunch_grant_type')
                        ->label(__('filament.grant_type'))
                        ->autofocus()
                        ->columnSpanFull(),
                    TextInput::make('crunch_device_id')
                        ->label(__('filament.device_id'))
                        ->autofocus()
                        ->columnSpanFull(),
                    TextInput::make('crunch_device_type')
                        ->label(__('filament.device_type'))
                        ->autofocus()
                        ->columnSpanFull(),
                    TextInput::make('crunch_etp_cookie')
                        ->label(__('filament.etp_cookie'))
                        ->autofocus()
                        ->columnSpanFull(),
                ]),
            Section::make('Endpoints')
                ->schema([
                    TextInput::make('crunch_base_url')
                        ->label(__('filament.base url'))
                        ->autofocus()
                        ->columnSpanFull(),
                    TextInput::make('crunch_token_endpoint')
                        ->label(__('filament.token endpoint'))
                        ->autofocus()
                        ->columnSpanFull(),
                    TextInput::make('crunch_browse_endpoint')
                        ->label(__('filament.browse endpoint'))
                        ->autofocus()
                        ->columnSpanFull(),
                ]),
        ];
    }
}
