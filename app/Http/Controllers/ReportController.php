<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Organizationallog;
use App\Models\UserRequest;
use App\Models\Requirement;
use App\Models\Program;

class ReportController extends Controller
{

    // DONE //

    public function getReportRequest(Request $request){

        $validated = $request->validate([
            'classification' => ['required'],
            'page' => ['nullable'],
            'college_id' => ['nullable','exists:programs,college_entity_id'],
            'program_id' => ['nullable','exists:organizational_logs,id']
        ]);
        
        $page = $validated['page'] ?? 1; 
        $perPage = 10; 
        
        $query = DB::table('user_requests')
            ->join('organizational_logs', 'user_requests.org_log_id', '=', 'organizational_logs.id')
            ->where('user_requests.status','A');
        
        if($validated['classification'] == 1) {
            $query->where('organizational_logs.org_id', '!=', 2);

            if(!empty($validated['college_id'])){
                $query->where('user_requests.college_entity_id',$validated['college_id']);
            }

            if(!empty($validated['program_id'])){
                $query->where('user_requests.org_log_id',$validated['program_id']);
            }
            
        } else {

            $query->where('organizational_logs.org_id', '=', 2);

            if(!empty($validated['program_id'])){
                $query->where('user_requests.org_log_id',$validated['program_id']);
            }
        }
        
      
        $report = $query->select('user_requests.*')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        $response = [
            'isSuccess' => true,
            'reportRequest' => $report
        ];
        
        return response()->json($response);
        
    }

       // DONE //
    public function getComplianceReport(Request $request){
        
        $validated = $request->validate([
            'classification' => ['required'],
            'college_id' => ['nullable','exists:programs,college_entity_id']
        ]);

        $response=$result="";

        if($validated['classification'] == 1) {

            $ReqCollege= [];
            $college_programs= [];
        

            $datas = OrganizationalLog::where('org_id', '1')->get();
          
            foreach ($datas as $data) {

                $college_programs= [];
                $totalreqCollege = 0;
                $totalUploadreqCollege=0;
                $percentage = 0;
    
                $programs = Program::where('college_entity_id', $data->id )->get();
    
                foreach ($programs as $program){
                    
                    
                    $totalReq = Requirement::where('org_log_id', $program->program_entity_id)->count();
                    $totalUpload = Requirement::where('org_log_id',$program->program_entity_id)
                                        ->where('upload_status', 'completed')
                                        ->count();

                    $org_log = OrganizationalLog::where('id',$program->program_entity_id)->get();

                    $college_programs[] = [
                        'id' => $program->program_entity_id,
                        'name' => $org_log->first()->name,
                        'totalReq' =>  $totalReq ,
                        'totalUpload' => $totalUpload,
                        'percentage' =>  $totalReq > 0 ? ($totalUpload /$totalReq) * 100 : 0
                    ];

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
                    'percentage' => $percentage,
                    'programs'=> $college_programs
                ];
    
            }
            
            if(!empty($validated['college_id'])){

                $datas = collect($ReqCollege); 
        
                $college = $datas->firstWhere('id', $validated['college_id']);
                $result = $college;

            }else{
                $result = $ReqCollege;
            }
    
            $response = [
                'isSuccess' => true,
                'reportCompliance' => $result 
            ];

        }else{
            
            $validated = $request->validate([
                'page' => ['nullable'], // Validate the page parameter
            ]);
        
            $ReqOffice = [];
            $perPage = 10; // Halimbawa: 10 items per page
            $page = $validated['page'] ?? 1; // Default to page 1 if not provided
        
            $datas = OrganizationalLog::where('org_id', '2')->get();
        
            foreach ($datas as $data) {
                // Get counts directly
                $totalReq = Requirement::where('org_log_id', $data->id)
                                        ->where('status', 'A')->count();
        
                $totalUpload = Requirement::where('org_log_id', $data->id)
                                          ->where('upload_status', 'completed')
                                          ->where('status', 'A')
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
        
                // Update percentage in the database
                $data->update([
                    'percentage' => $percentage
                ]);
            }
        
            // Paginate the ReqOffice array
            $currentPage = $page;
            $offset = ($currentPage - 1) * $perPage;
            $paginatedItems = array_slice($ReqOffice, $offset, $perPage);
            $total = count($ReqOffice);
            $lastPage = ceil($total / $perPage);
        
            $response = [
                'isSuccess' => true,
                'reportCompliance' => $paginatedItems,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'total' => $total,
            ];
        }
        
        return response()->json($response);

    }
}
