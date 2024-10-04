<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\requestUser;
use App\Models\UserRequest;
use App\Models\Account;
use App\Models\Program;

use Carbon\Carbon;

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

        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Account.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateReqStatus', "", $request->all(), $response);
            return response()->json($response, 500);
        }

    }

    public function getRequest(Request $request){
        
        try{

            $validate = $request->validate([
                'account_id' => 'required'
            ]);
    
            $role = $this->getRole($validate['account_id']);
    
            if(empty($request->search)){
    
                $data = UserRequest::where('role', $role)->get();
    
            }else{
    
                $results = UserRequest::where('role', $role)
                    ->when($query, function ($q) use ($query) {
                        return $q->where(function ($queryBuilder) use ($query) {
                            $queryBuilder->where('request_no', 'LIKE', "%{$request->search}%");
                        });
                    })->get();
            }
    
    
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];
            
            $this->logAPICalls('getRequest', "", $request->all(), $response);
            return response()->json($response,200);


        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Account.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getRequest', "", $request->all(), $response);
            return response()->json($response, 500);
        }

    }


    public function storeRequest(requestUser $request){

        try{

            $fileNames = [];
            $year = Carbon::now()->year;
            $currentDate = Carbon::now()->format('F j, Y');

            $validate = $request->validated();

            $role = $this->getRole($validate['account_id']);
            $account = Account::where('id',$validate['account_id'])->get();
                    
            foreach ($request->input('files') as $file) {
                $fileNames[] = ['filename' => $file]; 
            }
            
            $program = Program::where('program_entity_id',$account->first()->org_log_id)->get();
            $college_id = !$program->isEmpty() ?   $program->first()->college_entity_id : "";
            
            if (UserRequest::where('purpose',$validate['purpose'])
                            ->where('account_id',$validate['account_id'])
                            ->where('files', json_encode($fileNames))
                            ->exists()){
                                
                    
                    $response = [
                        'isSuccess' => false,
                        'message'=> 'The Request you are trying to register already exists. Please verify your input and try again.'

                    ];

                    $this->logAPICalls('storeRequest', "", $request->all(), [$response]);
                    return response()->json($response,422);
            }

                $data= UserRequest::create([
                    'request_no' => $year,
                    'purpose' => $validate['purpose'],
                    'account_id' => $validate['account_id'],
                    'org_log_id' => $account->first()->org_log_id,
                    'college_entity_id' => $college_id,
                    'requested_date' => $currentDate,
                    'approval_status' => "pending",
                    'qtyfile' =>count($fileNames),
                    'role' => $role ,
                    'files' => json_encode($fileNames)
                ]);
                
                $this->updateReqNo();
            


            $response = [
                'isSuccess' => true,
                'message' => "Successfully created."
            ];

            $this->logAPICalls('storeRequest', "", $request->all(), [$response]);
            return response()->json($response);


            
        }catch (Exception $e) {

            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully created. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('storeRequest', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    }

    public function getReqInfo(Request $request){

        try{

            $validated = $request->validate([
                'request_id' => ['required','exists:user_requests,id']
            ]);

            $data = UserRequest::where('id',$validated['request_id'])->get();

            foreach ($data as $item) {
                $item->files = json_decode($item->files, true);
            }
            
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];

            $this->logAPICalls('getReqInfo', "", $request->all(), $response);
            return response()->json($response);

        }catch(Exception $e){
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Account.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('getReqInfo', "", $request->all(), $response);
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
                $data->update(['aproval_status' => "completed"]);
            } else {
                $invalidId[] = $request_id;
            }
       }

       $response = [

            'isSuccess' => true,
            'message' => "Successfully updated!",
            'invalid_id' =>$invalidId

       ];

       return response()->json($response);

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

