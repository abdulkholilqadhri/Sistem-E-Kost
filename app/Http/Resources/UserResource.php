<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->name,
            'email' => $this->email,
            'no_hp' => $this->no_hp,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'status_akun' => $this->status_akun->value,
            'terdaftar_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
