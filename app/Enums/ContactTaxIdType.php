<?php

namespace App\Enums;

enum ContactTaxIdType: string
{
    case CPF = 'cpf';
    case CNPJ = 'cnpj';
}
