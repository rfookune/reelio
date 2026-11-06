<?php

arch('services do not depend on the HTTP layer')
    ->expect('App\Services')
    ->not->toUse('App\Http');

arch('Ratings does not depend on Catalog')
    ->expect('App\Services\Ratings')
    ->not->toUse('App\Services\Catalog');
