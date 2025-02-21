<?php

return [

    /*
     * Language lines will be fetched by these loaders. You can put any class here that implements
     * the Spatie\TranslationLoader\TranslationLoaders\TranslationLoader-interface.
     */
    'translation_loaders' => [
        Spatie\TranslationLoader\TranslationLoaders\Db::class,
    ],

    /*
     * This is the model used by the Db Translation loader. You can put any model here
     * that extends Spatie\TranslationLoader\LanguageLine.
     */
    'model' => App\Models\TranslationString::class,

    /*
     * This is the translation manager which overrides the default Laravel `translation.loader`
     */
    'translation_manager' => Spatie\TranslationLoader\TranslationLoaderManager::class,

    /**
     * The table where the translations are stored
     */
    'table' => 'translation_strings',

    /**
     * The column where the translations group should be stored
     */
    'group' => 'group',

    /**
     * The column where the translation key should be stored
     */
    'key' => 'key',

    /**
     * The column where the translation text itself should be stored
     */
    'translations' => 'text',

    /**
     * Array of functions which are used to get translations
     */
    'trans_functions' => [
        'trans',
        'trans_choice',
        'Lang::uri',
        'Lang::get',
        'Lang::choice',
        'Lang::trans',
        'Lang::transChoice',
        '@lang',
        '@choice',
        '__',
        '$trans.get',
    ],

];
