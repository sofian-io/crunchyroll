<?php

namespace App\Observers;

use App\Models\TranslationString;
use Illuminate\Support\Facades\Artisan;

class TranslationStringObserver
{
    public function saved(TranslationString $translationString)
    {
        Artisan::call('translations:export --force-confirm');
    }
}
