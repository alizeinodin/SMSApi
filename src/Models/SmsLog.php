<?php

namespace Alizeinodin\SmsApi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'sms_logs';

    protected $fillable = [
        'driver',
        'operation',
        'mobile',
        'content',
        'template_id',
        'parameters',
        'status',
        'message_id',
        'pack_id',
        'delivery_status',
        'delivery_state',
        'delivered_at',
        'message',
        'response',
        'attempts',
        'cost',
    ];

    protected $casts = [
        'parameters' => 'array',
        'response' => 'array',
        'attempts' => 'array',
        'cost' => 'integer',
        'delivery_state' => 'integer',
        'delivered_at' => 'datetime',
    ];

    public function isSuccessful(): bool
    {
        return $this->status === 'sent';
    }

    public function isDelivered(): bool
    {
        return $this->delivery_status === 'delivered';
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('delivery_status', 'delivered');
    }

    public function scopePendingDelivery(Builder $query): Builder
    {
        return $query->where('delivery_status', 'pending');
    }

    public function scopeForMobile(Builder $query, string $mobile): Builder
    {
        return $query->where('mobile', $mobile);
    }

    public function scopeForDriver(Builder $query, string $driver): Builder
    {
        return $query->where('driver', $driver);
    }
}
