<?php

namespace App\Enums;

enum TaxIDType: string
{
    case CPF = 'cpf';
    case CPNJ = 'cpnj';
    case VAT = 'vat';
}
