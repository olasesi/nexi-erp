<?php

namespace App\Enums;

enum ContactType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Lead = 'lead';
    case Both = 'both';
}
