<?php

namespace App\Console\Commands\Translations;

use App\Models\TranslationString;
use Illuminate\Console\Command;

class TranslateFixStrings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:translate-fix-strings {search?} {replace?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'QuickFix translation strings by replacing occurrences of a given string.';

    public function handle()
    {
        $search = $this->argument('search');
        $replace = $this->argument('replace');

        $errorStrings = [
            ':size-items' => ':size items',
            ':size tekens' => ':size tekens',
            ':attribute-veld' => ':attribute veld',
            ':min-cijfers' => ':min cijfers',
            ':min-items' => ':min items',
            ':value-tekens' => ':value tekens',
            ':value-items' => ':value items',
        ];

        if (! $search && ! $replace) {
            TranslationString::all()->each(function (TranslationString $translationString) use ($errorStrings) {
                // Create a modified copy
                foreach ($errorStrings as $search => $replace) {
                    $modifiedText = array_map(fn ($text) => str_replace($search, $replace, $text), $translationString->text);

                    // Assign the modified array back to the model
                    $translationString->text = $modifiedText;
                    $translationString->saveQuietly();
                }

            });
            $this->info('Replaced all defaults');
        } else {
            TranslationString::all()->each(function (TranslationString $translationString) use ($search, $replace) {
                // Create a modified copy
                $modifiedText = array_map(fn ($text) => str_replace($search, $replace, $text), $translationString->text);

                // Assign the modified array back to the model
                $translationString->text = $modifiedText;
                $translationString->saveQuietly();
            });

            $this->info("Replaced '{$search}' with '{$replace}' in all translation strings.");
        }

    }
}
