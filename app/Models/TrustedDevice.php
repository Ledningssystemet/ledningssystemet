<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device (browser) that a user has chosen to trust while completing the
 * two-factor challenge, so that MFA is skipped for subsequent logins from
 * that device until it expires. A user can have several trusted devices at
 * the same time.
 */
class TrustedDevice extends Model
{
   protected $fillable = [
      'user_id',
      'token_hash',
      'device_name',
      'user_agent',
      'ip_address',
      'last_used_at',
      'expires_at',
   ];

   protected $casts = [
      'last_used_at' => 'datetime',
      'expires_at' => 'datetime',
   ];

   public function user(): BelongsTo
   {
      return $this->belongsTo(User::class);
   }

   public function scopeNotExpired(Builder $query): Builder
   {
      return $query->where('expires_at', '>', now());
   }
}
