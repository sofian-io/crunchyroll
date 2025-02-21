<?php

namespace App\Console\Commands\Translations;

use App\Concerns\DeepltranslatorTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TranslateAndFindCommand extends Command
{
    use DeepltranslatorTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:translate {baseLocale?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and Translate all new translations strings using a specified base locale';

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
        Artisan::call('translations:find --path=resources --force-confirm --with-string-keys');
        Artisan::call('translations:find --path=app --force-confirm --with-string-keys');
        Artisan::call("translations:deepl $defaultLocale");
        Artisan::call('app:translate-fix-strings');
        Artisan::call('optimize:clear');
    }
}
