<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrganizationalLog;
use App\Models\Program;
use App\Models\ApiLog;

class ProgramController extends Controller
{
    public function getProgram(Request $request){
        try{

            $validated = $request->validate([
                'id' => 'required|exists:organizational_logs,id',
                'search' => 'nullable|string' // Optional: add validation for search parameter
            ]);
            
            $data = OrganizationalLog::find($validated['id']);
            
       
            if ($data->org_id == "1") {
             
                $search = $request->input('search');
            
       
                $query = Program::where('college_entity_id', $validated['id']);
         
                if (!empty($search)) {
                    $query->whereHas('organizationalLog', function($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('acronym', 'LIKE', "%{$search}%");
                    });
                }
            
                // Execute the query and get the results
                $programs = $query->with('organizationalLog:id,name,acronym')->get();
            
                // Transform the results to include the needed details
                $data = $programs->map(function($program) {
                    return [
                        'program_entity_id' => $program->program_entity_id,
                        'name' => $program->organizationalLog->name,
                        'acronym' => $program->organizationalLog->acronym,
                    ];
                });

                $response = [
                    'isSuccess' => true,
                    'programs' => $data
                ];

            }else{

                $response = [
                    'isSuccess' => false,
                    'message' => "Invalid College ID"
                ];
                $this->logAPICalls('getProgram', "", $request->all(), [$response]);
                return response()->json($response,500);
            }
            
           
            
            $this->logAPICalls('getProgram', "", $request->all(), [$response]);
            return response()->json($response);
            

        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('getProgram', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }

    }

    public function logAPICalls(string $methodName, string $userId, array $param, array $resp){
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
