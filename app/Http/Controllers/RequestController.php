<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\requestUser;
use App\Models\UserRequest;

use Carbon\Carbon;

class RequestController extends Controller
{
    //
    public function index(){
       

    }

    public function storeRequest(requestUser $request){

        try{
          
            $validate = $request->validated();
            $data= UserRequest::create($validate);
 
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
 
             $this->logAPICalls('storeAccount', "", $request->all(), [$response]);
             return response()->json($response, 500);
 
         }
    }

    function generateRequestNo($year, $number) {

        $digitYearCount = strlen((string) $year);
        $formattedYear = str_pad($year, $digitYearCount, '0', STR_PAD_LEFT);
  
        $formattedNumber = str_pad($number, 4, '0', STR_PAD_LEFT);
        return $formattedYear . '-' . $formattedNumber;
    }

}
