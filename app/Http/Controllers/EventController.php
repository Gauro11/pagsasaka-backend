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
use Throwable;
use App\Http\Requests\EventRequest;
use Exception;

class EventController extends Controller
{
    // DONE //
    public function getActiveEvent(){
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
                'org_log_name' => $org_log_data->name,
                'submission_date' => $data->submission_date

            ];
        }
        
       $response = [
         'isSuccess' =>true,
          'activeEvent' =>$event
       ];

       $this->logAPICalls('getEvent', "",[], [$response]);
       return response()->json($response);
       
    }

    // DONE //
    public function getEvent(Request $request){

        try{

            $validated = $request->validate([
                'org_log_id' => 'required|exists:organizational_logs,id',
                'search' => 'nullable|string',
                'academic_year' => 'nullable|string' 
            ]);
            
            $query = Event::where('org_log_id', $validated['org_log_id'])
                    ->where('status','A')
                    ->orderBy('created_at', 'desc');
            
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

            $response = [
                'isSuccess' => true,
                'data' => $data
           ];

            $this->logAPICalls('getEvent', "", $request->all(), [$response]);
            return response()->json($data);
            
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

    // DONE //
    public function storeEvent(EventRequest $request){

        try{

             if ($this->isExist($request->validated())) {
 
                 $response = [
                     'isSuccess'=> false,
                     'message'=> 'The event you are trying to register already exists. Please verify your input and try again.'
                 ];
 
                 $this->logAPICalls('storeEvent', "", $request->all(), [$response]);
 
                 return response()->json($response, 422);
 
             }else{
             
                $organizationalLog = OrganizationalLog::where('id', $request->validated())->get();

                // Test if the input org_log_id is for College. //

               if($organizationalLog->first()->org_id != "1"){

                    $exists = AcademicYear::where('name', $request->academic_year)
                                ->exists();

                    if(!$exists){
                        AcademicYear::create(['name' => $request->academic_year]);
                    }
                    
                    $program = Program::where('program_entity_id', $request->validated('org_log_id'))->get();
                    $college_id = !$program->isEmpty() ? $program->first()->college_entity_id : null;

                    Event::create(array_merge($request->validated(), [
                        "college_entity_id" => $college_id,
                    ]));

            
                    $eventid= $this->getEventID($request->name,$request->description,$request->academic_year,$request->submission_date);

                    $invalidRequirements = [];
                    $duplicateRequirements = [];

                    foreach ($request->requirements as $requirement) {
                        if (!OrganizationalLog::find($requirement['org_log_id'])) {
                
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

                    $this->logAPICalls('storeEvent', "", $request->all(), [$response]);
                    return response()->json($response);

                }else{
                
                 //  Response when input detected corresponds to a College.  //

                    $response = [
                        'isSuccess' => false,
                        'message' => "You are attempting to input org_log_id for College. Please note that only programs and offices have the authority to create events."
                   ];

                    $this->logAPICalls('storeEvent', "", $request->all(), [$response]);
                    return response()->json($response, 500);

                }
             }
 
              
          }catch (Exception $e) {
  
              $response = [
                  'isSuccess' => false,
                  'message' => "Unsucessfully created. Please check your inputs.",
                  'error' => 'An unexpected error occurred: ' . $e->getMessage()
             ];
  
              $this->logAPICalls('storeEvent', "", $request->all(), [$response]);
              return response()->json($response, 500);
  
          }
 
    }

    // DONE //
    public function editEvent(Request $request){

        try{

            $request->validate([

                'id' => 'required|exists:events,id'
            ]);

            $data = Event::find($request->id);

            $response = [
                'isSuccess' => true,
                    'data' => $data
            ];
   
           $this->logAPICalls('editEvent', "", $request->all(), [$response]);
           return response()->json($response);
   
        }catch(Exception $e){
   
            $response = [
                   'isSuccess' => false,
                   'message' => "Unsuccessfully edited. Please check your inputs.",
                   'error' => 'An unexpected error occurred: ' . $e->getMessage()
              ];
   
               $this->logAPICalls('editEvent', "", $request->all(), [$response]);
               return response()->json($response, 500);
        }
          
    }

    // DONE //
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

        }catch(Exception $e){
            $response = [
                'isSuccess' => false,
                'message' => "Unsucessfully updated. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
           ];

            $this->logAPICalls('updateEvent', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
        
    }

    // DONE //
    public function deleteEvent(Request $request){

        try{

            $validated = $validate = $request->validate([
                'id' => 'required|exists:events,id'
            ]);
    
            // Find the event by ID
            $event = Event::find($request->id);
    
            // Check if the event exists (although validation should handle this)
            if (!$event) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "Event not found."
                ], 404);
            }
    
            // Hard delete the event from the database
            $event->delete();
    
            // Return success response
            $response = [
                'isSuccess' => true,
                'message' => "Event successfully deleted."
            ];
    
            // Log API call
            $this->logAPICalls('deleteEvent', "", $request->all(), [$response]);
    
            return response()->json($response);
    
        } catch (Exception $e) {
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
    

    // DONE //
    public function getAcademicYear(){
        try{
            $data = AcademicYear::all();
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];
    
            $this->logAPICalls('getAcademicYear', "", [], [$response]);  // No $request data needed here
            return response()->json($response, 200);
    
        } catch (Exception $e) {  // Use Exception instead of Throwable for consistency
    
            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];
    
            // Removed the undefined $request variable
            $this->logAPICalls('getAcademicYear', "", [], [$response]);
            return response()->json($response, 500);
        }
    }
    

    // DONE //
    public function viewEvent(Request $request){

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
                'viewEvent' =>  [
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

            $this->logAPICalls('viewEvent', "", $request->all(), [$response]);
            return response()->json($response);
    
        } catch (Exception $e) {
            // Handle the exception and return an error response
            $response = [
                'isSuccess' => false,
                'message' => "An unexpected error occurred. Please contact support.",
                'error' => $e->getMessage()  // Return the actual error message
            ];
    
            // Log the API call with the error response
            $this->logAPICalls('viewEvent', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }
    
    // DONE //
    public function getEventID($name,$descrip,$acadyear,$submdate){
        $event= Event::where('name',$name)
                      ->where('description',$descrip)
                      ->where('academic_year',$acadyear)
                      ->where('submission_date',$submdate)
                      ->get();

        return $event->first()->id;
    }

    // DONE //
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

}


