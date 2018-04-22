<?php

/**
 * Class IntervalController
 */
class IntervalController extends  ApiController
{
    public function add()
    {
        return (string) rand(1, 100);
    }
}