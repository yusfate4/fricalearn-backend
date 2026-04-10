<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Cloudinary\Cloudinary; 

class CourseController extends Controller
{
    /**
     * 🚀 Helper to get a configured Cloudinary instance
     */
    private function getCloudinary()
    {
        return new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
        ]);
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // 🕵️ Get ID from Header or Query Param
            $activeStudentId = $request->header('X-Active-Student-Id') ?: $request->query('student_id');

            // 👑 1. ADMIN: See everything
            if ($user && ($user->role === 'admin' || $user->is_admin == 1)) {
                return response()->json(Course::withCount(['modules'])->latest()->get());
            }

            /**
             * 🛒 2. PARENT SHOPPING / GENERAL VIEW
             */
            if (!$activeStudentId || ($user && $user->role === 'parent' && empty($activeStudentId))) {
                $catalog = Course::where('is_published', true)
                    ->withCount(['modules'])
                    ->latest()
                    ->get();
                    
                return response()->json($catalog);
            }

            // 🛡️ 3. STUDENT / CLASSROOM VIEW: Only show PAID/ACTIVE courses
            $enrolledCourseIds = CourseEnrollment::where('student_id', $activeStudentId)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->pluck('course_id');

            return response()->json(
                Course::whereIn('id', $enrolledCourseIds)
                    ->where('is_published', true)
                    ->withCount(['modules'])
                    ->latest()
                    ->get()
            );
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 👨‍👩‍👧‍👦 Parent: Fetch courses for browsing or impersonation
     */
    public function getParentCourses(Request $request)
    {
        try {
            $activeStudentId = $request->header('X-Active-Student-Id') ?: $request->query('student_id');

            if ($activeStudentId) {
                $enrolledCourseIds = CourseEnrollment::where('student_id', $activeStudentId)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->pluck('course_id');

                return response()->json(
                    Course::whereIn('id', $enrolledCourseIds)
                        ->where('is_published', true)
                        ->withCount('modules')
                        ->get()
                );
            }

            return response()->json(Course::where('is_published', true)->withCount('modules')->get());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 📖 Show a specific course
     */
    public function show($id)
    {
        try {
            $course = Course::with([
                'modules' => function($query) {
                    $query->orderBy('order_index', 'asc');
                },
                'modules.lessons'
            ])->find($id);

            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }

            return response()->json($course);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Database Query Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔐 Admin: Store (Cloudinary Integrated)
     */
 public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:courses',
            'category' => 'required|string', 
            'subject' => 'required|string',
            'level' => 'required|string',
            'description' => 'required|string',
            'price_ngn' => 'required|numeric',
            'price_gbp' => 'required|numeric',
            'image' => 'required|image|max:5120', 
        ]);

        try {
            $cloudinary = $this->getCloudinary();
            
            $upload = $cloudinary->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'fricalearn/courses']
            );
            
            $uploadedFileUrl = $upload['secure_url'];

            // 🛡️ Remove 'image' from array before creating, add 'thumbnail_url'
            $courseData = $validated;
            unset($courseData['image']);

            $course = Course::create(array_merge($courseData, [
                'thumbnail_url' => $uploadedFileUrl,
                'created_by' => auth()->id(),
                'is_published' => true,
            ]));

            return response()->json($course, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Cloudinary Upload Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 📝 Admin: Update (Cloudinary Integrated)
     */
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        
        // Don't validate 'title' as unique if it's the same course
        $validated = $request->validate([
            'title' => 'string|max:255|unique:courses,title,' . $id,
            'category' => 'string',
            'subject' => 'string',
            'level' => 'string',
            'description' => 'string',
            'price_ngn' => 'numeric',
            'price_gbp' => 'numeric',
            'image' => 'nullable|image|max:5120',
        ]);

        try {
            $data = $request->except('image');

            if ($request->hasFile('image')) {
                $cloudinary = $this->getCloudinary();
                
                $upload = $cloudinary->uploadApi()->upload(
                    $request->file('image')->getRealPath(),
                    ['folder' => 'fricalearn/courses']
                );
                
                $data['thumbnail_url'] = $upload['secure_url'];
            }

            $course->update($data); 
            return response()->json(['message' => 'Course updated successfully', 'course' => $course]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Update Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 🗑️ Admin: Delete
     */
    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();
        return response()->json(['message' => 'Course deleted successfully']);
    }
}