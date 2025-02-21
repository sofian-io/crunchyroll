<?php

namespace App\Console\Commands\Translations;


use App\Concerns\DeepltranslatorTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TranslateVendor extends Command
{
    use DeepltranslatorTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:translate-vendor {baseLocale?}';

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
        $defaultLocale = $baseLocale ?: 'en';
        Artisan::call('translations:import');
        Artisan::call("translations:deepl $defaultLocale");
    }
}
