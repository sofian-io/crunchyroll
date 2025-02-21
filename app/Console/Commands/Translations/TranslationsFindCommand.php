<?php

namespace App\Console\Commands\Translations;

use App\Services\Translations;
use Illuminate\Console\Command;

class TranslationsFindCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:find
        { --path=                       : A specific directory path to find translations in }
        { --c|force-confirm             : Whether to skip confirming found translations }
        { --s|with-string-keys          : Also import _json }
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find translations in php files';

    protected $manager;

    public function __construct(Translations $manager)
    {
        $this->manager = $manager;
        $this->manager->command = $this;
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->option('path');
        // Ensure the path is valid
        if (isset($path) && ! (file_exists($path) && is_dir($path))) {
            $this->error('This is not a valid directory path! Ensure the path exists.');
            exit;
        }
        $options = [
            'path' => $path,
            'force-confirm' => $this->option('force-confirm'),
            'with-string-keys' => $this->option('with-string-keys'),
        ];
        $counter = $this->manager->findTranslations($options);
        $this->info("A total of {$counter} translations have been found and imported.");
    }
}
