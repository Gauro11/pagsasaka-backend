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

class DashboardController extends Controller
{

    private function percentOffice(){

        $ReqOffice = [];
        $datas = OrganizationalLog::where('org_id', '2')->get();

        foreach ($datas as $data) {
            // Get counts directly
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
                'percentage' =>  $percentage
            ]);
        }

        return $ReqOffice;
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

    private function percentColleges(){

        $this-> percentPrograms();
        $ReqCollege= [];
        $datas = OrganizationalLog::where('org_id', '1')->get();
      
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

        return $ReqCollege;

    }

    public function getAdminDashboard(Request $request){

        $response = [
            'isSuccess' => true,
            'colleges' => $this->percentColleges(),
            'offices' => $this->percentOffice()
        ];

        return response()->json($response);

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


     // DONE //
    public function getDocumentRequest(Request $request){

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
                                        ->orderBy('created_at', 'desc')
                                        ->get();
                
                    $org_log_data = OrganizationalLog::where('id',$data->first()->org_log_id)->first();

                    $requirement  = [

                        'id' => $data->first()->id,
                        'request_no' => $data->first()->request_no,
                        'org_log_id' => $data->first()->org_log_id,
                        'org_log_name' => $org_log_data->name,
                        'requested_date' =>  $data->first()->requested_date
                        
                    ];


        }else{

            $data = UserRequest::where('org_log_id',$account->first()->org_log_id)
                                        ->where('status','A')
                                        ->orderBy('created_at', 'desc')
                                        ->get();

            
            $requirement  = [

                        'id' => $data->first()->id,
                        'request_no' => $data->first()->request_no,
                        'purpose' => $data->first()->purpose,
                        'requested_date' =>  $data->first()->requested_date
                        
                    ];
        }

        $response = [
            'isSuccess' => true,
            'document_request' => $requirement
        ];

        return response()->json($response);

    }

    // DONE //
    public function getRecentUpload(Request $request){

        $validated = $request->validate([
            'account_id' => ['required','exists:accounts,id']
        ]);

        $account = Account::where('id',$validated['account_id'])->get();
        $role = $account->first()->role;

        if($role == "Admin" || $role == "1" || $role == "Staff" || $role == "2"){

            $requirement = RequirementFile::orderBy('created_at', 'desc')
                                            ->where('status',"A")->get();

        }elseif($role == "Dean" || $role == "3"){

            $requirement = RequirementFile::where('college_entity_id',$account->first()->org_log_id)
                                        ->where('status',"A")
                                        ->orderBy('created_at', 'desc')
                                        ->get();


        }else{

            $requirement = RequirementFile::where('org_log_id',$account->first()->org_log_id)
                                        ->where('status',"A")
                                        ->orderBy('created_at', 'desc')
                                        ->get();

        }

        $response = [
            'isSuccess' => true,
            'document_request' => $requirement
        ];

        return response()->json($response);

    }

    // DONE //
    public function getCompliance(Request $request){
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

    
}