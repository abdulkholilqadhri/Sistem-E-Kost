<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Trait AuthorizesRequests tidak lagi disertakan Laravel pada controller dasar.
 * Ditambahkan di sini agar $this->authorize() tersedia di seluruh controller,
 * karena otorisasi tingkat objek pada aplikasi ini dikerjakan oleh Policy dan
 * dipanggil dari controller.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
