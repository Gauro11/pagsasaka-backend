<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrganizationalLog;
use App\Models\ApiLog;
use App\Models\Program;
use App\Http\Requests\OrgLogRequest;
use Throwable;
use Illuminate\Validation\ValidationException;

class OrgLogController extends Controller
{
    
    public function getOrgLog(Request $request){

        try{

            $items = 10;

            // Validation
            $validate = $request->validate([
                'org_id' => 'required'
            ]);

            $perPage = $request->query('per_page', $items); 

          
            $search = $request->input('search'); 

            // Query building
            $query = OrganizationalLog::where('status', 'A')
                ->where('org_id', $request->org_id);

            // Search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('acronym', 'LIKE', "%{$search}%");
                });
            }

            // Custom handling for org_id == 3
            if ($request->org_id == 3) { // Ensure org_id is integer
                $data = $query->with(['programs:program_entity_id,college_entity_id'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                // Manipulate the response to get the name of the college.
                $data->getCollection()->transform(function ($item) {
                    foreach ($item->programs as $program) {
                        $college = OrganizationalLog::find($program->college_entity_id);
                        $program->college_name = $college ? $college->name : null;
                    }
                    return $item;
                });
            } else {
                $data = $query->orderBy('created_at', 'desc')->paginate($perPage);
            }

            $this->logAPICalls('getOrgLog', "", $request->all(), [$data]);
            return response()->json([
                'isSucccess' => true,
                'get_OrgLog' => $data
            ]);


        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function storeOrgLog(OrgLogRequest $request){

       try{
          $exists = false;

           $validate = $request->validate([
                'name' => 'required',
                'acronym' => ['required','min:2'],
                'org_id' => ['required', 'exists:organizations,id'],
                'college_entity_id' => ['nullable']
           ]);


           if($validate['org_id'] == "3"){

                
                $data = OrganizationalLog::where('name',$validate['name'])
                                        ->where('acronym',$validate['acronym'] )->get();


                if($data->isNotEmpty()){
                   $program_id =  $data->first()->id;

                   $exists = Program::where('program_entity_id', $program_id)
                                    ->where('college_entity_id', $validate['college_entity_id'])
                                    ->exists();
    
                }else{
                    $exists =false;
                }

              
        
           }else{
                $exists =  OrganizationalLog::where('name',$validate['name'])
                                             ->where('acronym',$validate['acronym'])->exists();
           }

        if ($exists) {

                $response = [
                    'isSuccess' => false,
                    'message' => 'The organization you are trying to register already exists. Please verify your input and try again.'
                ];

                $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
                return response()->json($response, 422);

            }else{

                $data = OrganizationalLog::create([
                    'name' => $validate['name'],
                    'acronym' => $validate['acronym'],
                    'org_id' => $validate['org_id']
                ]);

                ////  CODE FOR STORE PROGRAMS ////

                if($request->org_id == '3'){
                    $this->storePorgram($request->college_entity_id,$validate);
                }
                
               
                $response = [
                          'isSuccess' => true,
                           'message' => "Successfully created!",
                           'store_OrgLog' => $data 

                    ];

                $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
                return response()->json($response);
            }
        }
             
         }catch (Throwable $e) {
 
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
                'id' => 'required|exists:organizational_logs,id',
                'name' => 'required',
                'acronym' => 'required',
                'college_entity_id' => 'nullable'
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

                if($organization->org_id == "3"){

             
                    $program = Program::where('program_entity_id',$organization->id)->first();

                     $organization->update([
                        'name' => $validate['name'],
                        'acronym' => $validate['acronym']
                     ]);
                     $program->update([
                        'college_entity_id' => $validate['college_entity_id']
                     ]);

                }else{

                    $organization->update([
                        'name' => $validate['name'],
                        'acronym' => $validate['acronym']
                     ]);
                }
     
                $response = [
                          'isSuccess' => true,
                           'message' => "Successfully updated."
                    ];
    
                $this->logAPICalls('updateOrgLog', "", $request->all(), [$response]);
                return response()->json($response);
            }

        }catch(Throwable $e){
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
        $request->validate( [
                'id' => 'required|exists:organizational_logs,id'
            ] );

        $data = OrganizationalLog::find($request->id);

        if($data->org_id == '3'){
            $program  = Program::where('program_entity_id',$request->id)->get();
            if ($program->isNotEmpty()){
                $college = OrganizationalLog::where('id',$program->first()->college_entity_id)->get();
                $data = [
                    'id' => $data->id,
                    'name' => $data->name,
                    'acronym' =>  $data->acronym,
                    'college_id' => $program->first()->college_entity_id,
                    'college_name' => $college->first()->name,
                    'org_id' => $data->org_id,
                    'created_at' =>  $data->created_at,
                    'updated_at' =>  $data->updated_at

                ];
            }        
       }

        $response = [
            'isSuccess' => true,
             'edit_OrgLog' => $data
        ];

        $this->logAPICalls('editOrgLog', "", $request->all(), [$response]);
        return response()->json($response);

     }catch(Throwable $e){

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

            $request->validate( [
                'id' => 'required|exists:organizational_logs,id'
            ] );

            $organization = OrganizationalLog::find($request->id);
            $organization->update(['status' =>"I"]);
            
            $program = Program::where('program_entity_id',$request->id)->first();

            if($program){
                $program->update([
                    'status' => "I"
                ]);
            }
          
            $response = [
                'isSuccess' => true,
                'message' => "Successfully created."
            ];

            $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully created. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function isExist($validate){
         
        $data = OrganizationalLog::where('id',$validate['id'])->get();

          
          if($data->first()->org_id != "3"){

                return OrganizationalLog::where('name', $validate['name'])
                ->where('acronym', $validate['acronym'])
                ->exists();

          }else{
            if (OrganizationalLog::where('name', $validate['name'])
            ->where('acronym', $validate['acronym'])
            ->exists() && Program::where('program_entity_id',$validate['id'])
                     ->where('college_entity_id',$validate['college_entity_id'])
                     ->exists() ){
                         return true;}
          }     

        Program::create([
            'program_entity_id' => $program->id,
            'college_entity_id' => $college_id
        ]);
    }
    
    
    public function storePorgram($college_id,$validate){

            $program = OrganizationalLog::where('name', $validate['name'])
                        ->where('acronym', $validate['acronym'])
                        ->where('org_id', $validate['org_id'])
                        ->first();
            
            Program::create([
                'program_entity_id' => $program->id ,
                'college_entity_id' => $college_id
            ]);

    }


    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp)
            ]);
        } 
        catch (Throwable $ex) {
            return false;
        }
        return true;
    }
    }
