<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RequirementFile;
use App\Models\Requirement;
use App\Models\ApiLog;
use App\Models\Account;
use App\Models\Program;
use App\Models\Event;
use App\Models\OrganizationalLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Throwable;  

class FileRequirementController extends Controller
{
    

    public function getAllfile(Request $request){

        try{
            $allfiles = [];
            $validated = $request->validate([
                'search' => ['nullable'],
                'page' => ['nullable']
            ]);
            
            $query = RequirementFile::where('folder_id',null)->orderBy('created_at', 'desc');
            
            // Apply search filter if provided
            if ($request->filled('search')) {
                $query->where('filename', 'LIKE', '%' . $validated['search'] . '%'); 
            }
            
            $perPage = $validated['page'] ?? 10; 
            $datas = $query->paginate($perPage);
            
            // Fetch organizational logs and prepare response data
            foreach ($datas as $data) {
                $org_log = OrganizationalLog::find($data->org_log_id);
                
                $allfiles[] = [
                    'id' => $data->id,
                    'requirement_id' => $data->requirement_id,
                    'filename' => $data->filename,
                    'path' => $data->path,
                    'size' => $data->size,
                    'folder_id' => $data->folder_id,
                    'org_log_id' => $data->org_log_id,
                    'org_log_name' => $org_log ? $org_log->name : null,
                    'org_log_acronym' => $org_log ? $org_log->acronym : null,
                    'college_entity_id' => $data->college_entity_id,
                    'status' => $data->status,
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at
                ];
            }
            
            // Prepare the response with pagination information
            $response = [
                'isSuccess' => true,
                'AllFiles' => $allfiles,
                'pagination' => [
                    'currentPage' => $datas->currentPage(),
                    'lastPage' => $datas->lastPage(),
                    'perPage' => $datas->perPage(),
                    'total' => $datas->total(),
                ],
            ];
            
            $this->logAPICalls('getAllfile', "", $request->all(), [$response]);
            return response()->json($response);
            
            // If the code execution reaches here, it indicates a failure condition,
            // possibly due to authorization, so return a 403 response.
            $response = [
                'isSuccess' => false,
                'message' => "Only admins can access this API."
            ];
            
            return response()->json($response, 403);
            
            

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Account.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createAccount', "", $request->all(), $response);
            return response()->json($response, 500);

        }
    }

    public function getFolder(Request $request){


       try{

            $validated = $request->validate([
                'folder_id' => ['required'],
                'search' => ['nullable', 'string'], 
            ]);
            
        
            $query = RequirementFile::where('folder_id', $validated['folder_id']);
            
            
            if ($request->filled('search')) {
                $query->where('filename', 'LIKE', '%' . $validated['search'] . '%'); // Search in the filename
            }
            
            $data = $query->get();
            
            $response = [
                'isSuccess' => true,
                'folder' => $data
            ];
            
            $this->logAPICalls('getFolder', "", $request->all(), [$response]);
            return response($response, 200);


       }catch(Throwable $e){

                $response = [
                    'isSuccess' => false,
                    'message' => "Please contact support.",
                    'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

                $this->logAPICalls('getFolder', "", $request->all(), [$response]);
                return response()->json($response, 500);
       }

    }

    public function getFileRequirement(Request $request){
        
        try{

            $validated = $request->validate([
                'search' => 'nullable' 
            ]);

            if(!empty($request->folder_id) && empty($request->org_id)){
                
                 // Start the query with a join
                $query = RequirementFile::join('organizational_logs as ol', 'requirement_files.org_log_id', '=', 'ol.id')
                ->where('requirement_files.folder_id', $request->folder_id);
                
               // Apply search if provided
                if (!empty($validated['search'])) {
                    $query->where('requirement_files.filename', 'LIKE', '%' . $validated['search'] . '%'); 
                }

                // Select the necessary fields, including the organizational log name
                $data = $query->select('requirement_files.*', 'ol.id as org_log_id', 'ol.name as org_name','ol.acronym as org_acronym')
                ->get();

                    // Format the response
                    $formattedData = $data->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'requirement_id' => $item->requirement_id,
                            'filename' => $item->filename,
                            'path' => $item->path,
                            'size' => $item->size,
                            'folder_id' => $item->folder_id,
                            'org_log_id' => [
                                'id' => $item->org_log_id,
                                'name' => $item->org_name,
                                'acronym' => $item->org_acronym
                            ],
                            'status' => $item->status,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                        ];
                    });
                    
                    $response = [
                        'isSuccess' => true,
                        'data' => $formattedData
                    ];
                    
                    $this->logAPICalls('getFileRequirement', "", $request->all(), [$response]);
                    return response()->json($response, 200);
                 
                
    
            }elseif(!empty($request->org_id) && empty($request->folder_id)){

                if($request->org_id == "2"){

                    $results = DB::table('requirement_files as fr')
                    ->join('organizational_logs as ol', 'fr.org_log_id', '=', 'ol.id')
                    ->where('ol.org_id', 2)
                    ->select('fr.*', 'ol.id as org_id', 'ol.name as org_name') 
                    ->get();
            
                }else{
                    $results = DB::table('requirement_files as fr')
                    ->join('organizational_logs as ol', 'fr.org_log_id', '=', 'ol.id')
                    ->where('ol.org_id', '!=', 2) 
                    ->select('fr.*', 'ol.id as org_id', 'ol.name as org_name')
                    ->get();
                }

                  // Transform the results to the desired output format
                  $formattedResults = $results->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'requirement_id' => $item->requirement_id,
                        'filename' => $item->filename,
                        'path' => $item->path,
                        'size' => $item->size,
                        'folder_id' => $item->folder_id,
                        'org_log_id' => [
                            [
                                'id' => $item->org_id,
                                'name' => $item->org_name,
                            ]
                        ],
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                });
                
                $response = [
                    'isSuccess' => true,
                    'data' =>  $formattedResults
                ];
                return response()->json($response);

            }else{
            
                // Start the query with a join
                $query = RequirementFile::join('organizational_logs as ol', 'requirement_files.org_log_id', '=', 'ol.id')
                    ->where('requirement_files.requirement_id',$request->requirement_id);
                
                // Apply search if provided
                if (!empty($validated['search'])) {
                    $query->where('requirement_files.filename', 'LIKE', '%' . $validated['search'] . '%'); 
                }

                // Select the necessary fields, including the organizational log name
                $data = $query->select('requirement_files.*', 'ol.id as org_log_id', 'ol.name as org_name')
                    ->orderBy('requirement_files.created_at', 'desc')
                    ->get();

                // Format the response
                $formattedData = $data->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'requirement_id' => $item->requirement_id,
                        'filename' => $item->filename,
                        'path' => $item->path,
                        'size' => $item->size,
                        'folder_id' => $item->folder_id,
                        'org_log_id' => [
                            'id' => $item->org_log_id,
                            'name' => $item->org_name,
                        ],
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                });

                // Prepare the response
                $response = [
                    'isSuccess' => true,
                    'data' => $formattedData,
                ];
                $this->logAPICalls('getFileRequirement', "", $request->all(), [$response]);
                return response()->json($response);
            }
            
        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
       
    }

    public function getEditFile(Request $request){

        try{

            $validated = $request->validate([
                'file_id' => 'required|exists:requirement_files,id'
            ]);

            $data = RequirementFile::where('id','file_id')->first();

            $response = [
                'isSuccess' => true,
                'File' => $data
            ];

            $this->logAPICalls('getEditFile', "", $request->all(), [$response]);
            return response($respone,200);

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
        ];

            $this->logAPICalls('getEditFile', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }

    }


    public function updateFile(Request $request){

        try{

            $validated = $request->validate([
                'file_id' => 'required|exists:requirement_files,id',
                'name' => 'required|min:3|max:20'
            ]);

            $data = RequirementFile::find( $validated['file_id']);

            if(!RequirementFile::where('path',$data->path)
                                ->where('filename',$validated['name'])
                                ->exists()){
                    
                    $data->update([
                     'filename' => $validated['name']
                    ]);

                    $response = [
                        'isSuccess' => true,
                        'message' => "Updated successfully!",
                        'File' => $data
                    ];
        
                    $this->logAPICalls('getEditFile', "", $request->all(), [$response]);
                    return response($response,200);
            }

            $response = [
                'isSuccess' => false,
                'message' => "TThe file/folder name already exists. Please check your input!",
              
            ];

            $this->logAPICalls('getEditFile', "", $request->all(), [$response]);
            return response($response,500);

           

           

        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
        ];

            $this->logAPICalls('getEditFile', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    }
    // METHOD THAT CAN STORE FILES INSIDE REQUIREMENTS //
    // DONE //
    public function storeFileRequirement(Request $request){

           try{


                if ($request->hasFile('files')) {

                    $validated = $request->validate([
                    'files.*' => 'required|file', 
                    'req_id' => 'required|exists:requirements,id',
                    ]);

                    
                $requirement = Requirement::find($validated['req_id']);
                $requirement_name = $requirement->name;
                $event = Event::find($requirement->event_id);
                $event_name = $event->name;
                $organization = OrganizationalLog::find($event->org_log_id);
                $organization_name = $organization->name;
                $org_id =$organization->org_id;

                if($org_id == 3){
                    $program = Program::where('program_entity_id',$organization->id)->first();
                    $college = OrganizationalLog::find($program->college_entity_id);
                    $college_name =  $college->name;
                    $folder = $college_name.'/'.$organization_name.'/'.$event_name.'/'. $requirement_name;
                }else{
                    $folder = $organization_name.'/'.$event_name.'/'. $requirement_name;
                }
               

               $uploadedFiles=[];

                $data = Requirement::where('id',$validated['req_id'])->get();
                $program = Program::where('program_entity_id',$data->first()->org_log_id)->get();
                $college_id = !$program->isEmpty() ?   $program->first()->college_entity_id : "";
        
                foreach($request->file('files') as $file){

                    $filename = $file->getClientOriginalName();


                    $exists = RequirementFile::where('filename', $filename)
                                                ->where('requirement_id',$validated['req_id'])->exists();

                    if(!$exists){
                       
                        $path = $file->storeAs($folder, $filename, 'public');

                        if(empty($request->folder_id)){
                            $uploadedFiles[] = RequirementFile::create([
                                'requirement_id' => $validated['req_id'],
                                'filename' =>  $filename,
                                'path' => $path,
                                'size' => $file->getSize(),
                                'org_log_id' => $data->first()->org_log_id,
                                'college_entity_id' => $college_id
                            ]);

                        }else{
                            $uploadedFiles[] = RequirementFile::create([
                                'requirement_id' => "",
                                'filename' =>  $filename,
                                'path' => $path,
                                'size' => $file->getSize(),
                                'org_log_id' => $data->org_log_id,
                                'folder_id' => $request->id,
                                'college_entity_id' => $college_id
                            ]);
         
                        }

                      
                    }

                }

                $data->first()->update([
                    'upload_status' => "completed"
                ]);
                return response()->json([
                    'message' => 'Files uploaded successfully',
                    'data' => $uploadedFiles
                ]);

           }
            
            $this->logAPICalls('storeFileRequirement', "", $request->all(), [$response]);
            return response()->json(['message' => 'No file uploaded'], 400);

           }catch(Throwable $e){

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
                $program = Program::where('program_entity_id',$data->first()->org_log_id)->get();
                $college_id = !$program->isEmpty() ?   $program->first()->college_entity_id : "";
    
                if($data){
    
                    if( !RequirementFile::where('filename', $validate['foldername'])
                                ->where('folder_id',$request->folder_id)
                                ->exists()){
    
                            $data = RequirementFile::create([
    
                            'requirement_id' => "",
                            'filename' => $validate['foldername'],
                            'org_log_id' => $data->first()->org_log_id,
                            'college_entity_id' => $college_id,
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
                $program = Program::where('program_entity_id',$data->first()->org_log_id)->get();
                $college_id = !$program->isEmpty() ?   $program->first()->college_entity_id : "";
    
                if( !RequirementFile::where('filename', $validate['foldername'])
                                        ->where('requirement_id',$request->requirement_id)
                                        ->exists()){
    
                    $data = RequirementFile::create([
    
                            'requirement_id' => $request->requirement_id,
                            'filename' => $validate['foldername'],
                            'org_log_id' =>  $org_log->first()->org_log_id,
                            'college_entity_id' => $college_id
            
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
    
        }catch(Throwable $e){

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
    
    public function storeDMO_files(Request $request){
      
        try{

            if ($request->hasFile('files')) {

                $validated = $request->validate([
                  'files.*' => 'required|file'
                ]);

            $uploadedFiles=[];
            $exists_file=[];

            // $data = Requirement::where('id',$validated['req_id'])->get();
            // $program = Program::where('program_entity_id',$data->first()->org_log_id)->get();
            // $college_id = !$program->isEmpty() ?   $program->first()->college_entity_id : "";
            
            foreach($request->file('files') as $file){

                $filename = $file->getClientOriginalName();

                if(empty($request->folder_id)){
                    
                    $exists = RequirementFile::where('filename', $filename)
                                             ->where('folder_id',null)->exists();
                }else{

                    $exists = RequirementFile::where('filename', $filename)
                                             ->where('folder_id',$request->folder_id)->exists();
                }


                if(!$exists){
            
                  
                    if(empty($request->folder_id)){
                       
                        $path = $file->storeAs('uploads',$filename,'public');
                        $uploadedFiles[] = RequirementFile::create([
                            'requirement_id' => "DMO File",
                            'filename' =>  $filename,
                            'path' => $path,
                            'size' => $file->getSize(),
                            'org_log_id' => "",
                            'college_entity_id' => ""
                        ]);

                    }else{
                        $data = RequirementFile::find($request->folder_id);
                        $path = $file->storeAs($data->path,$filename,'public');
                        $uploadedFiles[] = RequirementFile::create([
                            'requirement_id' => "",
                            'filename' =>  $filename,
                            'path' => $path,
                            'size' => $file->getSize(),
                            'org_log_id' => "",
                            'folder_id' => $request->folder_id,
                            'college_entity_id' => ""
                        ]);
     
                    }
                    return response()->json([
                        'message' => 'Uploaded Successfully!',
                        'upload_files' => $uploadedFiles,
                        'exists_file' =>$exists_file
                    ],200);
                }else{
                    $exists_file[] = $filename;
                }
               
            }

            return response()->json([
                'isSuccess' => false,
                'message' => 'Files already exist. Please check the file you want to upload.',
                'upload_files' => $uploadedFiles,
                'exists_file' =>$exists_file
            ],500);

        }
        
        $this->logAPICalls('storeDMO_files', "", $request->all(), [$response]);
        return response()->json(['message' => 'No file uploaded'], 400);

       }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('storeDMO_files', "", $request->all(), [$response]);
            return response()->json($response, 500);

       }


    }

    public function createDMO_folder(Request $request){

        try{

            $validate = $request->validate([
                'foldername' =>  'required|min:3|max:20',
            ]);
    
            if(!empty($request->folder_id)){
    
                // CREATE FOLDER UNDER FOLDER" //
    
                $data = RequirementFile::where('id',$request->folder_id)->get();
    
                if($data){
    
                    if( !RequirementFile::where('filename', $validate['foldername'])
                                ->where('folder_id',$request->folder_id)
                                ->exists()){
                            
                             if (!Storage::disk('public')->exists($data->first()->filename.'/'.$validate['foldername'])) {
                              $path =  Storage::disk('public')->makeDirectory($data->first()->filename.'/'.$validate['foldername']);
                             }
    
                            $data = RequirementFile::create([
    
                            'requirement_id' => "DMO File",
                            'filename' => $validate['foldername'],
                            'org_log_id' => $data->first()->org_log_id,
                            'college_entity_id' => "",
                            'path' => $data->first()->filename.'/'.$validate['foldername'],
                            'folder_id' => $request->folder_id
    
                            ]);

            
                            $response = [
                                'isSuccess' => true,
                                'message' => 'Successfully created',
                                'folder' => $data
                            ];
                            $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                            return response()->json($response);
                    }
    
                 
                    $response = [
                        'isSuccess'=> false,
                        'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'
                    ];
                    return response()->json($response,500);
                    $this->logAPICalls('createDMO_folder', "", $request->all(), [$response]);
    
                }else{
                    $response = [
                        'isSuccess' => false,
                        'message' => 'Successfully created',
                        'data' => $data
                    ];
                    $this->logAPICalls('createDMO_folder', "", $request->all(), [$response]);
                    return response()->json($response,500);
                }
    
            }else{
    
               // CREATE FOLDER UNDER REQUIREMENT //
    
                if( !RequirementFile::where('filename', $validate['foldername'])->exists()){
                    
                    if (!Storage::disk('public')->exists($validate['foldername'])) {
                        Storage::disk('public')->makeDirectory($validate['foldername']);
                    }
                    $data = RequirementFile::create([
    
                            'requirement_id' => "DMO File",
                            'filename' => $validate['foldername'],
                            'org_log_id' => "",
                            'path' => $validate['foldername'],
                            'college_entity_id' => ""
            
                    ]);
                    
                    
                  

                    return response()->json([
                        'message' => 'Successfully created',
                        'data' => $data
                    ]);
                }
    
                // $this->logAPICalls('createDMO_folder', "", $request->all(), [$response]);
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                return response()->json($response, 500);
    
            }
    
        }catch(Exception $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('createDMO_folder', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    
    }

    public function deleteFile(Request $request){
        try{

            $validated = $request->validate([
                'file_id' => 'required|exists:requirement_files,id'
            ]);
    
            $data = RequirementFile::find($validated['file_id']);
            $data->update(
                [
                    'status' => 'I'
                ]
            );
    
            $response = [
                'isSuccess' => true,
                'message' => "Deleted successfully!",
                'data' => $data
            ];
    
            $this->logAPICalls('deleteFile', "", $request->all(), [$response]);
            return response($response,200);

        }catch(Exception $e){
            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('deleteFile', "", $request->all(), [$response]);
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

    // public function makefolder($account_id){
    //     $account = Account::find($account_id);
    //     return $account->org_log_id;
    //     // $folderPath = 'my_new_public_folder';

    //     // // Create the directory
    //     // if (Storage::disk('public')->makeDirectory($folderPath)) {
    //     //     return response()->json(['message' => 'Folder created successfully!'], 200);
    //     // }
    // }
}
