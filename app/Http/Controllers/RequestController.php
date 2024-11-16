<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\requestUser;
use App\Models\UserRequest;
use App\Models\OrganizationalLog;
use App\Models\Account;
use App\Models\Program;
use Carbon\Carbon;
use Throwable;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;


class RequestController extends Controller
{
    
    public function updateReqStatus(Request $request){

        try{

            $validated = $request->validate([
                'account_id' => ['required','exists:user_requests,id']
            ]);
    
            $account = Account::where('id',$validated['account_id'])->get();
    
            if($account->first()->role == "Admin"){
    
                $validated = $request->validate([
                    'req_id' => ['required','exists:user_requests,id']
                ]);
    
                $data = Account::where('id',$validated['req_id'])->get();
                $data->update([
                    'status' => "I"
                ]);
    
                $response = [
                    'isSuccess' => true,
                    'message' => "Successfully updated!"
                ];

                $this->logAPICalls('updateReqStatus', "", $request->all(), $response);
                return response()->json($response, 200);
            }
    
            $response = [
                'isSuccess' => true,
                'message' => "Only admins can access this API."
            ];
            
            $this->logAPICalls('updateReqStatus', "", $request->all(), $response);
            return response()->json($response, 403);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => 'Please contact support.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateReqStatus', "", $request->all(), $response);
            return response()->json($response, 500);
        }

    }

    public function getRequest(Request $request){
        
        try{

            $validated = $request->validate([
                'account_id' => 'required|exists:accounts,id'
            ]);

            $perPage = 10;
            $datas = null;
            $page = $request->input('page', 1);
            $search = $request->input('search', ''); 
            $role = $this->getRole($validated['account_id']);

    
            // Check if the search term is empty
            if(empty($search)){
                
                // If user has roles Admin, 1, Staff, or 2, fetch paginated data for all active (non-archived) requests
                if ($role == "Admin" || $role == "1" || $role == "Staff" || $role == "2") {
                    
                    // Get paginated user requests, ordered by creation date, where the request is not archived
                    $datas = UserRequest::where('is_archived', 0)
                                        ->orderBy('created_at', 'desc')
                                        ->paginate($perPage, ['*'], 'page', $page);
                    
                } else {
                    
                    // For other roles, fetch user requests where role matches and the request is not archived
                    $datas = UserRequest::where('role', $role)
                                        ->where('is_archived', 0)
                                        ->orderBy('created_at', 'desc')
                                        ->get();
                }

                // Initialize the arrays to store formatted data
                $requestData = $response = [];

                // Loop through each request data to retrieve additional information (like org_log acronym)
                foreach($datas as $data){
                    $org = OrganizationalLog::where('id', $data->org_log_id)->first();
                    $date = new \DateTime($data->created_at);
                    $formattedDate = $date->format('F j, Y');

                    $requestData[] = [
                        'id' => $data->id,
                        'request_no' => $data->request_no,
                        'account_id' => $data->account_id,
                        'org_log_acronym' =>  $org->acronym ,
                        'purpose' => $data->purpose,
                        'requested_date' => $formattedDate,
                        'files' =>  $data->files,
                        'qtyfile' => $data->qtyfile,
                        'approval_status' => $data->approval_status,
                        'status' =>  $data->status,
                    ];
                }

            } else {

                // If a search term is provided, perform a search based on the request number
                if($role == "Admin" || $role == "1" || $role == "Staff" || $role == "2"){

                    // Fetch paginated results for non-archived requests and search by request_no
                    $datas = UserRequest::where('is_archived',0)
                                        ->orderBy('created_at', 'desc')
                                        ->where(function($q) use ($search) {
                                            $q->where('request_no', 'LIKE', "%{$search}%");
                                        })
                                        ->paginate($perPage, ['*'], 'page', $page);
                    
                } else {
                    
                    // For other roles, fetch non-archived requests with role matching, and search by request_no
                    $datas = UserRequest::where('role', $role)
                                        ->where('is_archived', 0)
                                        ->orderBy('created_at','desc')
                                        ->when($search, function ($q) use ($search) {
                                            return $q->where('request_no', 'LIKE', "%{$search}%");
                                        })
                                        ->get();
                }

                // Initialize the array to store formatted data
                $requestData = [];

                // Loop through each request data to retrieve additional information (like org_log acronym)
                foreach($datas as $data){
                    $org = OrganizationalLog::where('id', $data->org_log_id)->first();
                    $date = new \DateTime($data->created_at);
                    $formattedDate = $date->format('F j, Y');

                    $requestData[] = [
                        'id' => $data->id,
                        'request_no' => $data->request_no,
                        'account_id' => $data->account_id,
                        'org_log_acronym' =>  $org->acronym ,
                        'purpose' => $data->purpose,
                        'requested_date' => $formattedDate,
                        'files' =>  $data->files,
                        'qtyfile' => $data->qtyfile,
                        'approval_status' => $data->approval_status,
                        'status' =>  $data->status
                    ];
                }
            }
           
            // susunod na code para sa response ng pagination ng Admin/ staff and not admin.            
            if ($role == "Admin" || $role == "1" || $role == "Staff" || $role == "2"){

                $response = [
                    'isSuccess' => true,
                    'requestData' => $requestData,
                    'current_page' => $datas->currentPage(),
                    'from' => $datas->firstItem(),
                    'last_page' => $datas->lastPage(),
                    'per_page' => $datas->perPage(),
                    'to' => $datas->lastItem(),
                    'total' => $datas->total()
                ];

            }else{
                   // Pagination for the users na hindi admin and staff.
                   // Get pagination parameters from request body (or query parameters)
                    $page = $request->input('page', 1); // Current page (default to 1)
                    $search = $request->input('search', ''); // Optional search term

                    // Retrieve your data (filtered by search, etc.)
                   // $requestData = $this->getRequestData($search);  // Example method to get the data

                    // Calculate total count (total number of records)
                    $totalItems = count($requestData); // Total records in the array

                    // Slice the data to get the current page's items
                    $slicedData = array_slice($requestData, ($page - 1) * $perPage, $perPage);

                    // Create a LengthAwarePaginator instance
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

                    // Prepare the response with paginated data
                    $response = [
                        'isSuccess' => true,
                        'requestData' => $paginator->items(),      // Current page's items
                        'current_page' => $paginator->currentPage(), // Get the current page
                        'from' => $paginator->firstItem(),         // The first item on this page
                        'last_page' => $paginator->lastPage(),     // The last page
                        'per_page' => $paginator->perPage(),       // Number of items per page
                        'to' => $paginator->lastItem(),            // The last item on this page
                        'total' => $totalItems,                    // Total number of records
                    ];

            }

            $this->logAPICalls('getRequest', "", $request->all(), $response);
            return response()->json($response,200);


        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => 'Please contact support.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getRequest', "", $request->all(), $response);
            return response()->json($response, 500);
        }

    }

    public function createRequest(requestUser $request){

        try{

            $fileNames = [];
            $year = Carbon::now()->year;

            // Validate the incoming request data. Yung validataion po nito nasa http/requests/requestUser
            $validate = $request->validated();

            // Retrieve the role of the account using the provided account_id
            $role = $this->getRole($validate['account_id']);
            $account = Account::find($validate['account_id']);   // Find the account associated with the account_id
   
            foreach ($request->input('files') as $file) {
                $fileNames[] = ['filename' => $file]; 
            }
            
            // Retrieve the associated program based on the account's organization log ID
            $program = Program::where('program_entity_id',$account->org_log_id)->first();
            $college_id = !empty($program) ? $program->college_entity_id : null; // Extract the college_entity_id if program exists, otherwise set it to an empty string
            
            // Check if a similar request already exists in the database (same purpose, account, and files)
            if (UserRequest::where('purpose',$validate['purpose'])
                            ->where('account_id',$validate['account_id'])
                            ->where('files', json_encode($fileNames))
                            ->where('is_archived',0)
                            ->exists()){
                                
                    
                    $response = [
                        'isSuccess' => false,
                        'message'=> 'The Request you are trying to register already exists. Please verify your input and try again.'
                    ];

                    $this->logAPICalls('createRequest', "", $request->all(), [$response]);
                    return response()->json($response,500);
            }

                UserRequest::create([
                    'request_no' => $year,
                    'purpose' => $validate['purpose'],
                    'account_id' => $validate['account_id'],
                    'org_log_id' => $account->org_log_id,
                    'college_entity_id' => $college_id,
                    'approval_status' => "pending",
                    'qtyfile' =>count($fileNames),
                    'role' => $role ,
                    'files' => json_encode($fileNames)
                ]);
                
                // Update the request number after successful creation
                $this->updateReqNo();

            $response = [
                'isSuccess' => true,
                'message' => "Successfully created."
            ];

            $this->logAPICalls('createRequest', "", $request->all(), [$response]);
            return response()->json($response,200);

            
        }catch (Throwable $e) {

            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully created. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('createRequest', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    }

    public function getRequestInformation(Request $request){

        try{

            $validated = $request->validate([
                'request_id' => ['required','exists:user_requests,id']
            ]);

            // Retrieve the UserRequest record(s) based on the validated 'request_id'
            $data = UserRequest::where('id',$validated['request_id'])->get();

            // Find the associated OrganizationalLog entry using the 'org_log_id' from the first retrieved UserRequest
            $org = OrganizationalLog::find($data->first()->org_log_id);
            $org_name = $org->name; // Get the organization name from the OrganizationalLog model

            // Loop through the retrieved UserRequest data and decode the 'files' field from JSON format
            foreach ($data as $item) {
                $item->files = json_decode($item->files, true);
            }
            
            $response = [
                'isSuccess' => true,
                'request-information' => $data,
                'org_log_name' => $org_name
            ];

            $this->logAPICalls('getRequestInformation', "", $request->all(), $response);
            return response()->json($response,200);

        }catch(Throwable $e){
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Account.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getRequestInformation', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    public function getAcceptRequest(Request $request){
        $invalidId = []; 
        $validated = $request->validate([
            'requests_id' => ['required']
        ]);

       foreach($validated['requests_id'] as $request_id){

            $data = UserRequest::where('id', $request_id)->first();

            if ($data) {
                $data->update(['status' => "completed"]);
            } else {
                $invalidId[] = $request_id;
            }

       }

       $response = [

            'isSuccess' => true,
            'message' => "Requests have been accepted successfully.",
            'invalid_id' =>$invalidId

       ];

       return response()->json($response);

    }

    public function rejectRequest(Request $request){

        try{

            $validated = $request->validate([
                'request_id' => ['required', 'exists:user_requests,id']
            ]);

            // Retrieve the UserRequest record that matches the validated 'request_id'
            $data = UserRequest::where('id',$validated['request_id'])->first();
           
           // Update the status of the retrieved UserRequest to 'rejected'
            $data->update([
                'status' => 'rejected'
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Request have been rejected successfully.'
            ];

            $this->logAPICalls('rejectRequest', "", $request->all(), $response);
            return response($response ,200);

            }catch(Throwable $e){

                $response = [
                    'isSuccess' => false,
                    'message' => "Please contact support.",
                    'error' => $e->getMessage()
                ];
                $this->logAPICalls('rejectRequest', "", $request->all(), $response);
                return response()->json($response, 500);

            }
        
    }


    private function getRole($account_id){

        $data = Account::where('id',$account_id)->first();
        return $data->role;
    }
    

    private function  updateReqNo(){

        $datas = UserRequest::all();

        foreach($datas as $data){

        $formattedNumber = str_pad($data->id, 4, '0', STR_PAD_LEFT);
        $req_no = $data->request_no;

        if (strpos($req_no , '-') == false) {

                $new_req_no = $data->request_no."-". $formattedNumber;
                $data->update([
                    'request_no' =>  $new_req_no
                ]);
            }  
        }

        return $data;
    }

    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try {
            \App\Models\ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }



}

