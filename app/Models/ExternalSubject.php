<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalSubject extends Model
{
    protected $fillable = [
        'name',
        'key_stage',
        'year_group',
        'source'
    ];

    public function topics()
    {
        return $this->hasMany(ExternalTopic::class, 'subject_id')->orderBy('order_index');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_external_subject_enrollments', 'external_subject_id', 'user_id')
                    ->withPivot('enrolled_at', 'progress_percentage')
                    ->withTimestamps();
    }
}