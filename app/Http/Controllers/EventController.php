<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Requirement;
use App\Models\Event;
use App\Models\OrganizationLog;
use App\Models\Program;
use App\Models\ApiLog;
use App\Models\AcademicYear;
use App\Models\OrganizationalLog;
use Illuminate\Support\Facades\Storage;
use Throwable;
use App\Http\Requests\EventRequest;
use Exception;

class EventController extends Controller
{


  
    public function getActiveEvent(){
        
       try{

            $event =[];

            $datas = Event::where('status','A')
                                ->orderBy('created_at','desc')->get();

                foreach($datas as $data){

                    $org_log_data = OrganizationalLog::where('id', $data->org_log_id)->first();

                    $event[] =[

                        'id' => $data->id,
                        'name'=> $data->name,
                        'description' => $data->description,
                        'org_log_id' => $data->org_log_id,
                        'org_log_name' =>$org_log_data ? $org_log_data->name :null,
                        'submission_date' => $data->submission_date

                    ];
                }
                
            $response = [
                'isSuccess' =>true,
                'activeEvent' =>$event
            ];

            $this->logAPICalls('getActiveEvent', "",[], [$response]);
            return response()->json($response);

       }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getActiveEvent', "",[], [$response]);
            return response()->json($response, 500);

       }
       
    }

    public function getEvent(Request $request){

        try{

            $validated = $request->validate([
                'org_log_id' => 'required|exists:organizational_logs,id', // ID Of Colleges/Offices/Programs
                'search' => 'nullable|string',
                'academic_year' => 'nullable|string' 
            ]);
            

            $query = Event::where('org_log_id', $validated['org_log_id'])
                    ->where('is_archived',0)

                    ->orderBy('created_at', 'desc');
                    
            $events = $query->get();

            // Wrap the $query result into a Laravel Collection para po sa mga requirements na query ko po
            $eventCollection = collect($events);

            $org_requirements =  Requirement::where('org_log_id',$validated['org_log_id'])
                                            ->where('is_archived',0)->get();
            
            if( $org_requirements->isNotEmpty()){

                foreach($org_requirements as $requirement){

                    if (!$eventCollection->contains('id', $requirement->event_id)){
                        
                        $data = Event::find($requirement->event_id);

                        if($data){
                            $eventCollection->push($data);
                        }

                    }

                }

                if (!empty($validated['search'])) {
                    // Use the collection's `filter` method to search the events by name
                    $eventCollection = $eventCollection->filter(function ($event) use ($validated) {
                        return strpos(strtolower($event->name), strtolower($validated['search'])) !== false;
                    });
                }

                if (!empty($validated['academic_year'])) {
                    // Filter the collection by academic_year
                    $eventCollection = $eventCollection->filter(function ($event) use ($validated) {
                        return $event->academic_year == $validated['academic_year'];
                    });
                }

                // Sort the collection in descending order by `created_at`
                $eventCollection = $eventCollection->sortByDesc(function ($event) {
                    return $event['created_at']; 
                });

                $data =  $eventCollection->values();
                
            }else{

                if (!empty($validated['search'])) {
                    $query->where('name', 'like', '%' . $validated['search'] . '%')
                    ->where('status','A')
                    ->orderBy('created_at', 'desc');
                }
                
                if (!empty($validated['academic_year'])) {
                    $query->where('academic_year', $validated['academic_year'])
                    ->where('status','A')
                    ->orderBy('created_at', 'desc');
                }
               
                $data = $query->get();
            }
   
            $org_name = OrganizationalLog::find($validated['org_log_id']);

            $response = [
                'isSuccess' => true,
                'events' => $data,
                'org_name' => $org_name->name
           ];

            $this->logAPICalls('getEvent', "", $request->all(), [$response]);
            return response()->json($response);
            
        }catch(Throwable $e){

            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('getEvent', "", $request->all(), [$response]);
            return response()->json($response, 500);

        }

    }
 
    public function createEvent(EventRequest $request){

        try{
            
             if ($this->isExist($request->validated())) {
 
                 $response = [
                     'isSuccess'=> false,
                     'message'=> 'The event you are trying to register already exists. Please verify your input and try again.'
                 ];
 
                 $this->logAPICalls('createEvent', "", $request->all(), [$response]);
 
                 return response()->json($response, 500);
 
             }else{
               
                $this->makeFolder($request->org_log_id, $request->name,$request->requirements); // Create ng folder sa repository base kung anong offices/programs/colleges and mag create rin ng subfolder kung ano mga event naka assign sa kanila.
              
                $organizationalLog = OrganizationalLog::where('id', $request->validated())->get();

               // Test if the input org_log_id is for College. //
               if($organizationalLog->first()->org_id != "1"){

                    $program = Program::where('program_entity_id', $request->validated('org_log_id'))->get();
                    $college_id = !$program->isEmpty() ? $program->first()->college_entity_id : null;

                    Event::create(array_merge($request->validated(), [
                        "college_entity_id" => $college_id,
                    ]));

                    $eventid= $this->getEventID($request->org_log_id,$request->name,$request->description,$request->academic_year,$request->submission_date);

                    $invalidRequirements = [];
                    $duplicateRequirements = [];

                    foreach ($request->requirements as $requirement) {

                        if ($requirement['org_log_id'] == null) {
                            $validated = $request->validated();
                            $requirement['org_log_id'] = $validated['org_log_id'];
                        }
                      
                        if (!OrganizationalLog::find($requirement['org_log_id'])  ) {
                
                            $invalidRequirements[] = $requirement;
                            continue; 
                        }

                        if(Requirement::where('event_id', $eventid)
                                    ->where('name', $requirement['name'])
                                    ->where('org_log_id',$requirement['org_log_id'])->exists()){
                                    
                                    $duplicateRequirements[] = $requirement;

                        }else{

                            Requirement::create([
                                'name' => $requirement['name'],
                                'org_log_id' => $requirement['org_log_id'],
                                'event_id' => $eventid,
                                'upload_status' => "pending"
                            ]);

                        }

                       
                    }
                    
                    $response = [
                            'isSuccess' => true,
                                'message' => "Successfully created.",
                                'invalid_requirements' => $invalidRequirements,
                                'duplicate_requirements' => $duplicateRequirements
                        ];

                    $this->logAPICalls('createEvent', "", $request->all(), [$response]);
                    return response()->json($response);

                }else{
                
                 //  Response when input detected corresponds to a College.  //
                    $response = [
                        'isSuccess' => false,
                        'message' => "You are attempting to input org_log_id for College. Please note that only programs and offices have the authority to create events."
                   ];

                    $this->logAPICalls('createEvent', "", $request->all(), [$response]);
                    return response()->json($response, 500);

                }
             }
 
              
          }catch (Throwable $e) {
  
              $response = [
                  'isSuccess' => false,
                  'message' => "Unsucessfully created. Please check your inputs.",
                  'error' => 'An unexpected error occurred: ' . $e->getMessage()
             ];
  
              $this->logAPICalls('storeEvent', "", $request->all(), [$response]);
              return response()->json($response, 500);
  
          }
 
    }

    public function updateEvent(Request $request){

        try{

            $validated = $validate = $request->validate([
                'id' => 'required|exists:events,id',
                'name' => 'required'
            ]);
            
            $data = Event::find($validated['id']);

            $exist = Event::where('name',$validate['name'])
                            ->where('org_log_id',$data->org_log_id)
                            ->exists();
            if($exist) {
    
                $response = [
                    'isSuccess'=> false,
                    'message'=> 'The event you are trying to update already exists. Please verify your input and try again.'
                ];
    
                $this->logAPICalls('updateEvent', "", $request->all(), [$response]);
                return response()->json($response, 422);
    
            }else{

                $data ->update(['name' => $validate['name']]);
           
                $response = [
                          'isSuccess' => true,
                           'message' => "Successfully updated."
                ];
    
                $this->logAPICalls('updateEvent', "", $request->all(), [$response]);
                return response()->json($response);
            }

        }catch(Throwable $e){
            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully updated. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('updateEvent', "", $request->all(), [$response]);
            return response($response, 500);
        }
        
    }

    public function deleteEvent(Request $request){

        try{

            $validated = $request->validate([
                'id' => 'required|exists:events,id'
            ]);
    
            // Find the event by ID
            $event = Event::find($validated['id']);
    
            // Check if the event exists (although validation should handle this)
            if (!$event) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "Event not found."
                ], 404);
            }
    
            //delete the event from the database
            $event->update(
                [
                    'is_archived' =>  1
                ]
            );

             //delete the requirements under that event
            $requirements = Requirement::where('event_id',$validated['id'])->where('is_archived',0)->get();

            if($requirements){
                foreach($requirements as $requirement){
                    $requirement->update([
                        'is_archived' =>  1
                    ]);
                }
            }

            // Return success response
            $response = [
                'isSuccess' => true,
                'message' => "Event successfully deleted."
            ];
    
            // Log API call
            $this->logAPICalls('deleteEvent', "", $request->all(), [$response]);
    
            return response()->json($response);
    
        } catch (Throwable $e) {
            // Handle exceptions
            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully deleted. Please try again.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];
    
            $this->logAPICalls('deleteEvent', "", $request->all(), [$response]);
    
            return response()->json($response, 500);
        }
    }

    public function eventDetails(Request $request){

        try{

            $validated = $request->validate([
                'id' => 'required|exists:events,id'
            ]);

            $data = Event::find($request->id);

           $college = OrganizationalLog::where('id',$data->college_entity_id)->get();
           $collegeName = $college->isNotEmpty() ? $college->first()->name : null;
           $collegeAcronym = $college->isNotEmpty() ? $college->first()->acronym : null;

            $response = [
                'isSuccess' => true,
                'event-details' =>  [
                        'id' => $data->id,
                        'name' => $data->name,
                        'org_log_id' => $data->org_log_id,
                        'college_entity_id' => $data->college_entity_id,
                        'college_name' => $collegeName,
                        'college_acronym' => $collegeAcronym,
                        'description' => $data->description,
                        'academic_year' => $data->academic_year,
                        'submission_date' => $data->submission_date,
                        'qtyfile' => $data->qtyfile,
                        'date_modified' => $data->date_modified,
                        'status' => $data->status,
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                    ]
                
           ];

            $this->logAPICalls('eventDetails', "", $request->all(), [$response]);
            return response()->json($response);
    
        } catch (Exception $e) {
            // Handle the exception and return an error response
            $response = [
                'isSuccess' => false,
                'message' => "An unexpected error occurred. Please contact support.",
                'error' => $e->getMessage()  // Return the actual error message
            ];
    
            // Log the API call with the error response
            $this->logAPICalls('eventDetails', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function eventApprovalStatus(Request $request){
        
        ////////////////////////////////////////////////////////
        //  PROGRAM CHAIR AND HEAD -> status: submited
        //  DEAN -> status: endorsed
        //  STAFF -> status : validated
        //  ADMIN -> status : Approved
        ////////////////////////////////////////////////////////

        try{

            $validated = $request->validate([
                'event_id' => 'required|exists:events,id',
                'status' => 'required'
            ]);
    
            $event = Event::find($validated['event_id']);
            
            if($event->update([
                'approval_status' => $validated['status']
            ])){
    
                $response = [
                    'isSuccess' => true,
                     'message' => "Successfully updated."
                ];
    
                $this->logAPICalls('approvalStatus', "", $request->all(), [$response]);
                return response()->json($response);
            }
    
        }catch(Throwable $e){
            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully updated. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('approvalStatus', "", $request->all(), [$response]);
            return response($response, 500);
        }

    }

    public function getEventID($org_log_id,$name,$descrip,$acadyear,$submdate){

        $event= Event::where('name',$name)
                      ->where('description',$descrip)
                      ->where('org_log_id',$org_log_id)
                      ->where('academic_year',$acadyear)
                      ->where('submission_date',$submdate)
                      ->get();

        return $event->first()->id;
    }

    public function isExist($validate){

        return Event::where('org_log_id', $validate['org_log_id'])
        ->where('name', $validate['name'])
        ->where('description', $validate['description'])
        ->where('academic_year', $validate['academic_year'])
        ->where('submission_date', $validate['submission_date'])
        ->exists();

    }
  
    public function logAPICalls(string $methodName, string $userId, array $param, array $resp){
        try
        {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' =>  json_encode($param),
                'api_response' =>  json_encode($resp)
            ]);
        }
        catch(Throwable $e){
            return false;
        }
        return true;
    }

    public function makeFolder($org_log_id,$eventName,$requirements){

        $organization = OrganizationalLog::find($org_log_id);
        $organization_name = $organization->name;

        if($organization->org_id != 3){

            $folderPath = $organization_name;
            if (!Storage::disk('public')->exists($folderPath)) {
                Storage::disk('public')->makeDirectory($folderPath);
                $folderPath = $organization_name.'/'.$eventName;
                    
                    if (!Storage::disk('public')->exists($folderPath)) {
                        Storage::disk('public')->makeDirectory($folderPath);
                       
                    }
                
                    $this->makeRequirement($requirements, $folderPath);

            }else{
                $folderPath = $organization_name.'/'.$eventName;
                    
                if (!Storage::disk('public')->exists($folderPath)) {
                    Storage::disk('public')->makeDirectory($folderPath);
                    
                }

                $this->makeRequirement($requirements, $folderPath);
            }
        }else{
            $program = Program::where('program_entity_id', $organization->id)->first();
            $college = OrganizationalLog::find($program->college_entity_id);
            $college_name = $college->name;

            if (!Storage::disk('public')->exists($college_name)) {

                Storage::disk('public')->makeDirectory($college_name);
                $folderPath = $college_name.'/'.$organization->name;
                    
                if (!Storage::disk('public')->exists($folderPath)) {
                    Storage::disk('public')->makeDirectory($folderPath);
                }

                $folderPath = $college_name.'/'.$organization->name.'/'.$eventName;
                if (!Storage::disk('public')->exists($folderPath)) {
                    Storage::disk('public')->makeDirectory($folderPath);
                }

             $this->makeRequirement($requirements, $folderPath);
            
               
            }else{

                $folderPath = $college_name.'/'.$organization->name;
                    
                if (!Storage::disk('public')->exists($folderPath)) {
                    Storage::disk('public')->makeDirectory($folderPath);
                    $folderPath = $college_name.'/'.$organization->name.'/'.$eventName;
                    if (!Storage::disk('public')->exists($folderPath)) {
                        Storage::disk('public')->makeDirectory($folderPath);
                    }
                }else{
                    $folderPath = $college_name.'/'.$organization->name.'/'.$eventName;
                    if (!Storage::disk('public')->exists($folderPath)) {
                        Storage::disk('public')->makeDirectory($folderPath);
                    }
                }

                $this->makeRequirement($requirements, $folderPath );
            
            }
        }
    }

    public function makeRequirement($requirements,$path){
      
       foreach($requirements as $requirement){
         $folderPath = $path.'/'.$requirement['name'];
         if (!Storage::disk('public')->exists($folderPath)) {
            Storage::disk('public')->makeDirectory($folderPath);
         }
       }
    }
}


