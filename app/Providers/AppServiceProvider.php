<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            class_alias(\App\Support\SimpleDomPdfBridge::class, \Barryvdh\DomPDF\Facade\Pdf::class);
        }

        if (! interface_exists(\Maatwebsite\Excel\Concerns\FromCollection::class)) {
            require_once app_path('Support/ExcelConcernsPolyfill.php');
        }

        if (! class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            class_alias(\App\Support\SimpleExcelBridge::class, \Maatwebsite\Excel\Facades\Excel::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
