<?php

declare(strict_types=1);

namespace App\Tips;

/** A problem the visitor can fix (bad phone, amount out of range, …). */
final class TipException extends \RuntimeException
{
}
