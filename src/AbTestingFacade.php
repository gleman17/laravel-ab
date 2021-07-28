<?php

namespace Gleman17\AbTesting;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Gleman17\AbTesting\AbTesting
 */
class AbTestingFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ab-testing';
    }
}
