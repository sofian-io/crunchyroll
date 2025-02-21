<?php

namespace App\Console\Commands\Translations;

use App\Services\Translations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TranslationsExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:export

        { --ignore-groups=              : Groups that should not be imported (split,by,commas), ex: --ignore-groups=routes,admin/non-editable-stuff }
        { --only-groups=                : Only export given groups (split,by,commas), ex: admin/employer,frontend/* }
        { --a|allow-vendor              : Whether to export to vendor lang files or not }
        { --c|force-confirm             : Whether to skip confirming found translations }
        { --j|allow-json                : Whether to export to JSON lang files or not }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export translations back to the lang files.';

    protected $manager;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Translations $manager)
    {
        $this->manager = $manager;
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $error = 'Are you really sure you want to export translations from the database to the lang files?' . PHP_EOL . ' Existing translations will be overwritten, and translations that have not been imported will be lost.';

        if ($this->option('force-confirm')) {

            // Set options from the command context
            $options = [
                'allow-vendor' => $this->option('allow-vendor'),
                'allow-json' => $this->option('allow-json'),
                'ignore-groups' => $this->option('ignore-groups'),
                'only-groups' => $this->option('only-groups'),
            ];
            $this->manager->exportTranslations($options);

            $this->info('All translations have been exported.');
        } elseif ($this->confirm($error)) {
            // Set options from the command context
            $options = [
                'allow-vendor' => $this->option('allow-vendor'),
                'allow-json' => $this->option('allow-json'),
                'ignore-groups' => $this->option('ignore-groups'),
                'only-groups' => $this->option('only-groups'),
            ];
            $this->manager->exportTranslations($options);

            $this->info('All translations have been exported.');
        } else {
            $this->warn('Exporting cancelled.');
        }
        system('cp -r ../../../lang/ ../../../resources/');

        Artisan::call('optimize:clear');
    }
}
