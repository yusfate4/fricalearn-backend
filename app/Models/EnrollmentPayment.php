<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'student_id',   // 🚀 ADDED: Essential for linking the child
        'course_id',
        'child_name',
        'amount',
        'currency',
        'receipt_path',
        'status',
        'approved_at'   // 🚀 ADDED: Essential for the Audit Log/History
    ];

    // --- 🤝 Relationships ---

    public function parent() 
    { 
        return $this->belongsTo(User::class, 'parent_id'); 
    }

    public function student() 
    { 
        return $this->belongsTo(User::class, 'student_id'); 
    }

    public function course() 
    { 
        return $this->belongsTo(Course::class); 
    }
}