<?php

namespace App\Contracts;

interface Pingable
{
    /**
     * Checks connectivity to the API and returns an associative array.
     *
     * Example structure:
     * [
     *   'success' => bool,
     *   'message' => string,
     * ]
     */
    public function ping(): array;
}
