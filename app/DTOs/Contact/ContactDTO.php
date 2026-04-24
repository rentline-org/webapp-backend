<?php

namespace App\DTOs\Contact;

use Illuminate\Http\Request;

class ContactDTO
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
