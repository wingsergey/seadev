<?php

/**
 * Class IndexController
 * @package App
 */
class IndexController extends  ApiController
{
    public function index()
    {
        return (string) rand(1, 100);
    }
}