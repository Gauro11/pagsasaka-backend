<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Event;
use App\Models\Account;
use App\Models\Requirement;
use App\Models\RequirementFile;
use App\Models\ApiLog;
use App\Models\OrganizationalLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class RequirementController extends Controller
{

    public function getRequirement(Request $request){

       try{
            $requirement = [];

            $validated = $request->validate([
                'event_id' => 'required|exists:events,id',
                'account_id' => 'required|exists:accounts,id',
                'search' => 'nullable' 
            ]);
            
            // Get the organization id of the event
            $event = Event::find($validated['event_id']);
            $event_organization = $event->org_log_id; 
            
            // Get the organization id of the logged-in user
            $account = Account::find($validated['account_id']);
            $account_organization = $account->org_log_id;
            
            $query = Requirement::where('event_id', $validated['event_id'])
                                ->where('is_archived', 0);
                                
            
            if (!empty($validated['search'])) {
                $query->where('name', 'like', '%' . $validated['search'] . '%'); 
            }
            
            $datas = $query->get();
            
            // Filter data based on the user's organization and event organization
            foreach ($datas as $data) {
                $orglog = OrganizationalLog::where('id', $data->org_log_id)->first();
            
                if ($event_organization == $account_organization) {
                    $requirement[] = [
                        "id" => $data->id,
                        "event_id" => $data->event_id,
                        "name" => $data->name,
                        "org_log_id" => $data->org_log_id,
                        "org_log_acronym" => $orglog ? $orglog->acronym : null,
                        "upload_status" => $data->upload_status,
                        "status" => $data->status,
                        "created_at" => $data->created_at,
                        "updated_at" => $data->updated_at
                    ];
                } else {
                    if ($account_organization == $data->org_log_id) {
                        $requirement[] = [
                            "id" => $data->id,
                            "event_id" => $data->event_id,
                            "name" => $data->name,
                            "org_log_id" => $data->org_log_id,
                            "org_log_acronym" => $orglog ? $orglog->acronym : null,
                            "upload_status" => $data->upload_status,
                            "status" => $data->status,
                            "created_at" => $data->created_at,
                            "updated_at" => $data->updated_at
                        ];
                    }
                }
            }
            
            // Paginate the $requirement array
            $currentPage = LengthAwarePaginator::resolveCurrentPage();  // Get the current page
            $perPage = 10;  // Number of items per page (adjust as necessary)
            $currentItems = array_slice($requirement, ($currentPage - 1) * $perPage, $perPage); // Slice the array based on the current page
            
            $paginator = new LengthAwarePaginator(
                $currentItems, // Current page items
                count($requirement), // Total number of items
                $perPage, // Items per page
                $currentPage, // Current page
                ['path' => LengthAwarePaginator::resolveCurrentPath()] // Path to maintain correct pagination links
            );
            
            // Response
            $response = [
                'isSuccess' => true,
                'getRequirement' => [
                    'data' => $paginator->items(),  // Use paginator's items to get the current page items
                    'current_page' => $paginator->currentPage(),  // Get the current page number
                    'last_page' => $paginator->lastPage(),  // Get the last page number
                    'per_page' => $paginator->perPage(),  // Number of items per page
                    'total' => $paginator->total(),  // Total number of items
                    'from' => $paginator->firstItem(),  // First item on the current page
                    'to' => $paginator->lastItem()  // Last item on the current page
                ]
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

    public function updateRequirement(Request $request){

        try{

            $validated = $request->validate([
                'id' => 'required|exists:requirements,id',
                'name' => 'required',
                'org_log_id' => 'required'
            ]);
        
           $requirement = Requirement::find($validated['id']);
           $requirement_event_id = $requirement->event_id;

           $isRequirementExists =  Requirement::where('name',$validated['name'])
                                                ->where('event_id', $requirement_event_id)
                                                ->where('org_log_id',$validated['org_log_id'])
                                                ->exists();

            if($isRequirementExists) {
    
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The requirement you are trying to update already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('updateRequirement', "", $request->all(), [$response]);
                return response()->json($response, 500);
    
            }

            $requirement->update([
                'name' => $validated['name'],
                'org_log_id' => $validated['org_log_id']
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "Successfully updated."
            ];

            $this->logAPICalls('updateRequirement', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully deleted. Please try again.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('updateRequirement', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    }

    public function deleteRequirement(Request $request){
        
        try{

            $validated = $request->validate([
                'id' => 'required|exists:requirements,id'
            ]);

            $organization = Requirement::find($request->id);
            $organization->update(['is_archived' => 1]);

            $files = RequirementFile::where('requirement_id',$validated['id'])->get();

            if($files){
                foreach($files as $file){
                    $file->update(['is_archived' => 1]);
                }
            }

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
