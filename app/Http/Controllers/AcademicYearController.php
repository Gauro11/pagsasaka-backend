<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Models\ApiLog;
use Carbon\Carbon;


class AcademicYearController extends Controller
{

    public function addAcademicYear(Request $request)
    {
        try {
            // Validate request data
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

            // Get the current time
            $currentTime = now()->format('H:i:s');

            // Combine input dates with the current time
            $startDate = Carbon::parse($request->start_date . ' ' . $currentTime);
            $endDate = Carbon::parse($request->end_date . ' ' . $currentTime);

            // Get the current date
            $currentDate = Carbon::now();

            // Determine status for the new academic year
            $status = ($startDate <= $currentDate && $currentDate <= $endDate) ? 'A' : 'I';

            // Create the AcademicYear entry
            $academicYear = AcademicYear::create([
                'academic_year' => $request->academic_year,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            // Update existing academic years to be inactive if their end date is in the past
            AcademicYear::where('end_date', '<', now()) // Only affect past years
                ->update(['status' => 'I']);

            // Set the status of future academic years to active if their start date is in the future
            AcademicYear::where('start_date', '>', now())
                ->update(['status' => 'A']);

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
