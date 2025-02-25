<?php

namespace App\Services;

use App\Models\Locale;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

use stdClass;
use Symfony\Component\Finder\Finder;

class Translations
{
    const JSON_GROUP = '_json';

    const LOGGING = [
        'info' => "\033[32m%s\033[0m",
        'error' => "\033[41m%s\033[0m",
        'warn' => "\033[33m%s\033[0m",
        'danger' => "\033[31m%s\033[0m",
    ];

    /** @var \Illuminate\Console\Command; */
    public $command;

    /** @var \Illuminate\Contracts\Foundation\Application */
    protected $app;

    /** @var \Illuminate\Filesystem\Filesystem */
    protected $files;

    /** @var \Illuminate\Contracts\Events\Dispatcher */
    protected $events;

    /** @var array */
    protected $databaseData;

    /** @var array */
    protected $options;

    /**
     * Manager constructor.
     */
    public function __construct(Application $app, Filesystem $files, Dispatcher $events)
    {
        $this->app = $app;
        $this->files = $files;
        $this->events = $events;

        $databaseData = [
            'table' => config('translation-loader.table'),
            'groupColumn' => config('translation-loader.group'),
            'keyColumn' => config('translation-loader.key'),
            'translationColumn' => config('translation-loader.translations'),
        ];

        if (array_search('', $databaseData) !== false) {
            $error = 'Table and column values cannot be null/empty! Ensure table, group, key and translations are set in config/translation-loader.php!';
            error_log(sprintf(self::LOGGING['error'], $error));
            exit;
        }

        $this->databaseData = $databaseData;
    }

    //##########################################
    //
    //   Import
    //
    //##########################################

    /**
     * Process to import all translations.
     *
     * @param  array  $options
     * @param  string  $vendorPath
     * @return int|mixed
     */
    public function importTranslations($options = [], $vendorPath = '')
    {
        $logInfo = '';
        $vendor = false;

        // Set options
        $this->options = $options;

        // Set the lang path
        $base = $this->app->basePath('lang');

        // Change lang path if it's a vendor directory
        if (! empty($vendorPath)) {
            $base = $vendorPath;
            $logInfo = ' for vendor package ' . basename($base);
            $vendor = true;
        }

        $counter = 0;

        // Loop through all directories in the base path
        foreach ($this->files->directories($base) as $langPath) {
            // Get locale from path
            $locale = basename($langPath);

            // If the locale can be imported (and is not in the ignore-locales)
            if ($this->localeCanBeImported($locale) || $locale == 'vendor') {
                if ($locale == 'vendor') {
                    if ($this->options['allow-vendor']) {
                        // Pass all packages as new langpath and rerun this function
                        foreach ($this->files->directories($langPath) as $vendorPath) {
                            $counter += $this->importTranslations($this->options, $vendorPath);
                        }
                    }
                } else {
                    error_log(sprintf(self::LOGGING['info'], "Processing locale '{$locale}'{$logInfo}"));

                    // Get the directory route of the locale, then the name of the directory (which is the package)
                    $packageName = $this->files->name($this->files->dirname($langPath));

                    // Loop through all files in the locale
                    foreach ($this->files->allfiles($langPath) as $file) {
                        $info = pathinfo($file);
                        $group = $info['filename'];

                        // Ensure separator consistency
                        $subLangPath = str_replace($langPath . DIRECTORY_SEPARATOR, '', $info['dirname']);
                        $subLangPath = str_replace(DIRECTORY_SEPARATOR, '/', $subLangPath);
                        $langPath = str_replace(DIRECTORY_SEPARATOR, '/', $langPath);

                        if ($subLangPath != $langPath) {
                            $group = $subLangPath . '/' . $group;
                        }

                        if ($this->groupCanBeProcessed($group)) {
                            if ($vendor) {
                                // Set group if vendor
                                $group = "vendor/{$packageName}/{$group}";
                            }
                            $translations = include $file;

                            // Loop through all translations
                            if ($translations && is_array($translations)) {
                                // Convert nested array keys to dots ('auth' => [ 'login' => 'Login', ], to auth.login
                                foreach (Arr::dot($translations) as $key => $value) {
                                    // Import the translation
                                    // Add to the counter if the translation was successful
                                    if ($this->importTranslation($key, $value, $locale, $group) === true) {
                                        $counter++;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                error_log(sprintf(self::LOGGING['warn'], "Skipping locale '{$locale}'{$logInfo}"));
            }
        }

        // Only process if we can process JSONs and we're not currently processing Vendor files
        if ($this->options['allow-json'] && ! $vendor) {
            // Loop through all JSON files
            foreach ($this->files->files($this->app->basePath('lang')) as $jsonTranslationFile) {
                // Only continue if it's a valid .json
                if (strpos($jsonTranslationFile, '.json') !== false) {
                    // Get locale from filename
                    $locale = basename($jsonTranslationFile, '.json');
                    // If it can be imported
                    if ($this->localeCanBeImported($locale)) {
                        error_log(sprintf(self::LOGGING['info'], "Processing JSON locale '{$locale}'"));

                        // Continue only if we can process the JSON group
                        $group = self::JSON_GROUP;
                        if ($this->groupCanBeProcessed($group)) {
                            // Retrieves JSON entries of the given locale only
                            // We can't use include here as the JSON file doesn't have a return statement
                            $translations = json_decode($jsonTranslationFile->getContents(), true);

                            // Import all translations from the JSON
                            if ($translations && is_array($translations)) {
                                foreach ($translations as $key => $value) {
                                    if ($this->importTranslation($key, $value, $locale, $group)) {
                                        $counter++;
                                    }
                                }
                            }
                        }
                    } else {
                        error_log(sprintf(self::LOGGING['warn'], "Skipping JSON locale '{$locale}'"));
                    }
                }
            }
        }

        return $counter;
    }

    /**
     * Process the import a singular translation.
     *
     * @return bool
     */
    public function importTranslation($key, $value, $locale, $group)
    {
        // Process only string values
        if (is_array($value)) {
            return false;
        }

        $table = $this->databaseData['table'];
        $groupColumn = $this->databaseData['groupColumn'];
        $keyColumn = $this->databaseData['keyColumn'];
        $translationColumn = $this->databaseData['translationColumn'];

        // See if a translation already exists
        $translation = DB::table($table)
            ->where($groupColumn, $group)
            ->where($keyColumn, $key)
            ->first();

        // If a translation does exist
        if ($translation instanceof stdClass) {
            $text = json_decode($translation->{$translationColumn}, true);

            // If the locale is not set, or if replace is true, or if the translation is empty
            if ((! isset($text[$locale]) || $this->options['overwrite'] || empty($text[$locale])) && $value !== '') {
                // Update the translation
                $text[$locale] = $value;
                $translation->{$translationColumn} = json_encode($text);

                DB::table($table)
                    ->where($groupColumn, $group)
                    ->where($keyColumn, $key)
                    ->update((array) $translation);

                return true;
            }
        } else {
            $locales = Locale::pluck('key')->toArray();
            $text = [
                $locale => $value,
            ];
            foreach ($locales as $supportedLocale) {
                if ($supportedLocale !== $locale) {
                    $text[$supportedLocale] = '';
                }
            }

            // Insert the translation into the database from the config
            DB::table($table)
                ->insert([
                    $groupColumn => $group,
                    $keyColumn => Str::lower($key),
                    $translationColumn => json_encode($text),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            return true;
        }

        return false;
    }

    //##########################################
    //
    //   Export
    //
    //##########################################

    /**
     * Process to export all translations.
     */
    public function exportTranslations($options)
    {
        // Set options
        $this->options = $options;

        // Get all groups
        $groupObjects = DB::table($this->databaseData['table'])
            ->select('group')
            ->groupBy('group')
            ->get();

        foreach ($groupObjects as $groupObject) {
            $group = $groupObject->group;

            $vendor = false;
            $json = false;
            if (Str::startsWith($group, 'vendor')) {
                $vendor = true;
            } elseif ($group == self::JSON_GROUP) {
                $json = true;
            }

            // Process separately if it's a vendor group or JSON
            if ($vendor) {
                if ($this->options['allow-vendor']) {
                    $vendorGroup = $group;
                    // Get an array of each nesting
                    $subfolders = explode(DIRECTORY_SEPARATOR, $group);
                    // Set the actual group
                    $group = implode(DIRECTORY_SEPARATOR, array_slice($subfolders, 2));

                    $this->processExportForGroup($group, $vendorGroup);
                }
            } elseif ($json) {
                if ($this->options['allow-json']) {
                    $this->processExportForGroup($group);
                }
            } else {
                $this->processExportForGroup($group);
            }
        }
    }

    /**
     * Processes the export of each group.
     *
     * @param  null  $vendorGroup
     */
    public function processExportForGroup($group, $vendorGroup = null)
    {
        if ($this->groupCanBeProcessed($group)) {
            // Get all translations by group
            $translations = DB::table($this->databaseData['table'])
                ->where($this->databaseData['groupColumn'], $vendorGroup ?? $group)
                ->get();

            // Make a tree for this group
            $tree = $this->makeTree($translations);

            $this->exportTranslationGroup($tree, $group, $vendorGroup);
        }
    }

    /**
     * Converts the tree into valid lang files.
     *
     * @param  null  $vendorGroup
     */
    public function exportTranslationGroup($tree, $group, $vendorGroup = null)
    {
        $json = false;
        if ($group == self::JSON_GROUP) {
            $json = true;
        }

        if (! $json) {
            // Loop through all groups
            foreach ($tree as $locale => $groups) {
                // Only process if the current group is present
                if (isset($groups[$vendorGroup ?? $group])) {
                    // Get the translations for this group
                    $translations = $groups[$vendorGroup ?? $group];

                    // Set the lang path
                    $base = $this->app->basePath('lang');

                    // If the full group exists, and is a vendor group
                    if (isset($vendorGroup) && Str::startsWith($vendorGroup, 'vendor')) {
                        // Construct the proper path to the locale
                        $vendorGroup = str_replace("/{$group}", '', $vendorGroup);

                        $localePath = $vendorGroup . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $group;
                    } else {
                        // Define the localePath, based of locale and group
                        $localePath = $locale . DIRECTORY_SEPARATOR . $group;
                    }

                    // Get an array of each nesting
                    $subfolders = explode(DIRECTORY_SEPARATOR, $localePath);
                    // Remove the last item (which is the actual .php file)
                    array_pop($subfolders);

                    $subfolder_level = '';
                    // Loop through each subfolder to validate the full path
                    foreach ($subfolders as $subfolder) {
                        // Define the path to the current subfolder
                        $subfolder_level = $subfolder_level . $subfolder . DIRECTORY_SEPARATOR;
                        // Build a path
                        $temp_path = rtrim($base . DIRECTORY_SEPARATOR . $subfolder_level, DIRECTORY_SEPARATOR);

                        // If the directory doesn't exist, ensure to make it
                        if (! is_dir($temp_path)) {
                            mkdir($temp_path, 0775, true);
                        }
                    }
                    // The path is now fully validated

                    // Define the path of the
                    $filePath = $base . DIRECTORY_SEPARATOR . $localePath . '.php';

                    // Convert the translations into valid PHP code to be written to the file
                    $output = "<?php\n\nreturn " . $this->fancyVarExport($translations) . ';' . \PHP_EOL;
                    // Write the translations to the file
                    $this->files->put($filePath, $output);
                }
            }
        } else {
            foreach ($tree as $locale => $groups) {
                if (isset($groups[$group])) {
                    $translations = $groups[$group];
                    $filePath = $this->app->basePath('lang') . '/' . $locale . '.json';
                    $output = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $this->files->put($filePath, $output);
                }
            }
        }
    }

    //##########################################
    //
    //   Find
    //
    //##########################################

    public function findTranslations($options = [])
    {
        $this->options = $options;

        $path = $this->options['path'] ?: base_path();
        $groupKeys = [];
        $stringKeys = [];
        $functions = config('translation-loader.trans_functions');

        // Match for all group based translations in the given functions (e.g. admin/
        $groupPattern =                             // See https://regex101.com/r/WEJqdL/19
            "[^\w|]" .                              // Must not have an alphanum or _ before real method
            '(?<!->)' .                             // Must not have an object operator (->) before the method
            '(' . implode('|', $functions) . ')' .  // Must start with one of the functions
            "\(" .                                   // Match opening parenthesis
            "[\'\"]" .                               // Match " or '
            '(' .                                    // Start a new group to match:
            '[\/a-zA-Z0-9_-]+' .                     // Must start with group
            '([.](?! )' .                            // Be followed by one or more items/keys
            "(?(?=['\"])(?<=\\\\)['\"]|[^\1)])+" .   // Of which the next quotes are only allowed if preceded by a \
            ')+)' .                                  // Close group
            "[\'\"]" .                               // Closing quote
            "[\),]";                                // Close parentheses or new parameter

        // Match for all strings in the same functions, for JSON
        $stringPattern =                                    // See https://regex101.com/r/WEJqdL/6
            "[^\w]" .                                        // Must not have an alphanum before real method
            '(' . implode('|', $functions) . ')' .          // Must start with one of the functions
            "\(\s*" .                                        // Match opening parenthesis
            "(?P<quote>['\"])" .                             // Match " or ' and store in {quote}
            "(?P<string>(?:\\\k{quote}|(?!\k{quote}).)*)" .  // Match any string that can be {quote} escaped
            "\k{quote}" .                                    // Match " or ' previously matched
            "\s*[\),]";                                     // Close parentheses or new parameter

        // Find all PHP + Twig files in the app folder, except for storage and vendor
        $finder = new Finder;
        $finder->in($path)
            ->exclude('storage')
            ->exclude('vendor')
            ->name('*.php')
            ->name('*.vue')
            ->files();

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            // Search the current file for the patterns
            if (preg_match_all("/$groupPattern/siU", $file->getContents(), $matches)) {
                // Get all matches
                foreach ($matches[2] as $key) {
                    $groupKeys[] = $key;
                }
            }

            if (preg_match_all("/$stringPattern/siU", $file->getContents(), $matches)) {
                foreach ($matches['string'] as $key) {
                    if (preg_match("/(^[a-zA-Z0-9_-]+([.][^\1)\ ]+)+$)/siU", $key, $groupMatches)) {
                        // group{.group}.key format, already in $groupKeys but also matched here
                        // do nothing, it has to be treated as a group
                        continue;
                    }

                    if (! (Str::contains($key, '::') && Str::contains($key, '.'))
                        || Str::contains($key, ' ')) {
                        $stringKeys[] = $key;
                    }
                }
            }
        }
        // Remove duplicates
        $groupKeys = array_unique($groupKeys);
        $stringKeys = array_unique($stringKeys);
        // As string matches also contain groups, we remove duplicates
        $stringKeys = array_diff($stringKeys, $groupKeys);

        $counter = 0;

        // Add the translations to the database, if not existing.
        foreach ($groupKeys as $key) {
            // Split the group and item
            [$group, $item] = explode('.', $key, 2);
            $newTranslation = $this->missingKey($group, $item);
            $counter += $newTranslation ? 1 : 0;
        }

        if ($this->options['with-string-keys']) {
            foreach ($stringKeys as $key) {
                $group = self::JSON_GROUP;
                $item = $key;
                $newTranslation = $this->missingKey($group, $item);
                $counter += $newTranslation ? 1 : 0;
            }
        }

        // Return the number of processed translations
        return $counter;
    }

    /**
     * Adds the translation to the database if it doesn't exist.
     */
    public function missingKey($group, $key): bool
    {
        $translation = DB::table($this->databaseData['table'])
            ->where($this->databaseData['groupColumn'], $group)
            ->where($this->databaseData['keyColumn'], $key)
            ->first();

        // Only process if it doesn't exist
        if (is_null($translation) && ($this->options['force-confirm'] || $this->command->confirm("Do you want to import group '{$group}' with key '{$key}'?", true))) {
            $translations = $this->getTranslationValues($group, $key);

            DB::table($this->databaseData['table'])
                ->insert([
                    $this->databaseData['groupColumn'] => $group,
                    $this->databaseData['keyColumn'] => $key,
                    $this->databaseData['translationColumn'] => $translations,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            return true;
        }

        return false;
    }

    /**
     * Gets all values for the found translation.
     *
     * @return string JSON
     */
    public function getTranslationValues($group, $key)
    {
        $base = $this->app->basePath('lang');
        $translations = [];

        if ($group == self::JSON_GROUP) {
            // Loop through all JSON files
            foreach ($this->files->files($this->app->basePath('lang')) as $jsonTranslationFile) {
                // Only continue if it's a valid .json
                if (strpos($jsonTranslationFile, '.json') !== false) {
                    // Get locale from filename
                    $locale = basename($jsonTranslationFile, '.json');
                    // Get json data from the file
                    $json = json_decode($jsonTranslationFile->getContents(), true);

                    $translations[$locale] = $json[$key] ?? '';
                }
            }
        } else {
            // Loop through all locales
            foreach ($this->files->directories($base) as $langPath) {
                // Get locale from path
                $locale = basename($langPath);
                $filePath = $langPath . DIRECTORY_SEPARATOR . $group . '.php';
                if ($locale != 'vendor') {
                    // If the file doesn't exist, we can't get a translation for it either
                    if (file_exists($filePath)) {
                        // Get translations
                        $translationsFromFile = include $filePath;

                        // Since a translation file can exist of nested arrays we have to loop
                        $keyParts = explode('.', $key);
                        // We can always get the first key
                        $currentTrans = $translationsFromFile[$keyParts[0]] ?? '';
                        // If the found translation is an array, and there's more than 1 key part,
                        // we can continue, otherwise we already reached the translation
                        $count = count($keyParts);
                        if ($count > 1 && is_array($currentTrans)) {
                            for ($i = 1; $i < $count; $i++) {
                                $currentTrans = $currentTrans[$keyParts[$i]] ?? '';
                            }
                        }

                        // Now we either have a translation or an empty value, which we can append
                        $translations[$locale] = $currentTrans;
                    }
                }
            }
        }
        if (empty($translations['nl'])) {
            $translations['nl'] = $key;
            $translations['en'] = $key;
        }

        $locales = Locale::pluck('key')->toArray();
        foreach ($locales as $supportedLocale) {
            if (! array_key_exists($supportedLocale, $translations)) {
                $translations[$supportedLocale] = '';
            }
        }

        return json_encode($translations);
    }

    //##########################################
    //
    //   Clean
    //
    //##########################################

    /**
     * Deletes all translations with empty values from the database.
     */
    public function cleanTranslations(): int
    {
        $translations = DB::table($this->databaseData['table'])
            ->get();

        $counter = 0;
        $ids = [];
        foreach ($translations as $translation) {
            $text = json_decode($translation->{$this->databaseData['translationColumn']}, true);

            $total = count($text);
            $empty = 0;
            foreach ($text as $locale => $value) {
                if (empty($value)) {
                    $empty++;
                }
            }
            if ($total == $empty) {
                $counter++;
                $ids[] = $translation->id;
            }
        }

        DB::table($this->databaseData['table'])
            ->whereIn('id', $ids)
            ->delete();

        return $counter;
    }

    //##########################################
    //
    //   Nuke
    //
    //##########################################

    public function deleteTranslations($options = [])
    {
        $query = DB::table($this->databaseData['table']);

        if (! empty($options['only-groups'])) {
            $groups = explode(',', $options['only-groups']);

            $query->where(function ($q) use ($groups) {
                foreach ($groups as $group) {
                    if (Str::endsWith($group, '/*')) {
                        $group = str_replace('*', '%', $group);
                        $q->orWhere($this->databaseData['groupColumn'], 'like', $group);
                    } else {
                        $q->orWhere($this->databaseData['groupColumn'], $group);
                    }
                }
            });
        }

        $query->delete();
    }

    //##########################################
    //
    //   Functions
    //
    //##########################################

    /**
     * Checks if a locale can be imported
     */
    public function localeCanBeImported($locale): bool
    {
        return ! in_array($locale, explode(',', $this->options['ignore-locales']));
    }

    /**
     * Checks if a group can be processed
     */
    public function groupCanBeProcessed($group): bool
    {
        $groupsToProcess = explode(',', $this->options['only-groups']);
        $groupsToIgnore = explode(',', $this->options['ignore-groups']);

        $canProcess = false;
        $ignore = false;

        if (! is_null($this->options['only-groups'])) {
            // Loop through all groups that were set in the options
            foreach ($groupsToProcess as $groupToProcess) {
                // If the option ends in a wildcard
                if (Str::endsWith($groupToProcess, '/*')) {
                    // Check if the group being checked starts with the group in the option
                    $groupToProcess = substr($groupToProcess, 0, -2);
                    if (Str::startsWith($group, $groupToProcess)) {
                        $canProcess = true;
                    }
                } elseif ($groupToProcess == $group) {
                    $canProcess = true;
                }
            }
        } else {
            $canProcess = true;
        }

        if (! is_null($this->options['ignore-groups'])) {
            // Loop through all groups that were set in the options
            foreach ($groupsToIgnore as $groupToIgnore) {
                // If the option ends in a wildcard
                if (Str::endsWith($groupToIgnore, '/*')) {
                    // Check if the group being checked starts with the group in the option
                    $groupToIgnore = substr($groupToIgnore, 0, -2);
                    if (Str::startsWith($group, $groupToIgnore)) {
                        $ignore = true;
                    }
                } elseif ($groupToIgnore == $group) {
                    $ignore = true;
                }
            }
        }

        return $canProcess && ! $ignore;
    }

    /**
     * Makes the default var_export fancy
     *
     * @return string|string[]|null
     */
    public function fancyVarExport($expression)
    {
        $export = var_export($expression, true);
        // Patterns to replace parts
        $transformPatterns = [
            "/array \(/" => '[',                // Matches all array opening functions and brackets
            "/(?<![\S])\)/" => ']',             // Matches all closing brackets (can only be preceded by a space)
            '/(?<==> )' . PHP_EOL . '/' => '',  // Matches the newlines before array opening brackets
            "/(?<==>)[ ]*(?=\[)/" => ' ',       // Matches all white space between an arrow and an opening bracket
        ];
        $export = preg_replace(array_keys($transformPatterns), array_values($transformPatterns), $export);
        // Patterns to double up on whitespace
        $indentPatterns = [
            "/(?<!=>)[ ]+(?='[\w])/",   // Matches all spaces before a translation
            "/[ ]+(?=[\]])/",           // Matches all spaces before a closing bracket
        ];

        $export = preg_replace_callback(
            array_values($indentPatterns),
            function ($matches) {
                return str_repeat($matches[0], 2);
            },
            $export
        );

        return $export;
    }

    /**
     * Build a nested tree array.
     *
     * @param  object  $translations
     * @return array
     */
    protected function makeTree($translations)
    {
        $array = [];
        foreach ($translations as $translation) {
            // Retrieve the translation values
            $text = json_decode($translation->{$this->databaseData['translationColumn']}, true);
            // Loop through all locales
            foreach ($text as $locale => $value) {
                // Build a tree nested array, shaped as locale => groups => keys => value
                Arr::set(
                    $array[$locale][$translation->{$this->databaseData['groupColumn']}],
                    $translation->{$this->databaseData['keyColumn']},
                    $value
                );
            }
        }

        return $array;
    }
}
