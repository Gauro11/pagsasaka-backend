<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\ApiLog;

class AccountController extends Controller
{
    public function getAccounts(Request $request){

        try{
            
            $currentPage = $request->input('page', 1);

            $data = Account::where('status', 'A')->paginate(10, ['*'], 'page', $currentPage);

            if ($data->isEmpty() && $currentPage > $data->lastPage()) {
                $response = [
                    'isSuccess' => true,
                    'message' => 'The page you requested does not exist.'
                ];
                $this->logAPICalls('getAccounts', "", $request->all(), [$response]);
                return response()->json($response, 404);
            } 
            
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];

            $this->logAPICalls('getAccounts', "", $request->all(), [$response]);
            return response()->json($response,200);

        }catch(Throwable $ex){
            $response = [
                'isSuccess' => false,
                'message' => "UPlease contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

           $this->logAPICalls('getAccounts', "", $request->all(), [$response]);
           return response()->json($response, 500);
        }

    }

    public function storeAccount(AccountRequest $request){

        try{
          
           $validate = $request->validated();
           $data= Account::create($validate);

           $response = [
                'isSuccess' => true,
                'message' => "Successfully created."
           ];

           $this->logAPICalls('storeAccount', "", $request->all(), [$response]);
           return response()->json($response);
            
        }catch (Exception $e) {

            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully created. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('storeAccount', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }

    }

    public function updateAccount(Request $request){

        try{
          
           $validate = $request->validate([
                'name' => 'required|min:5|max:150',
                'organization_id' => 'required',
                'role' => 'required'
           ]);


           $exists = Account::where('name', $validate['name'])
                  ->where('organization_id', $validate['organization_id'])
                  ->where('role', $validate['role'])
                  ->exists();

            if ($exists) {
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'An account with the same details already exists.'
                ];

                $this->logAPICalls('updateAccount', "", $request->all(), [$response]);

                return response()->json($response, 422);
            }else{
                $account = Account::find($request->id);
                $account->update($validate);
                $response = [
                          'isSuccess' => true,
                           'message' => "Successfully updated."
                    ];
                $this->logAPICalls('updateAccount', "", $request->all(), [$response]);
                return response()->json($response);
            }
            
        }catch (Exception $e) {

            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully updated. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('updateAccount', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }

    }

    public function deleteAccount(Request $request){

        try{

            $organization = Account::find($request->id);
            $organization->update(['status' => $request->status]);
            $response = [
                'isSuccess' => true,
                'message' => "Successfully deleted."
            ];

            $this->logAPICalls('deleteAccount', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully deleted. Please try again.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('deleteAccount', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
        

    }

    public function editAccount(Request $request){

        try{

            $data = Account::find($request->id);
            $response = [
                'isSuccess' => true,
                 'data' => $data
            ];
    
            $this->logAPICalls('editAccount', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully edited. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];
            $this->logAPICalls('editAccount', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }

    }


    public function searchAccount(Request $request){

        try{
            $query = $request->input('query');

            $results = Account::where('name', 'like', '%' . $query . '%')
                            ->orWhere('email', 'like', '%' . $query . '%')
                            ->orWhere('role', 'like', '%' . $query . '%')
                            ->orWhere('entityid', 'like', '%' . $query . '%')
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
