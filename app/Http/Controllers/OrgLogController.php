<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrganizationalLog;
use App\Models\ApiLog;
use App\Models\Program;
use App\Http\Requests\OrgLogRequest;


class OrgLogController extends Controller
{
    
    public function getOrgLog(Request $request){

        try{

            $data = OrganizationalLog::where('status', 'A')
            ->where('entity_id', $request->entity_id)
            ->orderBy('created_at', 'desc') // Palitan ang 'created_at' ng tamang column kung kinakailangan
            ->take(10)
            ->get();

            $response = [
                'isSuccess' => true,
                'data' => $data
            ];

            $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 200);

            // $currentPage = $request->input('page', 1);

            // $data = OrganizationalLog::where('status', 'A')->paginate(10, ['*'], 'page', $currentPage);
        
            // if ($data->isEmpty() && $currentPage > $data->lastPage()) {
            //     $response = [
            //         'isSuccess' => true,
            //         'message' => 'The page you requested does not exist.'
            //     ];
            //     $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            //     return response()->json($response, 404);
            // } 
        
            // $response = [
            //     'isSuccess' => true,
            //     'data' => $data
            // ];
            // $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            // return response()->json($response, 200);

        }catch(Throwable $ex){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('storeAccount', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    
    }

    public function paginateOrgLog(Request $request){

        //  $currentPage = $request->input('page', 2);

        //     $data = OrganizationalLog::where('status', 'A')
        //     ->where('entity_id', $request->entity_id)
        //     ->orderBy('created_at', 'desc')
        //     ->paginate(2, ['*'], 'page', $currentPage);
        
        //     if ($data->isEmpty() && $currentPage > $data->lastPage()) {
        //         $response = [
        //             'isSuccess' => true,
        //             'message' => 'The page you requested does not exist.'
        //         ];
        //         $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
        //         return response()->json($response, 404);
        //     } 
        
        //     $response = [
        //         'isSuccess' => true,
        //         'data' => $data
        //     ];
        //     $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
        //     return response()->json($response, 200);

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

                ////  CODE FOR STORE PROGRAMS ////

                if($request->entity_id == '3'){
                    $this->storePorgram($request->college_entity_id,$validate);
                }
                
               
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
            

            if ($this->isExist($validate,$request->entity_id,$request->id,$request->college_entity_id)) {
    
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The organization you are trying to update already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('updateOrgLog', "", $request->all(), [$response]);
    
                return response()->json($response, 422);
    
            }else{
    
                $organization = OrganizationalLog::find($request->id);

                if($organization->entity_id == '3'){
                   
                    $validated = $request->validate([
                        'college_entity_id' => 'required'
                    ]);
                   
                    $program = Program::where('program_entity_id',$organization->id);
                  
                    $program->update($validated);
                }else{

                    $organization->update($validate);
                }
     
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
        $college  = "";
        $data = OrganizationalLog::find($request->id);

        if($data->entity_id == '3'){
            $program  = Program::where('program_entity_id',$request->id)->get();
            if ($program->isNotEmpty()){
                $college = OrganizationalLog::where('id',$program->first()->college_entity_id)->get();
            }        
       }

        $response = [
            'isSuccess' => true,
             'data' => $data,
             'college_id' => $college
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

    public function isExist($validate,$entity_id=null,$program_id=null,$college_id=null){

        if($entity_id == '3'){

            return Program::where('program_entity_id',$program_id)
                             ->where('college_entity_id', $college_id)
                             ->exists();

        }else{
            return OrganizationalLog::where('name', $validate['name'])
            ->where('acronym', $validate['acronym'])
            ->where('entity_id', $validate['entity_id'])
            ->exists();
        }
      
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

    public function storePorgram($college_id,$validate){

            $program = OrganizationalLog::where('name', $validate['name'])
                        ->where('acronym', $validate['acronym'])
                        ->where('entity_id', $validate['entity_id'])
                        ->first();
            
            Program::create([
                'program_entity_id' => $program->id ,
                'college_entity_id' => $college_id
            ]);

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
