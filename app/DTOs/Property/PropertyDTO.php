<?php

namespace App\DTOs\Property;

use Illuminate\Http\Request;

class PropertyDTO
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
