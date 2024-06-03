<?php

namespace App\Contracts\Interfaces;

interface BCASnapRepositoryInterfaces
{
    /**
     * @param array $data
     * @return void
     */
    public function insert(array $data): void;
}
