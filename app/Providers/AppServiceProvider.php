<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Cloudinary\Configuration\Configuration;

class AppServiceProvider extends ServiceProvider
{
    
    public function register(): void
    {
        //
    }

   
    public function boot(): void
    {
        
        $cloudinaryUrl = config('services.cloudinary.url');

        if ($cloudinaryUrl) {
            Configuration::instance($cloudinaryUrl);
        }
    }
}