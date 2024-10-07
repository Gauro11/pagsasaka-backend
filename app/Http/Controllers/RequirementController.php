<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Requirement;
use App\Models\ApiLog;
use App\Models\OrganizationalLog;
use Throwable;

class RequirementController extends Controller
{

    public function getRequirement(Request $request){

       try{
            $requirement = [];
            $validated = $request->validate([
                'event_id' => 'required|exists:events,id',
                'search' => 'nullable' 
            ]);
        
            $query = Requirement::where('event_id', $validated['event_id'])
                            ->where('status', 'A')
                            ->orderBy('created_at', 'desc');

        
            if (!empty($validated['search'])) {
                $query->where('name', 'like', '%' . $validated['search'] . '%'); 
            }
        
            $datas= $query->get();

            foreach($datas as $data){

                $orglog = OrganizationalLog::where('id',$data->org_log_id)->first();
                
                $requirement[] = [
                    "id" => $data->id,
                    "event_id" => $data->event_id,
                    "name" => $data->name,
                    "org_log_id" => $data->org_log_id,
                    "org_log_acronym" => $orglog ?  $orglog->acronym : null,
                    "upload_status" => $data->upload_status,
                    "status" => $data->status,
                    "created_at" =>$data->created_at,
                    "updated_at" => $data->updated_at
                ];
            }
            


            $response = [
                'isSuccess' => true,
                'getRequirement' => $requirement
            ];
        
            $this->logAPICalls('getRequirement', "", $request->all(), [$response]);
            return response()->json($response, 200);
        

       }catch(Throwable $e){
            
        $response = [

                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()

        ];

            $this->logAPICalls('getRequirement', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }


    public function deleteRequirement(Request $request){
        
        try{

            $validated = $request->validate([
                'id' => 'required|exists:requirements,id'
            ]);

            $organization = Requirement::find($request->id);
            $organization->update(['status' => "I"]);

            $response = [
                'isSuccess' => true,
                'message' => "Successfully deleted."
            ];

            $this->logAPICalls('deleteRequirement', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully deleted. Please try again.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('deleteRequirement', "", $request->all(), [$response]);
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
