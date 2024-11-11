<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\HistoryDocument;
use Carbon\Carbon;
use Throwable;

class HistoryDocumentController extends Controller
{


    public function getHistory(Request $request){

        try{

            // Validate the file_id
            $validated = $request->validate([
                'file_id' => 'required|exists:requirement_files,id',  // Ensures file_id exists in accounts table
                'paginate' => 'required'
            ]);

            // Find the document based on file_id
            $data = HistoryDocument::where('file_id',$validated['file_id'])
                                    ->orderBy('created_at','desc');

            // Check if the document exists
            if (!$data) {
                return response()->json(['error' => 'Document not found.'], 404);  // If no document found, return error response
            }

            // Get the current date
            $currentDate = Carbon::now()->toDateString();

            // Check if there's a filter_date in the request
            if (empty($request->filter_date)) {
                // No filter date, just get the data for the current date
                $documents = $data->whereDate('created_at', $currentDate)->get();
            } else {
                // Filter date is provided
                $lastDays = null;
                $documents = null;

                if ($request->filter_date == "7d") {
                    // Get documents from the last 7 days
                    $lastDays = Carbon::now()->subDays(7);
                    $documents = $data->whereBetween('created_at', [$lastDays, $currentDate])->get();

                } elseif ($request->filter_date == "30d") {
                    // Get documents from the last 30 days
                    $lastDays = Carbon::now()->subDays(30);
                    $documents = $data->whereBetween('created_at', [$lastDays, $currentDate])->get();

                } else {
                    // Validate custom date format (Y-m-d)
                    $date = ['date' => $request->filter_date];
                    $validator = Validator::make($date, [
                        'date' => 'required|date_format:Y-m-d',  // Validate format as 'Y-m-d'
                    ]);

                    if ($validator->fails()) {
                        // Return error if the date format is invalid
                        return response()->json(['error' => 'Invalid date format. Expected: YYYY-MM-DD'], 422);
                    }

                    // Custom date filter
                    $currentDate = $request->filter_date;
                    $documents = $data->whereDate('created_at', $currentDate)->get();
                }
            }

            if($request->paginate == 1){
                $documents= $data->paginate(10);
            }

                $response = [
                    'isSuccess' => true,
                    'history' => $documents
                ];
                $this->logAPICalls('getHistory',"", $request->all(), [$response]);
                return response()->json($response,200);

        }catch(Throwable $e){
            
            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getHistory', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }

    }

    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try
        {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' =>  json_encode($param),
                'api_response' =>  json_encode($resp)
            ]);
        }
        catch(Throwable $ex){
            return false;
        }
        return true;
    }

}
