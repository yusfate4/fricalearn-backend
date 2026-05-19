<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentPayment extends Model
{
    use HasFactory;

 protected $fillable = [
    'parent_id',  // ← Make sure this is here!
    'course_id',
    'amount',
    'currency',
    'receipt_path',
    'child_name',
    'status',
    'auto_approved',
    'includes_maths',
    'includes_english',
    'includes_yoruba',
    'includes_hausa',
    'includes_igbo',
    // ... other fields
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