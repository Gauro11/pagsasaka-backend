<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Requirement;
use App\Models\OrganizationalLog;
use App\Models\Program;
use App\Models\Account;
use App\Models\UserRequest;
use App\Models\RequirementFile;
use App\Models\Event;
use App\Models\Apilog;
use Throwable;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class DashboardController extends Controller
{
    
    ///////////////////////////////////////////////////////
    //               ADMIN DASHBOARD                    //
    /////////////////////////////////////////////////////
    public function getAdminDashboard(Request $request){
 
        $currentPage_college = $request->input('page_college', 1);
        $currentPage_office = $request->input('page_office', 1);

        $response = [
            'isSuccess' => true,
            'colleges' => $this->percentColleges($currentPage_college,true), 
            'offices' => $this->percentOffice($currentPage_office,true)
        ];

        return response()->json($response,200);
    }

    public function getDeanDashboard(Request $request){

        $programs =  [];
        $validated = $request->validate([
            'college_id' => ['required', 'exists:organizational_logs,id']
        ]);
        
        // Fetch college data using the 'percentColleges' method, passing '1' and 'false' as arguments.
        // 'percentColleges' might return a collection of colleges and their percentage data.
        $datas = collect($this->percentColleges(1,false)); 
        
        // Find the college data matching the 'college_id' from the validated input
        $college = $datas->firstWhere('id', $validated['college_id']);
        
        // Fetch all the programs associated with the validated college ID
        $datas = Program::where('college_entity_id',$validated['college_id'] )->get();

        foreach( $datas as $data ){
                    $program = OrganizationalLog::where('id',$data->program_entity_id)->first();
                    if($program){
                        $programs[] = [
                            'id' => $data->program_entity_id,
                            'name' => $program->name,
                            'acronym' => $program->acronym,
                            'percentage' => $program->percentage
                        ];
                    }
        }

        $perPage = 10;
        $page = $request->input('page', 1);
        $totalItems = count($programs);


        // Slice the program data to only include the items for the current page
        // This ensures that only a subset of the programs are shown per page
        $slicedData = array_slice($programs, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator(
            $slicedData,       // The actual data to paginate (current page's items)
            $totalItems,       // Total number of records
            $perPage,          // Items per page
            $page,             // Current page
            [
                'path' => Paginator::resolveCurrentPath(), // Resolve current path
                'pageName' => 'page'                       // Customize the page query parameter name
            ]
        );

        $response = [
            'isSuccess' => true,
            'college' => $college ,
            'programs' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 
            'total' => $totalItems, 
            'from' => $paginator->firstItem(), 
            'to' => $paginator->lastItem()
        ];

        return response()->json($response,200);

    }

    public function getProgramDashboard(Request $request){

        $validated = $request->validate([
            'program_id' => ['required', 'exists:organizational_logs,id']
        ]);
        
        // Fetch data related to programs by calling the 'percentPrograms' method and converting the result to a collection
        $datas = collect($this->percentPrograms()); 
        
        // Find the specific program data from the collection using the 'program_id' provided in the request
        // The firstWhere method returns the first matching record based on the 'id'
        $program = $datas->firstWhere('id', $validated['program_id']);

        $response = [
            'isSuccess' =>true,
            'office' => $program
        ];

        return response()->json($response,200);
    }

    public function getHeadDashboard(Request $request){

        $validated = $request->validate([
            'office_id' => ['required', 'exists:organizational_logs,id']
        ]);
        
        // Fetch office data by calling the 'percentOffice' method and convert the result into a collection
        $datas = collect($this->percentOffice(1,false)); 
        
        // Find the specific office data from the collection using the 'office_id' provided in the validated request
        // The 'firstWhere' method finds the first matching item in the collection based on the 'id' field
        $office = $datas->firstWhere('id', $validated['office_id']);

        $response = [
            'isSuccess' =>true,
            'office' => $office
        ];
        return response()->json($response,200);

    }


    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //                            DOCUMENT REQUEST DASHBOARD                                                               //
    //                                                                                                                    //
    //    Dito po kinuha ko yung account id ng nag login po para makuha yung role nila.                                  //
    //    IF Admin or Staff - Ma view niya lahat ng request ng mga Offices/Programs/Colleges.                           //
    //    IF Dean - Lalabas yung mga recent created na request na ginawa nila pati yung mga program na under nila.     //
    //    IF Program chair /  Head - Lalabas yung mga recent created na request na ginawa nila.                //
    //                                                                                                               //
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function getDocumentRequestDashboard(Request $request){

        try{

            $validated = $request->validate([
                'account_id' => ['required','exists:accounts,id']
            ]);

            $account = Account::where('id',$validated['account_id'])->get();
            $role = $account->first()->role;

            if($role == "Admin" || $role == "1" || $role == "Staff" || $role == "2"){
                $requirement= [];

            $datas = UserRequest::where('is_archived', 0)
                        ->orderBy('created_at', 'desc')
                        ->get();

                        foreach ($datas as $data) {
                            $org_log_data = OrganizationalLog::where('id', $data->org_log_id)->first();
                        
                            $requirement[] = [
                                'id' => $data->id,
                                'request_no' => $data->request_no,
                                'org_log_id' => $data->org_log_id,
                                'org_log_name' => $org_log_data ? $org_log_data->name : null, // Check if org_log_data exists
                                'requested_date' => $data->requested_date
                            ];
                        }
                

            }elseif($role == "Dean" || $role == "3"){

                        $data = UserRequest::where('college_entity_id',$account->first()->org_log_id)
                                            ->orWhere('org_log_id',$account->first()->org_log_id)
                                            ->orderBy('created_at', 'desc')
                                            ->get();

                        
                            if($data->isNotEmpty()){
                                  $org_log_data  = OrganizationalLog::where('id',$data->first()->org_log_id)->first();
                                  $requirement  = [
    
                                    'id' =>   $data->first()->id ,
                                    'request_no' => $data->first()->request_no,
                                    'org_log_id' => $data->first()->org_log_id ,
                                    'org_log_name' =>$org_log_data->name,
                                    'requested_date' => $data->first()->requested_date
                                    
                                ];
                            }else{
                                $requirement  = [];
                            }

                           
        
            }else{

                $data = UserRequest::where('org_log_id',$account->first()->org_log_id)
                                            ->where('status','A')
                                            ->orderBy('created_at', 'desc')
                                            ->get();

                if($data->isNotEmpty()) {

                    $requirement  = [

                        'id' => $data->first()->id,
                        'request_no' => $data->first()->request_no,
                        'purpose' => $data->first()->purpose,
                        'requested_date' =>  $data->first()->requested_date 
                    ];
                }else{
                    $requirement = [];
                }
                
            }

            $response = [
                'isSuccess' => true,
                'document_request' => $requirement
            ];  

            $this->logAPICalls('getDocumentRequestDashboard', "", $request->all(), $response);
            return response()->json($response,200);
        
        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => $e->getMessage()
            ];
         //   $this->logAPICalls('getDocumentRequest', "", $request->all(), $response);
            return response()->json($response, 500);

        }

    }

     //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //                            RECENT UPLOAD DASHBOARD                                                                   //
    //                                                                                                                     //
    //    Dito po kinuha ko yung account id ng nag login po para makuha yung role nila.                                   //
    //    IF Admin or Staff - Ma view niya lahat ng recent upload files ng mga Offices/Programs/Colleges.                //
    //    IF Dean - Lalabas yung mga recent upload files na ginawa nila pati yung mga program na under nila.            //
    //    IF Program chair / Program Head - Lalabas yung mga recent upload files na ginawa nila.                       //
    //                                                                                                                //
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function getRecentUploadDashboard(Request $request){

        $validated = $request->validate([
            'account_id' => ['required','exists:accounts,id']
        ]);

        $file = [];
        $account = Account::find($validated['account_id']);
        $role = $account->role;

        if($role == "Admin" || $role == "1" || $role == "Staff" || $role == "2"){

            $datas = RequirementFile::orderBy('created_at', 'desc')
                                            ->where('path','!=',null)
                                            ->where('status',"A")
                                            ->orderBy('created_at', 'desc')
                                            ->get();

        }elseif($role == "Dean" || $role == "3"){

            $datas = RequirementFile::where('college_entity_id',$account->org_log_id)
                                        ->orWhere('org_log_id',$account->org_log_id)
                                        ->where('status',"A")
                                        ->orderBy('created_at', 'desc')
                                        ->get();


        }else{

            $datas = RequirementFile::where('org_log_id',$account->org_log_id)
                                        ->where('status',"A")
                                        ->orderBy('created_at', 'desc')
                                        ->get();

        }

        if($datas->isNotEmpty()){

            foreach($datas as $data){
                $org = OrganizationalLog::find( $data->org_log_id);
                if(is_file(storage_path('app/public/'.$data->path))){
                    $file[] = [
                        'id' => $data->id,
                        'filename' =>  $data->filename,
                        'path' => $data->path,
                        'org_log_id' => $data->org_log_id,
                        'org_log_name' =>  $org ?  $org ->name : "Inactive"
                    ];
                }
            }

        }

        $response = [
            'isSuccess' => true,
            'document_request' => $file
        ];

        return response()->json($response);

    }


    // DONE //
    public function getComplianceDasboard(Request $request){
       try{

            $requirement = [];
            $validated = $request->validate([
                'account_id' => ['required','exists:accounts,id']
            ]);

            $account = Account::where('id',$validated['account_id'])->get();
            $role = $account->first()->role;

            if($role == "Dean" || $role == "3"){

            
                $datas = Event::where('college_entity_id', $account->first()->org_log_id)
                                    ->where('status', 'A')
                                    ->orderBy('created_at', 'desc')
                                    ->get();
                
                $datasCollection = collect($datas);
                
                $get_programs_id =  Program::where('college_entity_id',$account->first()->org_log_id)
                                            ->where('status','A')
                                            ->get();


                foreach($get_programs_id as $program_id){

                    $exists = Requirement::where('org_log_id', $program_id->id)
                                          ->orWhere('org_log_id',$account->first()->org_log_id)->get();

                        if($exists->isNotEmpty()){

                            if (!$datasCollection->contains('id',$exists->first()->event_id)) {

                                $data = Event::find($exists->first()->event_id);

                                // $newEvent = {
                                //     'id' =>  $data->id,
                                //     "name": "Event 7",
                                //     "org_log_id": "65",
                                //     "college_entity_id": "11",
                                //     "description": "Description 7",
                                //     "academic_year": "2024-2025",
                                //     "submission_date": "November 22 2024",
                                //     "approval_status": null,
                                //     "status": "A",
                                //     "created_at": "2024-10-21T03:06:37.000000Z",
                                //     "updated_at": "2024-10-21T03:06:37.000000Z"
                                // };

                                // If the id does not exist, add the new event to the collection
                              //  $datasCollection->push($newEvent);
                            }
                        }
                }

                return  $datasCollection->all();




                $requirement = []; // Initialize as an array

                foreach ($datas as $data) {
                    $program = OrganizationalLog::where('id',$data->org_log_id)->get();

                    if ($program) { // Ensure program exists
                        $requirement[] = [ // Append to the array
                            'college_id' => $account->first()->org_log_id,
                            'program_name' => $program->first()->name,
                            'submission_date' => $data->first()->submission_date
                        ];
                    }
                    
                }

                return response()->json($requirement);

            }else{


                $requirement = Event::where('org_log_id',$account->first()->org_log_id)
                                            ->where('status','A')
                                            ->orderBy('created_at', 'desc')
                                            ->get();

            }

            $response = [
                'isSuccess' => true,
                'document_request' =>$requirement 
            ];

            return response()->json($response);

       }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully created. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            return response()->json($response);


       }
    }


    private function percentOffice($currentPage,$isPaginate){

        $perPage = 10; // Set items per page

        $ReqOffice = [];
        
        // Paginate the results
        if($isPaginate){
             $datas = OrganizationalLog::where('org_id', '2')->paginate($perPage, ['*'], 'page', $currentPage);
        }else{
            $datas = OrganizationalLog::where('org_id', '2')->where('is_archived',0)->get();
        }
        
        // Process each item
        foreach ($datas as $data) {
            $totalReq = Requirement::where('org_log_id', $data->id)->count();
            $totalUpload = Requirement::where('org_log_id', $data->id)
                                        ->where('upload_status', 'completed')
                                        ->count();
        
            // Avoid division by zero
            $percentage = $totalReq > 0 ? ($totalUpload / $totalReq) * 100 : 0;
        
            $ReqOffice[] = [ 
                'id' => $data->id,
                'name' => $data->name,
                'acronym' => $data->acronym,
                'totalReq' => $totalReq,
                'totalUpload' => $totalUpload,
                'percentage' => $percentage
            ];
        
            $data->update([
                'percentage' => $percentage
            ]);
        }


        if($isPaginate){
             // Prepare pagination details
            $lastPage = $datas->lastPage();
            $currentPage = $datas->currentPage();
            $total = $datas->total();

            $response = [
                'data' => $ReqOffice,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'total' => $total
            ];
            return $response; // Return the paginated response 
        }else{
            return $ReqOffice; // Return all offices with their calculated data
        }
        
    }

    private function percentPrograms(){

        // COMPUTATION OF PROGRAM PERCENTAGE //

        $ReqProgram = [];
        $datas = OrganizationalLog::where('org_id', '3')->get();

        foreach ($datas as $data) {
            // Get counts directly

           // Get the total count of requirements related to the current Organizational Log
            $totalReq = Requirement::where('org_log_id', $data->id)->count();

            // Get the count of requirements that have the upload status 'completed'
            $totalUpload = Requirement::where('org_log_id', $data->id)
                                    ->where('upload_status', 'completed')
                                    ->count();

      
            // Avoid division by zero when calculating the percentage
            // If there are no requirements, set percentage to 0
            $percentage = $totalReq > 0 ? ($totalUpload / $totalReq) * 100 : 0;

            $ReqProgram[] = [ 
                'id' => $data->id,
                'name' => $data->name,
                'acronym' => $data->acronym,
                'totalReq' => $totalReq,
                'totalUpload' => $totalUpload,
                'percentage' => $percentage
            ];

            $data->update([
                'percentage' =>  $percentage
            ]);
        }

        return $ReqProgram;

    }

    private function percentColleges($currentPage,$isAdmin){

        $perPage = 10; // Set items per page

        $this-> percentPrograms(); // Call the function to set the percent of programs (presumably doing some setup or calculation)
        $ReqCollege= [];
        
        // Check if the user is an admin
        if($isAdmin){
              // If the user is an admin, fetch the Organizational Logs with pagination (only unarchived entries)
             // Pagination is applied with 'page' and 'currentPage' parameters to paginate the results
              $datas = OrganizationalLog::where('org_id', '1')
                      ->where('is_archived',0)->paginate($perPage, ['*'], 'page', $currentPage);
        }else{
             // If the user is not an admin, fetch all Organizational Logs without pagination
              $datas = OrganizationalLog::where('org_id', '1')->where('is_archived',0)->get();
        }
      
        foreach ($datas as $data) {

            $totalreqCollege = 0;
            $totalUploadreqCollege=0;
            $percentage = 0;

             // Fetch the programs associated with the current Organizational Log (college)
            $programs = Program::where('college_entity_id', $data->id )->get();

            foreach ($programs as $program){

                // Count the total number of requirements for the current program
                $totalReq = Requirement::where('org_log_id', $program->program_entity_id)->count();
                
                // Count the number of completed uploads for the current program
                $totalUpload = Requirement::where('org_log_id',$program->program_entity_id)
                                    ->where('upload_status', 'completed')
                                    ->count();

                $totalreqCollege += $totalReq;
                $totalUploadreqCollege += $totalUpload;

                // Calculate the percentage of completed uploads
                // Prevent division by zero by checking if there are any requirements
                $percentage = $totalreqCollege > 0 ? ($totalUploadreqCollege /$totalreqCollege) * 100 : 0;

            }   

            // Update the Organizational Log entry with the calculated percentage
            $data->update([
                'percentage' =>  $percentage
            ]);
        
        
            $ReqCollege[] = [ 
                'id' => $data->id,
                'name' => $data->name,
                'acronym' => $data->acronym,
                'totalReq' => $totalreqCollege,
                'totalUpload' => $totalUploadreqCollege,
                'percentage' => $percentage
            ];

        }

        // Check if the user is an admin to return paginated response
        if($isAdmin){
            $lastPage = $datas->lastPage();
            $currentPage = $datas->currentPage();
            $total = $datas->total();
    
            $response = [
                'data' => $ReqCollege,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'total' => $total
            ];
            return $response; // Return the paginated response for admins
        }else{
           // If the user is not an admin, return the non-paginated college data
           return $ReqCollege;  // Return all colleges with their calculated data
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