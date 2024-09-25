<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrganizationalLog;
use App\Models\ApiLog;
use App\Http\Requests\OrgLogRequest;
use Throwable;  
use Exception; 


class OrgLogController extends Controller
{
    
    public function getOrgLog(Request $request){

        try{

            $currentPage = $request->input('page', 1);

            $data = OrganizationalLog::where('status', 'A')->paginate(10, ['*'], 'page', $currentPage);
        
            if ($data->isEmpty() && $currentPage > $data->lastPage()) {
                $response = [
                    'isSuccess' => true,
                    'message' => 'The page you requested does not exist.'
                ];
                $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
                return response()->json($response, 404);
            } 
        
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];
            $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 200);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('storeAccount', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    
    }

    public function storeOrgLog(OrgLogRequest $request){

       try{
          
           $validate = $request->validate([
                'name' => 'required',
                'acronym' => ['required','min:2'],
                'entity_id' => ['required']
           ]);

            if ($this->isExist($validate)) {

                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The organization you are trying to register already exists. Please verify your input and try again.'
                ];

                $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);

                return response()->json($response, 422);

            }else{

                OrganizationalLog::create($request->validated());
                $response = [
                          'isSuccess' => true,
                           'message' => "Successfully created."
                    ];

                $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
                return response()->json($response);
            }

             
         }catch (Exception $e) {
 
             $response = [
                 'isSuccess' => false,
                 'message' => "Unsucessfully created. Please check your inputs.",
                 'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];
 
             $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
             return response()->json($response, 500);
 
         }

    }

    public function updateOrgLog(Request $request){

        try{

            $validate = $request->validate([
                'id' => 'required',
                'entity_id' => 'required',
                'name' => 'required',
                'acronym' => 'required'
            ]);
    
            if ($this->isExist($validate)) {
    
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The organization you are trying to update already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('updateOrgLog', "", $request->all(), [$response]);
    
                return response()->json($response, 422);
    
            }else{
    
                $organization = OrganizationalLog::find($request->id);
                $organization->update($validate);
    
                $response = [
                          'isSuccess' => true,
                           'message' => "Successfully updated."
                    ];
    
                $this->logAPICalls('updateOrgLog', "", $request->all(), [$response]);
                return response()->json($response);
            }

        }catch(Exception $e){
            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully updated. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('updateOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
        
    }

    public function editOrgLog(Request $request){

     try{

        $data = OrganizationalLog::find($request->id);

        $response = [
            'isSuccess' => true,
             'data' => $data
        ];

        $this->logAPICalls('editOrgLog', "", $request->all(), [$response]);
        return response()->json($response);

     }catch(Exception $e){

         $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully edited. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('editOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);
     }
       
    }

    public function deleteOrgLog(Request $request){
        
        try{

            $organization = OrganizationalLog::find($request->id);
            $organization->update(['status' => $request->status]);
            $response = [
                'isSuccess' => true,
                'message' => "Successfully deleted."
            ];

            $this->logAPICalls('deleteOrgLog', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully deleted. Please try again.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('deleteOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
        
    }

    public function isExist($validate){

        return OrganizationalLog::where('name', $validate['name'])
        ->where('acronym', $validate['acronym'])
        ->where('entity_id', $validate['entity_id'])
        ->exists();

       

    }

    public function searchOrgLog(Request $request){

        try{
            
            $query = $request->input('query');
            $org_id = $request->input('organization_id');

            $results = OrganizationalLog::where('organization_id', $org_id)
            ->when($query, function ($q) use ($query) {
                return $q->where(function ($queryBuilder) use ($query) {
                    $queryBuilder->where('name', 'LIKE', "%{$query}%")
                                 ->orWhere('acronym', 'LIKE', "%{$query}%");
                });
            })
            ->get();

            $response = [
                'isSuccess' => true,
                'results' => $results
            ];

            $this->logAPICalls('searchAccount', "",$request->all(), [$response]);
            return response()->json($response);

        }catch(Exception $e){
            $response = [
                'isSuccess' => false,
                'message' => "Search failed. Please try again later.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('searchAccount', "", $request->all(), [$response]);
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
