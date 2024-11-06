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
use File;
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
                                ->where('status','A')->orderBy('created_at', 'desc');

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
                'updated_at' => $dateTime
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


    public function getFolder(Request $request){

       try{

            $validated = $request->validate([
                'folder_id' => ['required'],
                'search' => ['nullable', 'string'], 
            ]);
            
            // Query the 'requirement_files' table base on their folder.
            $query = RequirementFile::where('folder_id', $validated['folder_id'])
                                      ->where('status','A');
            
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
                    'status' => $data->status,
                ];
            }
            
            $response = [
                'isSuccess' => true,
                'folder' =>  $allfiles
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
                'name' => 'required|max:20',
                'folder' => 'required'
            ]);
        
           $data = RequirementFile::where('id',$validated['file_id'])->first();
           $newFolderName ="";
           
          $parts = explode('/', $data->path);
          $filename = $validated['name'];

                if ($validated['folder'] == 0) {
                        $file_extension = pathinfo( $parts[count($parts)-1], PATHINFO_EXTENSION);
                    $filename .= '.' . $file_extension; // Add the extension back
                }
                $parts[count($parts)-1]=$filename;
                $newFolderPath = (count($parts) > 1) ? implode('/', $parts) : $filename;
     

            if(!RequirementFile::where('path',$newFolderPath)
                                ->where('filename',$validated['name'])
                                ->exists()){
                               
                                $currentDateTime = Carbon::now();

                                
                        
                                if (Storage::disk('public')->exists($data->path)) {
                                
                                  Storage::disk("public")->move($data->path,$newFolderPath);
                                 
                                }

                                   

                    if($validated['folder'] != 0)  {
                        $this->updateChildPaths($data->path, $newFolderPath, $validated['file_id']);
                    }            

                    $data->update([
                     'filename' => $filename,
                     'path' => $newFolderPath
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


            try {

                $validated  = $request->validate([
                    'file_id' => 'required'
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

                        // Return the ZIP file as a downloadable response
                        return response()->download($zipFilePath)->deleteFileAfterSend(true);

                    } else {

                        return response()->json(['error' => 'ZIP file creation failed.'], 500);
                    }

                } else {
                    return response()->json(['error' => 'Failed to create the ZIP file.'], 500);
                }

            } catch (Exception $e) {
                
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
    
    public function storeDMO_files(Request $request){
      
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
                }else{
                    $exists_file[] = $filename;
                }
               
            }

            // return response()->json([
            //     'isSuccess' => true,
            //     'upload_files' => $uploadedFiles,
            //     'exists_file' =>$exists_file
            // ],200);
            if(!empty($uploadedFiles)){

                $response = [
                'isSuccess' => true,
                'message' => "Uploaded Successfully!",
                'upload_files' => $uploadedFiles,
                'exists_file' =>$exists_file
                ];
                $this->logAPICalls('storeDMO_files',"", $request->all(), [$response]);
                return response($response,200);

            }else{
                $response = [
                    'isSuccess' => false,
                    'upload_files' => $uploadedFiles,
                    'exists_file' =>$exists_file
                    ];
                    $this->logAPICalls('storeDMO_files', "", $request->all(), [$response]);
                    return response($response,500);
            }
         //  return response(,200)
            // return response()->json([
            //     'isSuccess' => false,
            //     'message' => 'Files already exist. Please check the file you want to upload.',
            //     'upload_files' => $uploadedFiles,
            //     'exists_file' =>$exists_file
            // ],500);

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

            $validate= $request->validate([
                'foldername' =>  'required|min:3',
                'account_id' => 'required|exists:accounts,id'
            ]);
            
            $account = Account::find( $validate['account_id']);
            $organization = Program::where('program_entity_id',$account->org_log_id)->first();
            $college_id = ($organization) ?  $organization->college_entity_id:"";
            
            if(!empty($request->folder_id)){
                $stat = null;
                
                // CREATE FOLDER UNDER FOLDER //
                $data = RequirementFile::where('id',$request->folder_id)->get();
    
                if($data){
    
                    if( !RequirementFile::where('filename', $validate['foldername'])
                                ->where('folder_id',$request->folder_id)
                                ->exists()){
                          
                             if (!Storage::disk('public')->exists($data->first()->filename.'/'.$validate['foldername'])) {
                                 Storage::disk('public')->makeDirectory($data->first()->filename.'/'.$validate['foldername']);
                                 $path = Storage::disk('public')->path($data->first()->filename.'/'.$validate['foldername']);
                                 $stat = stat($path);
                             }
                           
                            $data = RequirementFile::create([
    
                            'requirement_id' => "DMO File",
                            'filename' => $validate['foldername'],
                            'org_log_id' => $account->org_log_id,
                            'college_entity_id' => $college_id,
                            'path' => $data->first()->filename.'/'.$validate['foldername'],
                            'folder_id' => $request->folder_id,
                            'ino' => $stat['ino']
    
                            ]);

            
                            $response = [
                                'isSuccess' => true,
                                'message' => 'Successfully created',
                                'folder' => $data
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
               // CREATE FOLDER UNDER REQUIREMENT //
                if( !RequirementFile::where('filename', $validate['foldername'])->exists()){
                    
                    if (!Storage::disk('public')->exists($validate['foldername'])) {
                        // Gumawa ng directory
                        Storage::disk('public')->makeDirectory($validate['foldername']);
                        
                        // get inode of new directory directory
                        $path = Storage::disk('public')->path($validate['foldername']);
                        $stat = stat($path);
                    
                    }
              
                    $data = RequirementFile::create([
    
                            'requirement_id' => "DMO File",
                            'filename' => $validate['foldername'],
                            'org_log_id' => $account->org_log_id,
                            'path' => $validate['foldername'],
                            'college_entity_id' =>$college_id,
                            'ino' => $stat['ino']

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

            $datas = RequirementFile::where('folder_id',$validated['file_id'])->get();

            if($datas->isNotEmpty()){
                foreach($datas as $data){
                    $data->update([
                        'status' => 'I'
                    ]);
                }
            }

    
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
