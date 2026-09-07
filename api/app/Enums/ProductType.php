<?php

namespace App\Enums;

enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';
    case Digital = 'digital';
    case Bundle = 'bundle';
}
