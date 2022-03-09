<?php
namespace Alizne\SmsApi;

use Illuminate\Support\ServiceProvider;

class SmsApiServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/SMSApi.php' => config_path('SMSApi.php')
        ]);
    }

    public function register()
    {
        $this->app->singleton(SMSApi::class, function (){
            return new SMSApi();
        });
    }
}
