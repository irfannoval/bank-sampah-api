<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    const UPDATED_AT = null; // tabel users hanya punya created_at

    protected $table = 'users';

    protected $fillable = ['nama', 'no_hp', 'pin_hash', 'role', 'status'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['terkunci_sampai' => 'datetime'];
    }

    /** Login memakai PIN, bukan password. */
    public function getAuthPassword(): string
    {
        return $this->pin_hash;
    }
}
