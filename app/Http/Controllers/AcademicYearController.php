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
                    'errors' => $validator->errors()
                ];
                $this->logAPICalls('addAcademicYear', "", $request->all(), $response);
                return response()->json($response, 422);
            }

            // Get the current time and date
            $currentTime = now()->format('H:i:s');
            $currentDate = Carbon::now();

            // Combine input dates with the current time
            $startDate = Carbon::parse($request->start_date . ' ' . $currentTime);
            $endDate = Carbon::parse($request->end_date . ' ' . $currentTime);

            // Determine the status of the new academic year
            $status = ($startDate->gt($currentDate) || $startDate->eq($currentDate)) ? 'A' : 'I';

            $academicYear = AcademicYear::create([
                'academic_year' => $request->academic_year,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            // Update all past academic years (where end_date is less than the current date) to 'I'
            AcademicYear::where('end_date', '<', $currentDate)->update(['status' => 'I']);

            // Ensure that only current or future academic years (where start_date >= current date) are 'A'
            AcademicYear::where('start_date', '>=', $currentDate)->update(['status' => 'A']);

            $response = [
                'isSuccess' => true,
                'message' => 'Academic Year successfully added.',
                'academicYear' => $academicYear
            ];

            $this->logAPICalls('addAcademicYear', "", $request->all(), $response);
            return response()->json($response, 201);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to add Academic Year.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('addAcademicYear', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    public function deleteAcademicYear(Request $request, $id)
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
                return response()->json($response, 500);
            }

            // Check if the academic year is active
            if ($academicYear->status === 'A') {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Cannot delete an active academic year.'
                ];
                $this->logAPICalls('deleteAcademicYear', $id, $request->all(), $response);
                return response()->json($response, 500);
            }

            // Delete the inactive academic year
            $academicYear->delete();

            $response = [
                'isSuccess' => true,
                'message' => 'Academic Year successfully deleted.'
            ];
            $this->logAPICalls('deleteAcademicYear', $id, $request->all(), $response);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to delete Academic Year.',
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
                $query->where(function ($query) use ($searchTerm) {
                    $query->whereRaw("CONCAT_WS(' ', Academic_year,start_date, end_date) LIKE ?", ["%{$searchTerm}%"])
                         ;
                });
            }
    
            // Non-paginated data retrieval
            if (!$validated['paginate']) {
                $data = $query->get();
    
                // Log API call and return response
                $this->logAPICalls('getAcademicYear', "", $request->all(), $data);
    
                return response()->json([
                    'isSuccess' => true,
                    'Accounts' => $data,
                ], 200);
    
            } else {
                // Paginated data retrieval
                $perPage = $request->input('per_page', 10);
                $data = $query->paginate($perPage);
    
                // Log API call and return response with pagination data
                $response = [
                    'isSuccess' => true,
                    'AcademicYear' => $data,
                    'pagination' => [
                        'current_page' => $data->currentPage(),
                        'per_page' => $data->perPage(),
                        'total' => $data->total(),
                        'last_page' => $data->lastPage(),
                    ],
                ];
                $this->logAPICalls('getAccounts', "", $request->all(), $response);
    
                return response()->json($response, 200);
            }
    
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid input. Please ensure all required fields are provided correctly.',
                'errors' => $e->errors(),
            ], 422);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve AcademicYear. Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
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
