<?php

/**
 * Class ApiController
 * @package App
 */
abstract class ApiController
{
    /*********************** CRUD **********************/

    /**
     * @param string $table
     * @param int $id
     */
    public function read(string $table, int $id = 0)
    {

    }

    /**
     * @param string $table
     * @param array $data
     */
    public function create(string $table, array $data = [])
    {

    }

    /**
     * @param string $table
     * @param array $data
     */
    public function update(string $table, array $data = [])
    {

    }

    /**
     * @param string $table
     * @param int $id
     */
    public function delete(string $table, int $id)
    {

    }
}