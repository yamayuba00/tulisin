<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat verifikasi webhook (signature/token) gagal.
 *
 * Ditangani khusus oleh controller webhook agar membalas 401 (bukan 422),
 * sehingga provider tahu request palsu dan tidak perlu mengulang.
 */
class WebhookVerificationException extends RuntimeException
{
}
