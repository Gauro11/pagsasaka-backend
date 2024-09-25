<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RequirementFile;
use App\Models\Requirement;
use App\Models\RequirementFolder;
use ZipArchive;

class FileRequirementController extends Controller
{
    

    public function getFileRequirement(Request $request){

        try{


            if(!empty($request->folder_id)){

                $validated = $request->validate([
                    'search' => 'nullable|string|max:255' 
                ]);
                
                $query = RequirementFile::where('folder_id',$request->folder_id);
                $data = $query->get();
                
                if($query->isNotEmpty()){
                    if (!empty($validated['search'])) {
                        $query->where('filename', 'LIKE', '%' . $validated['search'] . '%'); 
                    }
                    
                    $data = $query->get();
                    
                    $response = [
                        'isSuccess' => true,
                        'data' => $data
                    ];
                    
                    $this->logAPICalls('getFileRequirement', "", $request->all(), [$response]);
                    return response()->json($response, 200);
                 }
                
    
            }else{
                $validate = $request->validate([
                    'requirement_id' => 'required',
                    'search' => 'nullable'
                ]);
                
                $query = RequirementFile::where('requirement_id', $validate['requirement_id']);
                
              
                if (!empty($validate['search'])) {
                    $query->where('filename', 'LIKE', '%' . $validate['search'] . '%'); 
                }
        
                $data = $query->orderBy('created_at', 'desc')->get();
                
                $response = [
                    'isSuccess' => true,
                    'data' => $data
                ];
                $this->logAPICalls('getFileRequirement', "", $request->all(), [$response]);
                return response()->json($response);
            }
            
        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
       
    }

    // METHOD THAT CAN STORE FILES INSIDE REQUIREMENTS //

    public function storeFileRequirement(Request $request){

           try{

                if ($request->hasFile('files')) {

                    $validated = $request->validate([
                    'files.*' => 'required|file', 
                    'req_id' => 'required|exists:requirements,id',
                    ]);

                $uploadedFiles=[];

                $data = Requirement::where('id',$validated['req_id'])->get();
        
                foreach($request->file('files') as $file){

                    
                    $filename = $file->getClientOriginalName();

                    $exists = RequirementFile::where('filename', $filename)
                                                ->where('requirement_id',$validated['req_id'])->exists();

                    if(!$exists){
                
                        $path = $file->store('uploads','public');

                        if(emprty($request->folder_id)){
                            $uploadedFiles[] = RequirementFile::create([
                                'requirement_id' => $validated['req_id'],
                                'filename' =>  $filename,
                                'path' => $path,
                                'size' => $file->getSize(),
                                'org_log_id' => $data->first()->org_log_id
                            ]);
                        }else{
                            $uploadedFiles[] = RequirementFile::create([
                                'requirement_id' => "",
                                'filename' =>  $filename,
                                'path' => $path,
                                'size' => $file->getSize(),
                                'org_log_id' => $data->org_log_id,
                                'folder_id' => $request->id
                            ]);
         
                        }
                    }

                }

                return response()->json([
                    'message' => 'Files uploaded successfully',
                    'data' => $uploadedFiles
                ]);

            }
            
            $this->logAPICalls('storeFileRequirement', "", $request->all(), [$response]);
            return response()->json(['message' => 'No file uploaded'], 400);

           }catch(Exception $e){

                $response = [
                    'isSuccess' => false,
                    'message' => "Please contact support.",
                    'error' => 'An unexpected error occurred: ' . $e->getMessage()
                ];

                $this->logAPICalls('storeFileRequirement', "", $request->all(), [$response]);
                return response()->json($response, 500);

           }

    }

    public function storeFolderRequirement(Request $request){

        try{

            $validate = $request->validate([
                'foldername' =>  'required',
            ]);
    
            if(!empty($request->folder_id)){
    
                // CREATE FOLDER UNDER FOLDER" //
    
                $data = RequirementFile::where('id',$request->folder_id)->get();
    
                if($data){
    
                    if( !RequirementFile::where('filename', $validate['foldername'])
                                ->where('folder_id',$request->folder_id)
                                ->exists()){
    
                            $data = RequirementFile::create([
    
                            'requirement_id' => "",
                            'filename' => $validate['foldername'],
                            'org_log_id' => $data->first()->org_log_id,
                            'folder_id' => $request->folder_id
    
                            ]);
                            $response = [
                                'isSuccess' => true,
                                'message' => 'Successfully created',
                                'data' => $data
                            ];
                            $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                            return response()->json($response);
                    }
    
                    $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                    $response = [
                        'isSuccess'=> false,
                        'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'
                    ];
        
    
                }else{
                    $response = [
                        'isSuccess' => false,
                        'message' => 'Successfully created',
                        'data' => $data
                    ];
                    $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                    return response()->json($response,500);
                }
    
            }else{
    
               // CREATE FOLDER UNDER REQUIREMENT //
    
               $validated = $request->validate([
    
                    'requirement_id' => 'required|exists:requirements,id',
    
                ]);
    
                $org_log = Requirement::where('id',$validated['requirement_id'])->get();
    
                if( !RequirementFile::where('filename', $validate['foldername'])
                                        ->where('requirement_id',$request->requirement_id)
                                        ->exists()){
    
                    $data = RequirementFile::create([
    
                            'requirement_id' => $request->requirement_id,
                            'filename' => $validate['foldername'],
                            'org_log_id' =>  $org_log->first()->org_log_id
            
                    ]);
    
                    return response()->json([
                        'message' => 'Successfully created',
                        'data' => $data
                    ]);
                }
    
                $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                return response()->json($response, 422);
    
            }
    
        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
        
    
    }

    public function downloadFileRequirement(Request $request){

        try{

            $validated = $request->validate([
                'requirement_id' => 'required'
            ]);
    
            $data = RequirementFile::find($validated['requirement_id']);
    
            if($data){
    
                if(!empty($data->folder_id)){
    
                     // DOWNLOAD MULTPLE FILES //
    
                     $folder_id = $data->folder_id;
                     $folder_name = $data->filename;
                     $files =[];
    
                     $files_folder = RequirementFile::where('folder_id',$folder_id)->get();
    
                     foreach($files_folder as $file){
                        $files[] = $file->path;
                     }
    
                    $zip = new ZipArchive();
                    $zipFileName =  $folder_name .".zip";
                    $zipFilePath = storage_path("app/{$zipFileName}");
    
                    if ($zip->open($zipFilePath, ZipArchive::CREATE) !== TRUE) {
                        return response()->json(['error' => 'Could not create zip file'], 500);
                    }
            
                    foreach ($files as $file) {
                        $filePath = storage_path("app/public/{$file}");
                        if (file_exists($filePath)) {
                            $zip->addFile($filePath, $file);
                        }
                    }
            
                    $zip->close();
                    
                    $respone = [
                        'isSuccess' =>true,
                        'message' => "Download successfully!"
                    ];
    
                    $this->logAPICalls('downloadFileRequirement', "", $files, [$response]);
                    return response()->download($zipFilePath)->deleteFileAfterSend(true);
    
                  
    
                }else{
    
                   // DOWNLOAD ONE FILE //
    
                    $filepath = $data->path;
                    $path = storage_path("app/public/{$filepath}");
    
                    if (!file_exists($path)) {
                        $respone = [
                            'isSuccess' =>false,
                            'message' => "File not found"
                        ];
        
                        $this->logAPICalls('downloadFileRequirement', "", $files, [$response]);
                        return response()->json($respone , 404);
                    }else{
                        $respone = [
                            'isSuccess' =>true,
                            'message' => "Download successfully!"
                        ];
        
                        $this->logAPICalls('downloadFileRequirement', "", $files, [$response]);
                        return response()->download($path);
    
                    }
                }
                
                   $response = [
    
                        'isSuccess' => false,
                        'message' => "File ID does not exist."
    
                    ];
    
            }
            $this->logAPICalls('downloadFileRequirement', "", $request->all(), [$response]);
            return response()->json( $response, 400);

        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('downloadFileRequirement', "", $request->all(), [$response]);
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
