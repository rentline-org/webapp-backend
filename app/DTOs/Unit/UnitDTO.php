<?php

namespace App\DTOs\Unit;

use Illuminate\Http\Request;

class UnitDTO
{
    /**
     * __construct
     *
     * @return void
    */
    public function __construct(){}

    public static function fromRequest(Request $request, ?self $existing = null): self
    {
        return new self();
    }
}
