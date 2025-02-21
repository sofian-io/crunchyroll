<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Forms\CrunchyrollForm;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    public ?array $data = [];

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string $view = 'filament.pages.settings';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationLabel = 'Settings';

    public static function shouldRegisterSpotlight(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->data = Setting::all()->pluck('value', 'key')->toArray();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Crunch')
                            ->label(__('filament.crunchyroll'))
                            ->icon('heroicon-o-play')
                            ->schema(CrunchyrollForm::get())
                            ->columns(3),
                    ]),
            ])
            ->statePath('data');
    }

    public function update(): void
    {
        $settings = $this->form->getState();
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->successNotification(__('filament.settings_saved'));
        redirect(request()?->header('Referer'));
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('Save')
                ->label(__('filament.save'))
                ->color('primary')
                ->submit('Update'),
        ];
    }

    private function successNotification(string $title): void
    {
        Notification::make()
            ->title($title)
            ->success()
            ->send();
    }
}
