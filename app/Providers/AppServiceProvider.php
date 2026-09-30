<?php

namespace App\Providers;

use App\Content\CourseContent;
use App\Services\CatalogService;
use App\Services\PurchaseService;
use App\Services\StripeService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One Stripe client per request, reused across the purchase services.
        $this->app->singleton(StripeService::class);

        // The course material is a few hundred kilobytes of JSON, read once and
        // held for the request. A page that needs a lesson and a paper list
        // reads the files once rather than once per lookup.
        $this->app->singleton(CourseContent::class);

        $this->app->singleton(CatalogService::class);

        $this->app->singleton(PurchaseService::class, function ($app) {
            return new PurchaseService($app->make(StripeService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
