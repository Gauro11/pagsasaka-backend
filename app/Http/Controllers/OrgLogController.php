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
use Illuminate\Support\Arr;

class OrgLogController extends Controller
{

            //////////////////////////                    
            //      ORG_ID VALUE   
            //       1- College
            //       2- Office
            //       3- Program
            ///////////////////////////

    //under po ito getConcernedOfficeProgram() under po yan ng creation ng events po. 
    public function getConcernedOfficeProgram(){

        try{

            $programs_offices =  OrganizationalLog::where('org_id','!=',1)
                                        ->where('status','A')
                                        ->orderBy('created_at','desc')->get();

            $response = [
                'isSuccess' => true,
                'offices-programs' => $programs_offices
            ];
            
            $this->logAPICalls('getConcernedOfficeProgram', "", [], [$response]);
            return response($response,200);

        }catch(Throwable $e){
            
            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getConcernedOfficeProgram', "", [],[$response]);
            return response($response, 500);
        }
                          
    }

    public function getDropdownOrganization(Request $request){

        try{

            $orgLog=[]; 
            $validated = $request->validate([
                'org_id' => 'required'
            ]);
            
            // Query the OrganizationalLog model to find all records with a specific org_id
            $datas = OrganizationalLog::where('org_id',$validated['org_id'])
                                        ->where('status','A')->get();

            if($datas){

                $response = [];

                foreach($datas as $data){
                    // Add a new associative array to the $orgLog array na masesesave lang po na key and value ay id and name.
                    $orgLog[] = [
    
                        'id' => $data->id,
                        'name' => $data->name
                    ];
                }
    
                if($validated['org_id'] == 1){
    
                    $response = [
                        'isSuccess' => true,
                        'colleges' =>  $orgLog
                    ];
    
                }elseif($validated['org_id'] == 2){
    
                    $response = [
                        'isSuccess' => true,
                        'offices' =>  $orgLog
                    ];
    
                }else{
                    $response = [
                        'isSuccess' => true,
                        'programs' =>  $orgLog
                    ];
                }
                
                
                $this->logAPICalls('getDropdownOrganization', "", $request->all(), [$response]);
                return response($response,200);

            }

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getDropdownOrganization', "", $request->all(), [$response]);
            return response($response, 500);
        }
        
    }

    public function getOrganization(Request $request){

        try{

            $validated = $request->validate([
                'paginate' =>  'required',
                'org_id' => 'required'
            ]);

            $response = [];

            ///////////////////////////////////////////////////////
            //
            // PAGINATE : 0 -> return lahat ng data
            // PAGINATE : 1  -> return lahat ng data with paginate
            //
            ////////////////////////////////////////////////////////

            if ($validated['paginate'] == 0) {
                // Build the query
                $query = OrganizationalLog::where('org_id', $validated['org_id'])
                                          ->where('is_archived',0)
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
                $this->logAPICalls('getOrgnization', "", $request->all(), [$data]);
            
                 // Return the response
                 $org_id = $validated['org_id'];

                 if($org_id==1){
                     $response = [
                         'isSuccess' => true,
                         'colleges' =>  $data
                     ];
                 }elseif($org_id==2){
                     $response = [
                         'isSuccess' => true,
                         'offices' => $data
                     ];
                 }else{
                     $response = [
                         'isSuccess' => true,
                         'programs' => $data
                     ];
                 }
         
                 return response()->json($response);

            }else{
           
               $perPage = 10;
       
               $query = OrganizationalLog::where('org_id', $request->org_id)
                                                 ->where('is_archived', 0);
                                       

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

                // Removing URL in response.
                $data_convert_to_array = $data->toArray();
                unset($data_convert_to_array['path']); 
                unset($data_convert_to_array['links']); 
                unset($data_convert_to_array['first_page_url']);
                unset($data_convert_to_array['last_page_url']);
                unset($data_convert_to_array['next_page_url']);
                unset($data_convert_to_array['prev_page_url']);
                $org_id = $validated['org_id'];

                // Log API call
               $this->logAPICalls('getOrgnization', "", $request->all(), [$data]);

                // Return the response
                if($org_id==1){
                    $response = [
                        'isSuccess' => true,
                        'colleges' =>   $data_convert_to_array
                    ];
                }elseif($org_id==2){
                    $response = [
                        'isSuccess' => true,
                        'offices' =>  $data_convert_to_array
                    ];
                }else{
                    $response = [
                        'isSuccess' => true,
                        'programs' =>  $data_convert_to_array
                    ];
                }
        
                return response()->json($response,200);

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

    public function createOrganization(OrgLogRequest $request){

       try{

          $exists = false;

           $validate = $request->validate([
                'name' => 'required',
                'acronym' => ['required','min:2'],
                'org_id' => ['required'],
                'college_entity_id' => ['nullable']
           ]);
           

           if($validate['org_id'] == "3"){ // executed if organization is a program.

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

                $this->logAPICalls('createOrganization', "", $request->all(), [$response]);
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
                           'message' => "Successfully created!"
                    ];

                $this->logAPICalls('createOrganization', "", $request->all(), [$response]);
                return response()->json($response);
            }
             
         }catch (Throwable $e) {
 
             $response = [

                 'isSuccess' => false,
                 'message' => "Unsucessfully created. Please check your inputs.",
                 'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];
 
             $this->logAPICalls('createOrganization', "", $request->all(), [$response]);
             return response()->json($response, 500);
 
         }
    }

    public function updateOrganization(Request $request){

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
    
                $this->logAPICalls('updateOrganization', "", $request->all(), [$response]);
    
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
    
                $this->logAPICalls('updateOrganization', "", $request->all(), [$response]);
                return response()->json($response);
            }

        }catch(Throwable $e){
            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully updated. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('updateOrganization', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
        
    }

    public function updateOrganizationStatus(Request $request){
        
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
           }

            $response = [
                'isSuccess' => true,
                'message' => $message
            ];

            $this->logAPICalls('updateOrganizationStatus', "", $request->all(), [$response]);
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


    public function deleteOrganization(Request $request){

        try{

            $validated = $request->validate( [
                'id' => 'required|exists:organizational_logs,id'
            ] );

            $organization = OrganizationalLog::find($validated['id']);
            $organization->update([
                'is_archived' => 1 ,
                'status' => 'I'
            ]);

            $program = Program::where('program_entity_id',$request->id)->first();

            if($program){
                $program->update([
                    'is_archived' => 1,
                    'status' => 'I'
                ]);
            }

            $response = [
                'isSuccess' => true,
                'message' => "Deleted Successfully!"
            ];

            $this->logAPICalls('deleteOrganization', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully created. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('deleteOrganization', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
        
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
                        'per_page' => $paginatedPrograms->perPage()
                  
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
                    'per_page' => $datas->perPage()
                
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

            $this->logAPICalls('getFilteredPrograms', "", $request->all(), [$response]);
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
