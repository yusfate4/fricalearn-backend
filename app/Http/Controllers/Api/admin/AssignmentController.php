<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function show($id)
    {
        $assignment = Assignment::findOrFail($id);
        return response()->json($assignment);
    }

    // Student submits an assignment
    public function submit(Request $request, $id)
    {
        $assignment = Assignment::findOrFail($id);
        
        $validated = $request->validate([
            'submission_text' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:5120',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('submissions', 'public');
        }

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $request->user()->id,
            'submission_text' => $validated['submission_text'],
            'file_url' => $filePath,
            'status' => 'pending',
        ]);

        return response()->json($submission, 201);
    }

    // Tutor/Admin grades the assignment
    public function grade(Request $request, $id)
    {
        $submission = AssignmentSubmission::findOrFail($id);
        
        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $submission->update([
            'score' => $validated['score'],
            'feedback' => $validated['feedback'],
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
            'status' => 'graded',
        ]);

        return response()->json($submission);
    }
}