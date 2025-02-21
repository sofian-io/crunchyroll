<?php

namespace App\Console\Commands\Translations;

use App\Concerns\DeepltranslatorTrait;
use App\Models\Locale;
use App\Models\TranslationString;
use Illuminate\Console\Command;

class TranslationsDeeplCommand extends Command
{
    use DeepltranslatorTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:deepl {baseLocale?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate all language lines in the database using Deepl API';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseLocale = $this->argument('baseLocale');
        $defaultLocale = $baseLocale ?: 'nl';

        $translationStrings = TranslationString::where('is_new', true)->get();

        $bar = $this->output->createProgressBar(count($translationStrings));

        $bar->start();

        foreach ($translationStrings as $translationString) {
            // Translations are already in dutch so we remove it here.
            $locales = Locale::whereNot('key', $defaultLocale)->pluck('key')->toArray();

            $translations = $this->translateString($translationString->text[$defaultLocale], $defaultLocale, $locales);
            $translations[$defaultLocale] = $translationString->text[$defaultLocale];
            $translationString->text = $translations;
            $translationString->is_new = false;
            $translationString->saveQuietly();
            $bar->advance();
        }

        $bar->finish();

        $this->newLine();
        $this->info('The command was successful! 🔥');
    }
}
