<?php

namespace App\Models;

use App\Observers\TranslationStringObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Spatie\TranslationLoader\LanguageLine as TranslationLoaderLanguageLine;

#[ObservedBy([TranslationStringObserver::class])]
class TranslationString extends TranslationLoaderLanguageLine
{
    protected $table = 'translation_strings';

    protected $fillable = [
        'is_new',
        'text',
    ];
}
