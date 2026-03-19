<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = ['lesson_id', 'file_name', 'file_path', 'file_type'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}