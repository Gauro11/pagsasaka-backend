<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrganizationalLog;
use App\Models\ApiLog;
use App\Models\Program;
use App\Http\Requests\OrgLogRequest;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

class OrgLogController extends Controller
{

    public function getOrgLogInfo(Request $request){

        try{

            $validated = $request->validate([

                'org_log_id' => 'required|exists:organizational_logs,id'

            ]);

            $data = OrganizationalLog::where('id', $validated['org_log_id'])->first();

            $response = [
                'isSuccess' => true,
                'data' => $data
            ];

            $this->logAPICalls('getOrgLogInfo',"", $request->all(), [$response]);
            return response($response,200);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getOrgLogInfo',"", $request->all(), [$response]);
            return response($response, 500);

        }
    }
    
    public function getConcernedOffice(){

        try{

            $data =  OrganizationalLog::where('org_id','!=',1)
                                        ->where('status','A')
                                        ->orderBy('created_at','desc')->get();

            $response = [
                'isSuccess' => true,
                'concerned_office' => $data
            ];
            
            $this->logAPICalls('getConcernedOffice', "", [], [$response]);
            return response($response,200);

        }catch(Throwable $e){
            
            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getConcernedOffice', "", [] [$response]);
            return response($response, 500);
        }
        
                       
    }

    public function getDropdownOrg(Request $request){

        try{
            $orgLog=[];
            $validated = $request->validate([
                'org_id' => 'required'
            ]);

            $datas = OrganizationalLog::where('org_id',$validated['org_id'])->get();

            foreach($datas as $data){
                
                $orgLog[] = [

                    'id' => $data->id,
                    'name' => $data->name
                ];
            }

            $response = [
                'isSuccess' => true,
                'OrgLog' =>  $orgLog
            ];
            
            $this->logAPICalls('getDropdownOrg', "", $request->all(), [$response]);
            return response($response,200);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getDropdownOrg', "", $request->all(), [$response]);
            return response($response, 500);
        }
        
    }

    public function getOrgLog(Request $request){

        try{

            $validated = $request->validate([
                'paginate' =>  'required',
                'org_id' => 'required'
            ]);

            if ($validated['paginate'] == 0) {
                // Build the query
                $query = OrganizationalLog::where('org_id', $validated['org_id'])
                                          ->where('status', '!=', 'D')
                                          ->orderBy('created_at', 'desc');
            
                // Eager load the programs relationship if org_id is 3
                if ($request->org_id == 3) {
                    $query->with(['programs:program_entity_id,college_entity_id']);
                }
            
                // Get the results
                $data = $query->get();
            
                // If org_id is 3, transform the data to include college names
                if ($request->org_id == 3) {
                    $data->transform(function ($item) {
                        foreach ($item->programs as $program) {
                            $college = OrganizationalLog::find($program->college_entity_id);
                            $program->college_name = $college ? $college->name : null;
                        }
                        return $item;
                    });
                }
            
                // Log the API call
                $this->logAPICalls('getOrgLog', "", $request->all(), [$data]);
            
                // Return the response
                return response()->json([
                    'isSuccess' => true,
                    'get_OrgLog' => $data
                ]);

            }else{
                $perPage = 10;
                $query = OrganizationalLog::where('org_id', $request->org_id)
                                        ->where('status', '!=', 'D');

                // If there is a search term in the request, filter based on it
                if ($request->has('search') && $request->search) {
                    // Assuming the search term can be used to filter by any relevant column (e.g., name or description)
                    $searchTerm = $request->search;
                    $query = $query->where(function ($query) use ($searchTerm) {
                        $query->where('name', 'like', "%$searchTerm%")
                            ->orWhere('acronym', 'like', "%$searchTerm%");
                          // Replace column_name_X with actual column names
                    });
                }

                // Custom handling for org_id == 3
                if ($request->org_id == 3) { // Ensure org_id is an integer
                    $data = $query->with(['programs:program_entity_id,college_entity_id'])
                                ->orderBy('created_at', 'desc')
                                ->paginate($perPage);

                    // Manipulate the response to get the name of the college
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

                // Log API call
                $this->logAPICalls('getOrgLog', "", $request->all(), [$data]);

                // Return the response
                return response()->json([
                    'isSucccess' => true,
                    'get_OrgLog' => $data
                ]);

            }

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
                'id' => 'required|exists:organizational_logs,id',
                'status' => 'required'
            ] );

            $status = strtoupper($request->status);

            $organization = OrganizationalLog::find($request->id);
            $organization->update(['status' =>  $status ]);
            
            $program = Program::where('program_entity_id',$request->id)->first();

            if($program){
                $program->update([
                    'status' =>  $status 
                ]);
            }
            
           if($status == 'A'){
                $message = "Activated successfully.";
           }elseif($status == 'I'){
                $message = "Inactivated successfully.";
           }else{
             $message = "Successfully deleted.";
           }

            $response = [
                'isSuccess' => true,
                'message' => $message
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
                         return true;
                        
                        
                        }
         
          }     
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

    public function getFilteredPrograms(Request $request){
        try{

           

            $validated = $request->validate([
                'college_id' => 'required|exists:organizational_logs,id'
            ]);
            
            $programs = [];
            $response = [];
            
            // Check if a search term is provided
            if (!empty($request->search)) {
                // Fetch Organizational Logs with search term (name or acronym)
                $datas = OrganizationalLog::where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('acronym', 'like', '%' . $request->search . '%')
                    ->orderBy('created_at','desc')
                    ->get();
            
                // Loop through the results and filter programs
                foreach ($datas as $data) {
                    // Check if a program exists for this organizational log and college ID
                    $progExists = Program::where('program_entity_id', $data->id)
                        ->where('college_entity_id', $validated['college_id'])
                        ->first();
            
                    if ($progExists) {
                        // Fetch the related college for the program
                        $college = OrganizationalLog::find($progExists->college_entity_id);
            
                        // Add the program data to the array
                        $programs[] = [
                            'id' => $data->id,
                            'name' => $data->name,
                            'acronym' => $data->acronym,
                            'status' => $data->status,
                            'programs' => [
                                [
                                    'program_entity_id' => $data->id, 
                                    'college_entity_id' => $validated['college_id'],
                                    'college_name' => $college->name,
                                ],
                            ]
                        ];
                    }
                }
            
                // Manually paginate the programs array
                $currentPage = $request->get('page', 1); // Get current page from the query string (default is page 1)
                $perPage = 10; // Set the number of items per page
                $currentPagePrograms = array_slice($programs, ($currentPage - 1) * $perPage, $perPage);
            
                // Create the LengthAwarePaginator
                $paginatedPrograms = new LengthAwarePaginator(
                    $currentPagePrograms, 
                    count($programs), 
                    $perPage, 
                    $currentPage, 
                    ['path' => url()->current(), 'query' => request()->query()]
                );
            
                // Return the response with paginated programs and pagination metadata
                $response = [
                
                        'data' => $paginatedPrograms->items(),
                        'total' => $paginatedPrograms->total(),
                        'current_page' => $paginatedPrograms->currentPage(),
                        'last_page' => $paginatedPrograms->lastPage(),
                        'per_page' => $paginatedPrograms->perPage(),
                        'next_page_url' => $paginatedPrograms->nextPageUrl(),
                        'prev_page_url' => $paginatedPrograms->previousPageUrl(),
                  
                ];
            
        
            
        }else{


            $validated = $request->validate([
                'college_id' => 'required|exists:organizational_logs,id'
            ]);
            
            $programs = [];  // Initialize the array to store the programs
            
            // Paginate the Programs based on college_id and status
            $datas = Program::where('college_entity_id', $validated['college_id'])
                ->where('status', 'A')
                ->orderBy('created_at', 'desc')
                ->paginate(10);  // Paginate to 10 per page
            
            // Loop through the paginated results and collect the necessary data
            foreach ($datas as $data) {
                // Retrieve the organization details for the program
                $organization = OrganizationalLog::find($data->program_entity_id);
                $college = OrganizationalLog::find($validated['college_id']);
            
                // Check if both organization and college exist
                if ($organization && $college) {
                    // Populate the programs array with necessary data
                    $programs[] = [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'acronym' => $organization->acronym,
                        'status' => $organization->status,
                        'programs' => [
                            [
                                'program_entity_id' => $organization->id,
                                'college_entity_id' => $validated['college_id'],
                                'college_name' => $college->name,
                            ],
                        ]
                    ];
                }
            }
            
            // Prepare the response with pagination metadata
            $response = [
                    'data' => $programs,  // Programs that match the query and search term
                    'total' => $datas->total(),
                    'current_page' => $datas->currentPage(),
                    'last_page' => $datas->lastPage(),
                    'per_page' => $datas->perPage(),
                    'next_page_url' => $datas->nextPageUrl(),
                    'prev_page_url' => $datas->previousPageUrl(),
                
            ];
            
        }
        
        return response([
            'isSucess' => true,
            'programs' => $response
        ],200);


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
