<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Enums;

enum RequestType: string
{
    case Delete = 'delete';
    case Anonymize = 'anonymize';
    case Export = 'export';
}
