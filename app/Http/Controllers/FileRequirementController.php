<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RequirementFile;
use App\Models\RequirementFolder;

class FileRequirementController extends Controller
{
    
    public function getFileRequirement(Request $request){

        $validate = $request->validate([
            'requirement_id' => 'required'
        ]);
        $data = RequirementFile::where('requirement_id',$validate['requirement_id'])
                                    ->orderBy('created_at', 'desc') 
                                    ->get();
        
        $response = [
            'isSuccess' => true,
            'data' => $data
        ];
        
        //$this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
        return response()->json($response, 200);

    }

    public function storeFileRequirement(Request $request){


            if ($request->hasFile('files')) {

                 $request->validate([
                    'files.*' => 'required|file|max:2048', 
                    'requirement_id' => 'required',
                    'org_log_id' => 'required'
                 ]);

                $uploadedFiles=[];
     
                foreach($request->file('files') as $file){

                    
                    $filename = $file->getClientOriginalName();

                    $exists = RequirementFile::where('filename', $filename)
                                               ->where('requirement_id',$request->id_requirement)->exists();

                    if(!$exists){
                        $path = $file->store('uploads','public');
                        $uploadedFiles[] = RequirementFile::create([
                            'requirement_id' => $request->requirement_id,
                            'filename' =>  $filename,
                            'path' => $path,
                            'size' => $file->getSize(),
                            'org_log_id' => $request->org_log_id
                        ]);

                    }

                }

                return response()->json([
                    'message' => 'Files uploaded successfully',
                    'data' => $uploadedFiles
                ]);

            }

            return response()->json(['message' => 'No file uploaded'], 400);

    }

    public function storeFolderRequirement(Request $request){

        $validate = $request->validate([
            'foldername' =>  'required',
            'requirement_id' => 'required',
            'org_log_id' => 'required'
        ]);


        if( !RequirementFile::where('filename', $validate['foldername'])
                                ->where('requirement_id',$request->requirement_id)
                                ->exists()){

            $data = RequirementFile::create([

                    'requirement_id' => $request->requirement_id,
                    'filename' => $validate['foldername'],
                    'org_log_id' => $request->org_log_id
       
            ]);

            return response()->json([
                'message' => 'Successfully created',
                'data' => $data
            ]);
        }

        $response = [
            'isSuccess'=> false,
            'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'
        ];

        //$this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);

        return response()->json($response, 422);

    }

    public function searchFileRequirement(Request $request){

        try{
            
            $query = $request->input('query');
            $req_id = $request->input('requirement_id');

            $results = RequirementFile::where('requirement_id', $req_id)
            ->when($query, function ($q) use ($query) {
                return $q->where(function ($queryBuilder) use ($query) {
                    $queryBuilder->where('name', 'LIKE', "%{$query}%");                   
                });
            })->get();

            if($results->isEmpty()){
                $results = RequirementFolder::where('requirement_id', $req_id)
                                ->when($query, function ($q) use ($query) {
                                    return $q->where(function ($queryBuilder) use ($query) {
                                        $queryBuilder->where('name', 'LIKE', "%{$query}%");                   
                                    });
                                })->get();
            }

            $response = [
                'isSuccess' => true,
                'results' => $results
            ];

            // $this->logAPICalls('searchAccount', "",$request->all(), [$response]);
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



}
