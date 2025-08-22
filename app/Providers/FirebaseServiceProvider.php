<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Messaging;


class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {

        if (app()->runningInConsole()) {
            return;
        }

        $credentialsPath = config('firebase.credentials');
        $credentialsPath = '/Users/viktorkarpinski/Desktop/Freelance/AppDevelopment/SAR/api/firebase_cred.json';


        Log::info($credentialsPath);

        if (!$credentialsPath || !file_exists($credentialsPath)) {
            throw new \Exception("The service account file does not exist at: " . $credentialsPath);
        }

        if (!is_readable($credentialsPath)) {
            throw new \Exception("The service HH account file is not readable at: " . $credentialsPath);
        }

        $this->app->singleton(Auth::class, function ($app) use ($credentialsPath) {
            Log::info("Creating Firebase Factory...");
            $firebaseFactory = (new Factory)
                ->withServiceAccount($credentialsPath);

            Log::info("Factory created, initializing Auth...");
            return $firebaseFactory->createAuth();
        });

        $this->app->singleton(Messaging::class, function ($app) use ($credentialsPath) {
            $firebaseFactory = (new Factory)->withServiceAccount($credentialsPath);
            return $firebaseFactory->createMessaging();
        });
    }

    public function boot()
    {
        // Additional boot logic if necessary
    }
}
