<?php

namespace App\Services\Gateway;

use RuntimeException;

/** Gateway tidak bisa dihubungi atau menolak permintaan. */
class GatewayException extends RuntimeException
{
}
