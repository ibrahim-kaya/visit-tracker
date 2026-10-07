<?php

namespace IbrahimKaya\VisitTracker\Tests;

use IbrahimKaya\VisitTracker\VisitTrackerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            VisitTrackerServiceProvider::class,
        ];
    }
}
