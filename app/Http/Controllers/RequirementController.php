<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Requirement;
use App\Models\ApiLog;


class RequirementController extends Controller
{

    public function getRequirement(Request $request){

       try{

            $data = Requirement::where('event_id',$request->event_id)->get();
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];

            $this->logAPICalls('getRequirement', "", $request->all(), [$response]);
            return response()->json($response, 200);

       }catch(Exception $e){
            
        $response = [

                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()

        ];

            $this->logAPICalls('getRequirement', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function searchRequirement(Request $request){

        try{
            
            $query = $request->input('query');
            $event_id = $request->input('event_id');

            $results = Requirement::where('event_id', $event_id)
            ->when($query, function ($q) use ($query) {
                return $q->where(function ($queryBuilder) use ($query) {
                    $queryBuilder->where('name', 'LIKE', "%{$query}%")
                                 ->orWhere('acronym', 'LIKE', "%{$query}%");
                });
            })->get();

            $response = [
                'isSuccess' => true,
                'results' => $results
            ];

            $this->logAPICalls('searchRequirement', "",$request->all(), [$response]);
            return response()->json($response);

        }catch(Exception $e){
            $response = [
                'isSuccess' => false,
                'message' => "Search failed. Please try again later.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('searchRequirement', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function deleteRequirement(Request $request){
        
        try{

            $organization = Requirement::find($request->id);
            $organization->update(['status' => "I"]);
            $response = [
                'isSuccess' => true,
                'message' => "Successfully deleted."
            ];

         //   $this->logAPICalls('deleteRequirement', "", $request->all(), [$response]);
            return response()->json($response);

        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully deleted. Please try again.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

          //  $this->logAPICalls('deleteRequirement', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
        
    }

   // public function uploadfileRequirement(Request $request){

        // Validate the request
        // $request->validate([
        //     'file' => 'required|file|max:2048', // 2MB Max
        // ]);

        // // Store the file
        // $path = $request->file('file')->store('uploads', 'public');

        // Return response
        // return response()->json([
        //     'message' => 'File uploaded successfully',
        //     'path' => $path,
        // ]);
    
        
       // $file = $request->file("file");

    //    if( $uploaded_files = $request->file->store('public/uploads')){
    //     return ['result' => $uploaded_files];
    //    }else{
    //     return "failed!";
    //    }
       
        // $filePaths = [];
        // $filePaths[] = "apple";
        // $filePaths[] = "banana";
        // foreach ($request->file('file') as $file) {
        //     $filePaths[] = $file->getRealPath(); // Store each file
        // }

        // return $filePaths;
       // return
        // if($file->move("upload",$file->getClientOriginalName())){
        //     return "file upload sucess!";
        // }else{
        //     return "failed  to upload file!";
        // }
 //   }

}
