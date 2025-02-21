<?php

use App\Models\Setting;



if (! function_exists('get_setting')) {
    function get_setting($settingKey, $default = null)
    {
        return Setting::firstWhere('key', $settingKey)->value ?? $default;
    }
}

