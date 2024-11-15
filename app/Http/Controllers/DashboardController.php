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
            'colleges' => $this->percentColleges($currentPage_college), 
            'offices' => $this->percentOffice($currentPage_office)
        ];

        return response()->json($response,200);
    }


    public function getDeanDashboard(Request $request){

        $programs =  [];
        $validated = $request->validate([
            'college_id' => ['required', 'exists:organizational_logs,id']
        ]);
        
        $datas = collect($this->percentColleges()); 
        
        $college = $datas->firstWhere('id', $validated['college_id']);
        
       $datas = Program::where('college_entity_id',$validated['college_id'] )->get();

        foreach( $datas as $data ){

                $program = OrganizationalLog::where('id',$data->program_entity_id)->get();
                $programs[] = [
                    'id' => $data->program_entity_id,
                    'name' => $program->first()->name,
                    'acronym' => $program->first()->acronym,
                    'percentage' => $program->first()->percentage
                ];

        }

        return [
            'college' => $college ,
            'programs' =>  $programs
        ];

    }

    public function getProgramDashboard(Request $request){

        $validated = $request->validate([
            'program_id' => ['required', 'exists:organizational_logs,id']
        ]);
        
        $datas = collect($this->percentPrograms()); 
        
        $program = $datas->firstWhere('id', $validated['program_id']);

        return $program;
    }

    public function getHeadDashboard(Request $request){

        $validated = $request->validate([
            'office_id' => ['required', 'exists:organizational_logs,id']
        ]);
        
        $datas = collect($this->percentOffice()); 
        
        $office = $datas->firstWhere('id', $validated['office_id']);

        return $office;

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

            $datas = UserRequest::where('status', 'A')
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
        
        }catch(Exception $e){

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
                                $datasCollection->push($newEvent);
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

       }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully created. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            return response()->json($response);


       }
    }


    private function percentOffice($currentPage){

        $perPage = 10; // Set items per page

        $ReqOffice = [];
        
        // Paginate the results
        $datas = OrganizationalLog::where('org_id', '2')->paginate($perPage, ['*'], 'page', $currentPage);
        
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



        return $response;
    }

    private function percentPrograms(){

        // COMPUTATION OF OFFICE PERCENTAGE //

        $ReqProgram = [];
        $datas = OrganizationalLog::where('org_id', '3')->get();

        foreach ($datas as $data) {
            // Get counts directly

            $totalReq = Requirement::where('org_log_id', $data->id)->count();
            $totalUpload = Requirement::where('org_log_id', $data->id)
                                    ->where('upload_status', 'completed')
                                    ->count();

            // Avoid division by zero

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

    private function percentColleges($currentPage){

        $perPage = 10; // Set items per page

        $this-> percentPrograms();
        $ReqCollege= [];
        
        $datas = OrganizationalLog::where('org_id', '1')->paginate($perPage, ['*'], 'page', $currentPage);
      
        foreach ($datas as $data) {

            $totalreqCollege = 0;
            $totalUploadreqCollege=0;
            $percentage = 0;

            $programs = Program::where('college_entity_id', $data->id )->get();

            foreach ($programs as $program){

                $totalReq = Requirement::where('org_log_id', $program->program_entity_id)->count();
                $totalUpload = Requirement::where('org_log_id',$program->program_entity_id)
                                    ->where('upload_status', 'completed')
                                    ->count();

                $totalreqCollege += $totalReq;
                $totalUploadreqCollege += $totalUpload;
                $percentage = $totalreqCollege > 0 ? ($totalUploadreqCollege /$totalreqCollege) * 100 : 0;

            }

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

        $lastPage = $datas->lastPage();
        $currentPage = $datas->currentPage();
        $total = $datas->total();

        $response = [
            'data' => $ReqCollege,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'total' => $total
        ];

        return $response;

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