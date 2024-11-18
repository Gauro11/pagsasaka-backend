<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Models\ApiLog;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;


class AcademicYearController extends Controller
{

    public function addAcademicYear(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'academic_year' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
            ]);
    
            if ($validator->fails()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ];
                $this->logAPICalls('addAcademicYear', "", $request->all(), $response);
                return response()->json($response, 422);
            }
    
            $startDate = Carbon::parse($request->start_date)->format('Y-m-d');
            $endDate = Carbon::parse($request->end_date)->format('Y-m-d');
    
            // Check if an academic year with the same start and end dates already exists
            $existingAcademicYear = AcademicYear::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->first();
    
            if ($existingAcademicYear) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'An academic year with these dates already exists.',
                ];
                $this->logAPICalls('addAcademicYear', "", $request->all(), $response);
                return response()->json($response, 409);
            }
    
            // Set `status` to 'A' and `Isarchive` to 0
            $academicYear = AcademicYear::create([
                'academic_year' => $request->academic_year,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'A', // Status set to 'A' (active)
                'is_archived' => 0, // Isarchive set to 0 (not archived)
            ]);
    
            // Update statuses of past academic years to 'I' (inactive) if needed
            $currentDate = Carbon::now()->format('Y-m-d');
            AcademicYear::whereDate('end_date', '<', $currentDate)->update(['status' => 'I']);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Academic Year successfully added.',
                'academic_year' => $academicYear,
            ];
    
            $this->logAPICalls('addAcademicYear', "", $request->all(), $response);
            return response()->json($response, 201);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to add Academic Year.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('addAcademicYear', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    

    public function deactivateAcademicYear(Request $request, $id)
    {
        try {
            // Find the academic year by ID
            $academicYear = AcademicYear::find($id);
    
            // Check if the academic year exists
            if (!$academicYear) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Academic Year not found.'
                ];
                $this->logAPICalls('deleteAcademicYear', $id, $request->all(), $response);
                return response()->json($response, 404);
            }
    
            // Check if the academic year is active
            if ($academicYear->status === 'A') {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Cannot delete an active academic year.'
                ];
                $this->logAPICalls('deleteAcademicYear', $id, $request->all(), $response);
                return response()->json($response, 400);
            }
    
            // Update the academic year to mark as archived and inactive instead of deleting
            $academicYear->update([
                'is_archived' => 1, // Archive the record
                'status' => 'I'   // Set status to inactive
            ]);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Academic Year successfully marked as archived and inactive.'
            ];
            $this->logAPICalls('deleteAcademicYear', $id, $request->all(), $response);
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update Academic Year status.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteAcademicYear', $id, $request->all(), $response);
            return response()->json($response, 500);
        }
    }
    

    public function getAcademicYear(Request $request)
    {
        try {
            $validated = $request->validate([
                'paginate' => 'required',
            ]);

            // Initialize the base query
            $query = AcademicYear::select('id', 'Academic_year', 'start_date', 'end_date', 'status')
                ->whereIn('status', ['A', 'I'])
                ->orderBy('start_date', 'desc');

            // Apply search term if present
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                try {
                    // Parse search term as date if possible
                    $searchDate = Carbon::parse($searchTerm, null)->format('Y-m-d');
                } catch (\Exception $e) {
                    $searchDate = null;
                }

                $query->where(function ($query) use ($searchTerm, $searchDate) {
                    $query->where('Academic_year', 'like', "%{$searchTerm}%")
                        ->orWhereDate('start_date', $searchDate)
                        ->orWhereDate('end_date', $searchDate);
                });
            }

            // Paginated data retrieval
            $perPage = $request->input('per_page', 10);
            $data = $query->paginate($perPage);

            // Format the dates for the paginated response
            $data->getCollection()->transform(function ($item) {
                $item->start_date = Carbon::parse($item->start_date)->format('F j, Y');
                $item->end_date = Carbon::parse($item->end_date)->format('F j, Y');
                return $item;
            });

            // Prepare the response with only required pagination metadata
            $response = [
                'isSuccess' => true,
                'message' => 'Active Academic year retrieved successfully.',
                'academic_years' => [
                    'data' => $data->items(),
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                ]
            ];

            $this->logAPICalls('getAcademicYear', "", $request->all(), $response);
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve Academic Year records.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getAcademicYear', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }


    public function logAPICalls(string $methodName, ?string $userId,  array $param, array $resp)
    {
        try {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp)
            ]);
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }
}
