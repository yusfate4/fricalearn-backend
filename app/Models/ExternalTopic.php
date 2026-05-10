<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalTopic extends Model
{
    protected $fillable = [
        'subject_id',
        'title',
        'description',
        'order_index',
        'external_id'
    ];

    public function subject()
    {
        return $this->belongsTo(ExternalSubject::class, 'subject_id');
    }

    public function lessons()
    {
        return $this->hasMany(ExternalLesson::class, 'topic_id')->orderBy('order_index');
    }
}