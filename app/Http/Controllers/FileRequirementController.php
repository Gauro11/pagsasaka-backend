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
use App\Models\HistoryDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use ZipArchive;
use Throwable; 

use Carbon\Carbon;  

class FileRequirementController extends Controller
{
    
public function getAllfile(Request $request)
{
    try {
        // Validate the request body parameters
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
        ]);

        // Set default items per page
        $perPage = 10;

        // Initialize the query
        $query = RequirementFile::where('folder_id', null)
                                ->where('is_archived',0)->orderBy('created_at', 'desc');

        // Apply search filter if provided
        if ($request->filled('search')) {
            $query->where('filename', 'LIKE', '%' . $validated['search'] . '%');
        }

        // Check total items before pagination
        $totalItems = $query->count();

        // Get the page parameter from the request body, default to 1 if not provided
        $currentPage = $validated['page'] ?? 1;

        // Paginate the results
        $datas = $query->paginate($perPage, ['*'], 'page', $currentPage);

        // Prepare the response data
        $allfiles = [];

        foreach ($datas as $data) {
            $org_log = OrganizationalLog::find($data->org_log_id);

            // Format the date and time
            $dateTime = Carbon::parse($data->updated_at)
                ->setTimezone('Asia/Manila')
                ->format('F j Y g:i A');

            // Assuming you want to check the filename (e.g., stored in $data->filename)
            $filename = $data->filename; // Example field storing the filename
            $type = null;
            // Check if the filename contains an extension (i.e., it has a dot)
            if (strpos($filename, '.') !== false) {
                // Filename has an extension (it’s a file)
                $type = "file";
            } else {
                // Filename has no extension (it’s not a typical file)
                $type = "folder";
            }

            $allfiles[] = [
                'id' => $data->id,
                'requirement_id' => $data->requirement_id,
                'filename' => $data->filename,
                'path' => $data->path,
                'folder_id' => $data->folder_id,
                'org_log_id' => $data->org_log_id, // ID of the person who created the folder or file
                'org_log_name' => $org_log ? $org_log->name : null, 
                'org_log_acronym' => $org_log ? $org_log->acronym : null,
                'status' => $data->status,
                'updated_at' => $dateTime,
                'type' => $type
            ];
        }

        // Prepare the response with pagination information
        $response = [
            'isSuccess' => true,
            'all_files' => [
                'data' =>  $allfiles,
                'current_page' => $datas->currentPage(),
                'last_page' => $datas->lastPage(),
                'per_page' => $datas->perPage(),
                'total' => $datas->total()

            ]
        ];

        // Log the API call
        $this->logAPICalls('getAllfile', $datas, $request->all(), [$response]);
        
        return response()->json($response);

    } catch (Throwable $e) {
        // Handle exception and return error response
        $response = [
            'isSuccess' => false,
            'message' => 'Failed to fetch the files.',
            'error' => $e->getMessage()
        ];

        // Log the error
        $this->logAPICalls('getAllfile', "", $request->all(), $response);

        return response()->json($response, 500);
    }
}


    public function getFilesInsideFolder(Request $request){

       try{

            $validated = $request->validate([
                'folder_id' => ['required'],
                'search' => ['nullable', 'string'], 
            ]);
            
            $folder =  RequirementFile::find($validated['folder_id']);
            $requirement_info = [];

            if($folder->requirement_id != 'DMO File'){

              $requirement = Requirement::find($folder->requirement_id);
              $event = Event::find($requirement->event_id);
              $requirement_info = [
                'event_id' => $requirement->event_id,
                'event_name' =>  $event->name,
                'requirement_id' =>  $requirement->id,
                'requirement_name' =>  $requirement->name

              ];
            
            }

            // Query the 'requirement_files' table base on their folder.
           $query = RequirementFile::where('folder_id', $validated['folder_id'])
                                      ->where('is_archived',0);
            
            // Search in the filename
            if ($request->filled('search')) {
                $query->where('filename', 'LIKE', '%' . $validated['search'] . '%'); 
            }
            
            $datas = $query->orderBy('created_at', 'desc')->get();

       

            $allfiles = [];

            foreach ($datas as $data) {

                $org_log = OrganizationalLog::find($data->org_log_id);

                // Format the date and time
                $dateTime = Carbon::parse($data->updated_at)->setTimezone('Asia/Manila')->format('F j Y g:i A');

                $sizeInBytes = $data->size;

                if($sizeInBytes == 0 || $sizeInBytes == null){
                    $size = 0;
                }else if ($sizeInBytes >= 1073741824) { // 1 GB = 1024^3 bytes
                    $size = number_format($sizeInBytes / 1073741824, 2) . ' GB';
                } elseif ($sizeInBytes >= 1048576) { // 1 MB = 1024^2 bytes
                    $size = number_format($sizeInBytes / 1048576, 2) . ' MB';
                } elseif ($sizeInBytes >= 1024) { // 1 KB = 1024 bytes
                    $size = number_format($sizeInBytes / 1024, 2) . ' KB';
                } else {
                    $size = $sizeInBytes . ' bytes';
                }

                $allfiles[] = [
                    'id' => $data->id,
                    'requirement_id' => $data->requirement_id,
                    'filename' => $data->filename,
                    'path' => $data->path,
                    "size" =>  $size,
                    'org_log_id' => $data->org_log_id, // ID of the person who created the folder or file
                    'org_log_name' => $org_log ? $org_log->name : null, 
                    'org_log_acronym' => $org_log ? $org_log->acronym : null,
                ];
            }
            
            if($folder->requirement_id != 'DMO File'){
                $response = [
                    'isSuccess' => true,
                    'folder' =>  [
                        'data' => $allfiles,
                        'requirement_info' =>  $requirement_info,
                        'type' => 'requirement'
                    ]
                ];
            }else{
                $response = [
                    'isSuccess' => true,
                    'folder' =>  [
                        'data' => $allfiles,
                        'type' => 'dmo files'
                    ]
                ];
            }
           
            
            $this->logAPICalls('getFilesInsideFolder', "", $request->all(), [$response]);
            return response($response, 200);


       }catch(Throwable $e){

                $response = [
                    'isSuccess' => false,
                    'message' => "Please contact support.",
                    'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

                $this->logAPICalls('getFilesInsideFolder', "", $request->all(), [$response]);
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
            return response()->json($response,200);

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


    public function updateFileOrFolder(Request $request){

        try{
            
            $validated = $request->validate([
                'file_id' => 'required|exists:requirement_files,id',
                'name' => 'required',
                'account_id' => 'required|exists:accounts,id'
            ]);
        
          // Retrieve the file record based on the 'file_id' from the 'requirement_files' table
           $data = RequirementFile::where('id',$validated['file_id'])->first();
         
          $type = null; // To store type of file (file or folder)
          $parts = explode('/', $data->path); // Split the file path into parts using '/' as separator
          $filename = $validated['name'];

                // Check if the original filename contains an extension (it's a file)
                if (strpos($data->filename, '.') !== false) {
                    // Filename has an extension (it’s a file)
                    $file_extension = pathinfo( $parts[count($parts)-1], PATHINFO_EXTENSION);
                    $filename .= '.' . $file_extension; // Add the extension back
                    $type = "file";
                } else{
                    $type = "folder";
                }

                // Replace the last part of the path with the new filename
                $parts[count($parts)-1]=$filename;

                // Construct the new folder path from the parts
                $newFolderPath = (count($parts) > 1) ? implode('/', $parts) : $filename;
     
                // Check if the new folder/file already exists in the database
            if(!RequirementFile::where('path',$newFolderPath)
                                ->where('filename',$validated['name'])
                                ->exists()){
                               
                                $currentDateTime = Carbon::now();

                                
                                     // Check if the file exists in storage and move it to the new path
                                if (Storage::disk('public')->exists($data->path)) {
                                
                                  Storage::disk("public")->move($data->path,$newFolderPath);
                                 
                                }

                                   
                     // If it's a folder, update the paths of any child files/folders
                    if($type == "folder")  {
                        $this->updateChildPaths($data->path, $newFolderPath, $validated['file_id']);
                       
                    }            

                    $data->update([
                     'filename' => $filename,
                     'path' => $newFolderPath
                    ]);
                    
                    // Retrieve the user who initiated the change
                    $user = Account::where('id',$validated['account_id'])->first();

                    // The records of the user who renamed the file will be saved here. //
                    if($user){
                        HistoryDocument::create([
                            'user_id' => $user->id,
                            'action' => "has renamed the folder/file",
                            'file_id' => $validated['file_id']
                        ]);
                     }

                    $response = [
                        'isSuccess' => true,
                        'message' => "Updated successfully!"
                    ];
                    
                    $this->logAPICalls('updateFileOrFolder', "", $request->all(), [$response]);
                    return response($response,200);
            }

          

            $response = [
                'isSuccess' => false,
                'message' => "TThe file/folder name already exists. Please check your input!",
              
            ];

            $this->logAPICalls('updateFileOrFolder', "", $request->all(), [$response]);
            return response($response,500);

        
        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
        ];

            $this->logAPICalls('updateFileOrFolder', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }
    }

    // API for Changing Confirmation under on UI DMO Files  //
    public function confirmationForEditDelete(Request $request){
        
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'password' => 'required'
        ]);

          $user = Account::find($validated['account_id']);
         

        if (Hash::check($request->password, $user->password)) {
            $isSuccess =true;
            $message = "Password authentication successful.";
            $code = 200;
            if($user->status == 'I' || $user->status == 'D'){
                $isSuccess=false;
                $message = "Account is inactive or Deleted.";
                $code = 500;
            }

            $response =[
                'isSuccess' => $isSuccess,
                'message' => $message,
            ];

            return response()->json($response, $code);

        }

        $response = [
            'isSuccess' => false,
            'message' => "Invalid password!"
        ];

        return response()->json($response, 500);

    }

    private function updateChildPaths($oldPath, $newPath,$id) {

        $children = RequirementFile::where('path', 'like', $oldPath . '/%')
                                    ->where('folder_id',$id)->get();

        foreach ($children as $child) {
           
            $newChildPath = str_replace($oldPath, $newPath, $child->path);
            
            // Update the child's record
           $requirement = RequirementFile::where('id',$child->id)->first();
            $requirement->update([
                'path' => $newChildPath
            ]);
        }

    }

    // METHOD THAT CAN STORE FILES INSIDE REQUIREMENTS //
    // DONE //
    public function createFileRequirement(Request $request){

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

                $response = [
                    'isSuccess' => true,
                    'message' => 'Files uploaded successfully',
                    'data' => $uploadedFiles
                ];

                return response()->json($response,200);

           }
            $response= [
                'isSuccess' =>true,
                'message' => 'No file uploaded'

            ];
            $this->logAPICalls('createFileRequirement', "", $request->all(), [$response]);
            return response()->json($response, 500);

           }catch(Throwable $e){

                $response = [
                    'isSuccess' => false,
                    'message' => "Please contact support.",
                    'error' => 'An unexpected error occurred: ' . $e->getMessage()
                ];

                $this->logAPICalls('createFileRequirement', "", $request->all(), [$response]);
                return response()->json($response, 500);

           }

    }

    public function createFolderRequirement(Request $request){

        try{

            $validated = $request->validate([
                'foldername' =>  'required',
            ]);
            
         
            if(!empty($request->folder_id)){
    
                // CREATE FOLDER UNDER FOLDER" //
    
                $data = RequirementFile::where('id',$request->folder_id)->get();
                $program = Program::where('program_entity_id',$data->first()->org_log_id)->get();
                $college_id = !$program->isEmpty() ?   $program->first()->college_entity_id : "";
    
                if($data){
    
                    if( !RequirementFile::where('filename', $validated['foldername'])
                                ->where('folder_id',$request->folder_id)
                                ->exists()){
    
                            $data = RequirementFile::create([
    
                            'requirement_id' => "",
                            'filename' => $validated['foldername'],
                            'org_log_id' => $data->first()->org_log_id,
                            'college_entity_id' => $college_id,
                            'folder_id' => $request->folder_id
    
                            ]);
                            $response = [
                                'isSuccess' => true,
                                'message' => 'Successfully created',
                                'data' => $data
                            ];
                            $this->logAPICalls('createFolderRequirement', "", $request->all(), [$response]);
                            return response()->json($response,200);
                    }
                    
                    $response = [
                        'isSuccess'=> false,
                        'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'
                    ];
                    $this->logAPICalls('createFolderRequirement', "", $request->all(), [$response]);

        
    
                }else{
                    $response = [
                        'isSuccess' => false,
                        'message' => 'Successfully created',
                        'data' => $data
                    ];
                    $this->logAPICalls('createFolderRequirement', "", $request->all(), [$response]);
                    return response()->json($response,500);
                }
    
            }else{
    
               // CREATE FOLDER UNDER REQUIREMENT //
    
               $validated = $request->validate([
    
                    'requirement_id' => 'required|exists:requirements,id',
    
                ]);
    
                $requirement = Requirement::find($validated['requirement_id']);
                $program = Program::where('program_entity_id',$requirement->org_log_id)->first();
                $college_id = !empty($program) ?   $program->college_entity_id : "";
    
                if( !RequirementFile::where('filename', $validated['foldername'])
                                        ->where('requirement_id', $validated['requirement_id'])
                                        ->exists()){
    
                    $data = RequirementFile::create([
    
                            'requirement_id' => $request->requirement_id,
                            'filename' => $validated['foldername'],
                            'org_log_id' =>  $requirement->org_log_id,
                            'college_entity_id' => $college_id
            
                    ]);
                    
                    $response =[
                        'isSuccess' => true,
                        'message' => 'Successfully created',
                        'data' => $data

                    ];
                    return response()->json($response,200);
                }
                
                $response = [
                    'isSuccess' => false,
                    'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'

                ];
                $this->logAPICalls('createFolderRequirement', "", $request->all(), [$response]);
                return response()->json($response, 500);
    
            }
    
        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('createFolderRequirement', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
        
    }

    public function downloadFileRequirement(Request $request){

            try {

                $validated  = $request->validate([
                    'file_id' => 'required',
                    'account_id' => 'required|exists:accounts,id'
                ]);


                $filesToZip = [];
                $file_name =[];

                // Retrieve the file path for the given file ID.

                foreach($validated['file_id'] as $file_id){
                    $data = RequirementFile::find($file_id);
                    $filesToZip[] = $data->path;
                }

                
                
                // Name of the zip file
                $zipFileName = 'download.zip';
                $zipFilePath = storage_path('app/' . $zipFileName); // Path for the zip file
            
                // Create a new ZipArchive instance
                $zip = new ZipArchive;
            
                if ($zip->open($zipFilePath, ZipArchive::CREATE)) {

                    // Loop through each file to be added to the ZIP archive
                    foreach ($filesToZip as $filePath) {

                        // Generate the absolute path of the file in the public directory
                        $fullFilePath = storage_path('app/public/'.$filePath);
                        $fullFilePath = str_replace('\\', '/', $fullFilePath);


                        // Check if the file exists before trying to add it
                        if (file_exists($fullFilePath)) {

                            // Add the file to the ZIP archive\

                           if (File::isDirectory($fullFilePath)) {

                            // Kung folder, i-zip ang lahat ng files sa loob

                            $this->addFolderToZip($fullFilePath, $zip);

                        } else {
                            // File only
                            $zip->addFile($fullFilePath, basename($fullFilePath));
                        }
                        } else {

                            // If the file doesn't exist, return an error message
                            return response()->json(['error' => "File does not exist: $fullFilePath"], 404);
                        }
                    }
            

                    $zip->close();
            
                    // Check if the zip file was created successfully

                    if (file_exists($zipFilePath)) {

                        //  //
                        $user = Account::where('id',$validated['account_id'])->first();

                        if($user){
                            foreach($validated['file_id'] as $file_id){
                                $exists = RequirementFile::where('id',$file_id)->exists();

                                if($exists){
                                    HistoryDocument::create([
                                        'user_id' => $user->id,
                                        'action' => "has downloaded the files.",
                                        'file_id' => $file_id
                                    ]);
                                }

                            }
                            
                        }

                        // Return the ZIP file as a downloadable response
                        return response()->download($zipFilePath)->deleteFileAfterSend(true);

                    } else {

                        return response()->json(['error' => 'ZIP file creation failed.'], 500);
                    }

                } else {
                    return response()->json(['error' => 'Failed to create the ZIP file.'], 500);
                }

            } catch (Throwable $e) {
                
                     $response = [
                        'isSuccess' => false,
                        'message' => "Please contact support.",
                        'error' => 'An unexpected error occurred: ' . $e->getMessage()
                     ];

                    $this->logAPICalls('downloadFileRequirement', "", $request->all(), [$response]);
                    return response()->json($response, 500);

            }
       

    }

    private function addFolderToZip($folder, ZipArchive $zip, $zipFolder = null)
    {
        $folderName = $zipFolder ?? basename($folder);

        // I-iterate ang mga files sa folder at idagdag sa zip
        $zip->addEmptyDir($folderName);
        $files = File::allFiles($folder);

        foreach ($files as $file) {
            $zip->addFile($file, $folderName . '/' . $file->getRelativePathname());
        }
    }
    
    public function uploadDMOFiles(Request $request){
      
        try{

            if ($request->hasFile('files')) {

                $validated = $request->validate([
                  'files.*' => 'required|file',
                  'account_id' => 'required|exists:accounts,id' 
                ]);
            

            $account = Account::find( $validated['account_id']);
            
           // Query to get the college ID from a specific program.
           $organization = Program::where('program_entity_id',$account->org_log_id)->first();
           $college_id = ($organization) ?  $organization->college_entity_id:"";

            $total_size = 0;
            $uploadedFiles=[];
            $exists_file=[];
            $stat = null;

            
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
            
                    $path=null;
                    if(empty($request->folder_id)){
                       
                        $path = $file->storeAs('uploads',$filename,'public');
                        $stat= stat(Storage::disk('public')->path($path));
                      //  $stat = stat('public/'.$path);
                        $uploadedFiles[] = RequirementFile::create([
                            'requirement_id' => "DMO File",
                            'filename' =>  $filename,
                            'path' => $path,
                            'size' => $file->getSize(),
                            'org_log_id' => $account->org_log_id,
                            'college_entity_id' => $college_id,
                            'ino' => $stat['ino']
                        ]);

                    }else{
                        
                        $data = RequirementFile::find($request->folder_id);
                        $total_size += $file->getSize();
                        $path = $file->storeAs($data->path,$filename,'public');
                        $stat= stat(Storage::disk('public')->path($path));
                        $uploadedFiles[] = RequirementFile::create([
                            'requirement_id' => "DMO File",
                            'filename' =>  $filename,
                            'path' => $path,
                            'size' => $file->getSize(),
                            'org_log_id' =>  $account->org_log_id,
                            'folder_id' => $request->folder_id,
                            'college_entity_id' => $college_id,
                            'ino' => $stat['ino']
                        ]);
                       
                    }
                       // UPDATE THE SIZE OF THE FOLDER
                        if(!empty($request->folder_id)){
                        
                            $main_folder = RequirementFile::find($request->folder_id);

                            $main_folder->update([
                                'size' => $main_folder->size+$total_size
                            ]);
                        }

                  // The records of the user who renamed the file will be saved here. //

                    $file = RequirementFile::where('path',$path)->first();

                    $user = Account::find($validated['account_id']);

                    if($user){

                        HistoryDocument::create([
                            'user_id' => $user->id,
                            'action' => "has created the folder/file",
                            'file_id' =>  $file->id
                        ]);

                    }

                }else{
                    $exists_file[] = $filename;
                }

            }

            if(!empty($uploadedFiles)){

                $response = [
                'isSuccess' => true,
                'message' => "Uploaded Successfully!",
                'upload_files' => $uploadedFiles,
                'exists_file' =>$exists_file
                ];
                $this->logAPICalls('createDMOFiles',"", $request->all(), [$response]);
                return response($response,200);

            }else{
                $response = [
                    'isSuccess' => false,
                    'upload_files' => $uploadedFiles,
                    'exists_file' =>$exists_file
                    ];
                    $this->logAPICalls('createDMOFiles', "", $request->all(), [$response]);
                    return response($response,500);
            }
         // return response(,200)
            return response()->json([
                'isSuccess' => false,
                'message' => 'Files already exist. Please check the file you want to upload.',
                'upload_files' => $uploadedFiles,
                'exists_file' =>$exists_file
            ],500);

        }
        
    //    $this->logAPICalls('createDMOFiles', "", $request->all(), [$response]);
        return response()->json(['message' => 'No file uploaded'], 400);

       }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('createDMOFiles', "", $request->all(), [$response]);
            return response()->json($response, 500);

       }

    }

    public function createDMOFolder(Request $request){

        try{

            $validated= $request->validate([
                'foldername' =>  'required|min:3',
                'account_id' => 'required|exists:accounts,id'
            ]);

            $account = Account::find( $validated['account_id']);
            $organization = Program::where('program_entity_id',$account->org_log_id)->first();
            $college_id = ($organization) ?  $organization->college_entity_id:"";
            
            if(!empty($request->folder_id)){
                $stat = null;
                
                // CREATE FOLDER UNDER FOLDER //
                $data = RequirementFile::where('id',$request->folder_id)->get();
    
                if($data){
    
                    if( !RequirementFile::where('filename', $validated['foldername'])
                                ->where('folder_id',$request->folder_id)
                                ->exists()){
                                
                             if (!Storage::disk('public')->exists($data->first()->filename.'/'.$validated['foldername'])) {
                                 Storage::disk('public')->makeDirectory($data->first()->filename.'/'.$validated['foldername']);
                                 $path = Storage::disk('public')->path($data->first()->filename.'/'.$validated['foldername']);
                                 $stat = stat($path);
                             }
                             
                            $path = $path = $data->first()->filename.'/'.$validated['foldername'];
                            $data = RequirementFile::create([
    
                            'requirement_id' => "DMO File",
                            'filename' => $validated['foldername'],
                            'org_log_id' => $account->org_log_id,
                            'college_entity_id' => $college_id,
                            'path' => $path,
                            'folder_id' => $request->folder_id,
                            'ino' => $stat['ino']
    
                            ]);

                        // The records of the user who created the folder will be saved here. //
                        
                         $file = RequirementFile::where('path',$path)->first();

                            $user = Account::find($validated['account_id']);

                            if($user){

                                HistoryDocument::create([
                                    'user_id' => $user->id,
                                    'action' => "has created the folder/file",
                                    'file_id' =>  $file->id
                                ]);
                        
                             }

                            $response = [
                                'isSuccess' => true,
                                'message' => 'Successfully created'
                            ];
                            $this->logAPICalls('createDMO_folder', $data, $request->all(), [$response]);
                            return response()->json($response);
                    }
    
                 
                    $response = [
                        'isSuccess'=> false,
                        'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'
                    ];

                    $this->logAPICalls('createDMO_folder', "", $request->all(), [$response]);
                    return response()->json($response,500);
                   
    
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

 
                $stat = null;
         
                if( !RequirementFile::where('filename', $validated['foldername'])->exists()){
                    
                    if (!Storage::disk('public')->exists($validated['foldername'])) {
                        // Gumawa ng directory
                        Storage::disk('public')->makeDirectory($validated['foldername']);
                        
                        // get inode of new directory directory
                        $path = Storage::disk('public')->path($validated['foldername']);
                        $stat = stat($path);
                    
                    }
                 
                    $data = RequirementFile::create([
    
                            'requirement_id' => "DMO File",
                            'filename' => $validated['foldername'],
                            'org_log_id' => $account->org_log_id,
                            'path' => $validated['foldername'],
                            'college_entity_id' =>$college_id,
                            'ino' => $stat['ino']

                    ]);
                    
                      // The records of the user who renamed the file will be saved here. //
                      $path = $validated['foldername'];
                      $file = RequirementFile::where('path',$path)->first();

                      $user = Account::find($validated['account_id']);
  
                      if($user){
  
                          HistoryDocument::create([
                              'user_id' => $user->id,
                              'action' => "has created the folder/file",
                              'file_id' =>  $file->id
                          ]);
                          
                      }
                      

                    $response = [
                       'isSuccess' =>true,
                        'message' => 'Successfully created'
                    ];

                    $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                    return response()->json($response,200);
                }

                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The folder you are trying to register already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('storeFolderRequirement', "", $request->all(), [$response]);
                return response()->json($response, 500);
    
            }
    
        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('createDMO_folder', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    
    }

    public function deleteFileFolder(Request $request){
        try{

            $validated = $request->validate([
                'file_id' => 'required|exists:requirement_files,id'
            ]);
    
            $data = RequirementFile::find($validated['file_id']);
            $data->update(
                [
                    'is_archived' => 1
                ]
            );

            $datas = RequirementFile::where('folder_id',$validated['file_id'])->get();

            if($datas->isNotEmpty()){
                foreach($datas as $data){
                    $data->update([
                        'is_archived' => 1
                    ]);
                }
            }

    
            $response = [
                'isSuccess' => true,
                'message' => "Deleted successfully!"
            ];
    
            $this->logAPICalls('deleteFile', "", $request->all(), [$response]);
            return response($response,200);

        }catch(Throwable $e){
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
