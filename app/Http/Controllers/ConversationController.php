<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RequirementConversation;
use App\Http\Requests\ConversationRequest;
use Carbon\Carbon;

class ConversationController extends Controller
{

    public function storeConverstation(ConversationRequest $request){

        try{
           
            $currentDate = Carbon::now()->format('m/d/Y');
            $currentTime = Carbon::now()->format('g:ia');

            $data = RequirementConversation::create([

                'requirement_id' => $request->requirement_id,
                'account_id' => $request->account_id,
                'org_log_id' => $request->org_log_id,
                'message' => $request->message,
                'date' =>  $currentDate,
                'time' =>  $currentTime

            ]);

            $response = [
                    'isSuccess' => true,
                    'message' => "Comment successfully",
                    'date' => $data
            ];
 
            return response()->json($response);
          }catch (Exception $e) {
  
             $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            //$this->logAPICalls('storeAccount', "", $request->all(), [$response]);
            return response()->json($response, 500);

  
        }
 
    }

    public function getConvesation(Request $request){

        try{

            $request->validate([
                'requirement_id' =>'required'
            ]);

            $data = RequirementConversation::where('requirement_id',$request->requirement_id)->get();

            $response = [
                'isSuccess' => true,
                'data' => $data
            ];

            return response()->json($response, 200);

        }catch(Exception $e){

        }
    }
}
