<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    use HasFactory;

    protected $table = 'contact_submissions';

    protected $fillable = [
        'type',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'metadata',
        'status',
        'read_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'enrollment' => 'Course Enrollment',
            'assessment' => 'Free Assessment',
            'tutor'      => 'Tutor Application',
            default      => 'General Inquiry',
        };
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            'enrollment' => 'badge-light-primary text-primary',
            'assessment' => 'badge-light-success text-success',
            'tutor'      => 'badge-light-warning text-warning',
            default      => 'badge-light-info text-info',
        };
    }
}
