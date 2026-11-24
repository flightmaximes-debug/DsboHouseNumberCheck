<?php

namespace DsboHouseNumberCheck\Providers;

use DsboHouseNumberCheck\Procedures\CheckDeliveryHouseNumber;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Plugin\ServiceProvider;

class HouseNumberCheckServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot(EventProceduresService $eventProceduresService)
    {
        $eventProceduresService->registerProcedure(
            'DsboHouseNumberCheck',
            ProcedureEntry::EVENT_TYPE_ORDER,
            [
                'de' => 'DSBO: Hausnummer prüfen und bei Fehlen Status 5.1 setzen',
                'en' => 'DSBO: Check delivery house number and set status 5.1 if missing',
            ],
            CheckDeliveryHouseNumber::class . '@execute'
        );
    }
}
